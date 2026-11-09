<?php

namespace App\Modules\Auth\Threading;

use App\Modules\Auth\Ldap\Ber;
use App\Modules\Auth\Ldap\Directory;
use App\Modules\Auth\Ldap\Protocol;
use Colibri\Threading\Worker;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Runs the Auth LDAP listener using native PHP streams.
 *
 * @class
 * @memberof App\Modules\Auth\Threading
 * @extends Worker
 */
class LdapWorker extends Worker
{
    /**
     * Whether the foreground listener should keep accepting requests.
     * @var bool
     * @private
     * @ignore
     */
    private bool $_running = true;

    /**
     * Per-address Bind rate windows, shared across reconnects.
     * @var array
     * @private
     * @ignore
     */
    private array $_bindWindows = [];

    /**
     * Builds the selected application directory from prepared parameters.
     * @public
     * @return void
     */
    public function Run(): void
    {
        $options = (array)$this->_params;
        $directory = new Directory((string)($options['application'] ?? ''), (string)($options['token'] ?? ''));
        unset($options['token']);
        $this->Serve($directory, $options);
    }

    /**
     * Serves bounded, nonblocking connections in the current worker process.
     * @public
     * @param Directory $directory Application directory.
     * @param array $options Listener URI, TLS certificate/key and test override.
     * @return void
     */
    public function Serve(Directory $directory, array $options = []): void
    {
        $listen = (string)($options['listen'] ?? 'tcp://127.0.0.1:1389');
        $address = parse_url($listen);
        if ($address === false || !in_array($address['scheme'] ?? '', ['tcp', 'tls'], true)
            || !isset($address['host'], $address['port']) || isset($address['path'])
            || isset($address['user']) || isset($address['query']) || isset($address['fragment'])
            || $address['port'] < 0 || $address['port'] > 65535) {
            throw new InvalidArgumentException('Use tcp://address:port or tls://address:port');
        }
        $tls = $address['scheme'] === 'tls';
        if (!$tls && !in_array($address['host'], ['127.0.0.1', '[::1]'], true) && empty($options['allow-insecure'])) {
            throw new InvalidArgumentException('Non-loopback LDAP requires TLS or explicit --allow-insecure for isolated tests');
        }
        $ssl = [];
        if ($tls) {
            if (empty($options['cert']) || empty($options['key']) || !is_readable($options['cert']) || !is_readable($options['key'])) {
                throw new InvalidArgumentException('TLS requires readable --cert and --key files');
            }
            $ssl = [
                'local_cert' => $options['cert'],
                'local_pk' => $options['key'],
                'verify_peer' => false,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
                'disable_compression' => true,
            ];
        }
        $context = stream_context_create(['ssl' => $ssl]);
        $server = @stream_socket_server('tcp://' . $address['host'] . ':' . $address['port'], $errorCode, $errorMessage, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($server === false) {
            throw new RuntimeException('Unable to bind LDAP listener: ' . $errorMessage, $errorCode);
        }
        stream_set_blocking($server, false);
        $this->_running = true;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->Stop());
            pcntl_signal(SIGINT, fn() => $this->Stop());
        }
        fwrite(STDOUT, 'Auth LDAP listening on ' . $address['scheme'] . '://' . stream_socket_get_name($server, false) . ' base=' . $directory->GetBaseDn() . PHP_EOL);
        $clients = [];
        try {
            while ($this->_running) {
                $read = [$server];
                $write = [];
                $pending = false;
                foreach ($clients as $client) {
                    $read[] = $client['socket'];
                    if ($client['output'] !== '') {
                        $write[] = $client['socket'];
                    }
                    $pending = $pending || $client['pending'];
                }
                $except = null;
                if (@stream_select($read, $write, $except, 0, $pending ? 0 : 200000) === false) {
                    if (!$this->_running) {
                        break;
                    }
                    throw new RuntimeException('LDAP socket selection failed');
                }
                if (in_array($server, $read, true)) {
                    $socket = @stream_socket_accept($server, 0, $peer);
                    if ($socket !== false) {
                        if (count($clients) >= 64) {
                            fclose($socket);
                        } else {
                            stream_set_blocking($socket, false);
                            $clients[(int)$socket] = [
                                'socket' => $socket,
                                'peer' => preg_replace('/:\d+$/D', '', $peer),
                                'protocol' => new Protocol($directory),
                                'input' => '',
                                'output' => '',
                                'last' => microtime(true),
                                'encrypted' => !$tls,
                                'pending' => false,
                            ];
                        }
                    }
                }
                foreach ($clients as $id => &$client) {
                    try {
                        if (microtime(true) - $client['last'] > ($client['encrypted'] ? 60 : 5)) {
                            throw new RuntimeException('Idle connection');
                        }
                        if (!$client['encrypted']) {
                            $handshake = @stream_socket_enable_crypto($client['socket'], true, $ssl['crypto_method']);
                            if ($handshake === false) {
                                throw new RuntimeException('TLS handshake failed');
                            }
                            $client['encrypted'] = $handshake === true;
                            continue;
                        }
                        if (in_array($client['socket'], $read, true)) {
                            $bytes = @fread($client['socket'], 8192);
                            if ($bytes === false || ($bytes === '' && feof($client['socket']))) {
                                throw new RuntimeException('Connection closed');
                            }
                            if ($bytes !== '') {
                                $client['input'] .= $bytes;
                                $client['last'] = microtime(true);
                            }
                            if (strlen($client['input']) > Ber::MaxMessageSize) {
                                throw new RuntimeException('Input buffer limit');
                            }
                        }
                        $client['pending'] = false;
                        for ($iteration = 0; $iteration < 8; $iteration++) {
                            $frame = Ber::TakeFrame($client['input']);
                            if ($frame === null) {
                                break;
                            }
                            if (!$this->_allowRequest($client['peer'], $frame)) {
                                throw new RuntimeException('Bind rate limit');
                            }
                            $response = $client['protocol']->Handle($frame);
                            if ($response === null) {
                                throw new RuntimeException('Unbind');
                            }
                            $client['output'] .= $response;
                            if (strlen($client['output']) > 4194304) {
                                throw new RuntimeException('Output buffer limit');
                            }
                            $client['pending'] = $iteration === 7;
                        }
                        if ($client['output'] !== '' && in_array($client['socket'], $write, true)) {
                            $written = @fwrite($client['socket'], $client['output']);
                            if ($written === false) {
                                throw new RuntimeException('Write failed');
                            }
                            if ($written > 0) {
                                $client['output'] = substr($client['output'], $written);
                                $client['last'] = microtime(true);
                            }
                        }
                    } catch (Throwable $exception) {
                        fclose($client['socket']);
                        unset($clients[$id]);
                    }
                }
                unset($client);
            }
        } finally {
            foreach ($clients as $client) {
                fclose($client['socket']);
            }
            fclose($server);
        }
    }

    /**
     * Requests graceful termination of the listener.
     * @public
     * @return void
     */
    public function Stop(): void
    {
        $this->_running = false;
    }

    /**
     * Limits Bind attempts per remote address, including reconnects.
     * @private
     * @ignore
     * @param string $peer Remote address without its port.
     * @param string $frame Complete LDAPMessage.
     * @return bool Whether the operation may proceed.
     */
    private function _allowRequest(string $peer, string $frame): bool
    {
        $message = Ber::Read($frame, 48)['value'];
        Ber::Read($message, 2);
        if (Ber::Read($message)['tag'] !== 96) {
            return true;
        }
        $now = time();
        foreach ($this->_bindWindows as $address => $window) {
            if ($now - $window['start'] >= 60) {
                unset($this->_bindWindows[$address]);
            }
        }
        if (!isset($this->_bindWindows[$peer])) {
            if (count($this->_bindWindows) >= 1024) {
                return false;
            }
            $this->_bindWindows[$peer] = ['start' => $now, 'count' => 0];
        }
        return ++$this->_bindWindows[$peer]['count'] <= 30;
    }
}