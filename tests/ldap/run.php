<?php

use App\Modules\Auth\Ldap\Ber;
use App\Modules\Auth\Ldap\Directory;
use App\Modules\Auth\Ldap\Protocol;
use App\Modules\Auth\Threading\LdapWorker;

require_once __DIR__ . '/../../src/Auth/Ldap/Ber.php';
require_once __DIR__ . '/../../src/Auth/Ldap/Directory.php';
require_once __DIR__ . '/../../src/Auth/Ldap/Protocol.php';

/**
 * Provides deterministic LDAP data without loading production credentials.
 * @class
 * @memberof global
 * @extends Directory
 */
class FixtureDirectory extends Directory
{
    /**
     * Whether an established Bind remains authorized.
     * @var bool
     * @private
     * @ignore
     */
    private bool $_authorized = true;

    /**
     * Avoids application storage access in protocol tests.
     * @public
     */
    public function __construct()
    {
    }

    /**
     * Returns the fixture naming context.
     * @public
     * @return string Base DN.
     */
    public function GetBaseDn(): string
    {
        return 'dc=tasks';
    }

    /**
     * Authenticates fixture credentials only.
     * @public
     * @param string $identity Presented identity.
     * @param string $password Presented password.
     * @return string|null Fixture token or null.
     */
    public function Authenticate(string $identity, string $password): ?string
    {
        if ($identity === 'alice' && $password === 'fixture-password') {
            return 'fixture-token';
        }
        return null;
    }

    /**
     * Checks a fixture account's live authorization state.
     * @public
     * @param string $token Bound identity.
     * @return bool Whether access remains valid.
     */
    public function IsAuthorized(string $token): bool
    {
        return $this->_authorized && $token === 'fixture-token';
    }

    /**
     * Simulates blocking an already authenticated member.
     * @public
     * @return void
     */
    public function Revoke(): void
    {
        $this->_authorized = false;
    }

    /**
     * Streams users and groups in canonical directory order.
     * @public
     * @return iterable Fixture entries.
     */
    public function Entries(): iterable
    {
        yield ['dn' => 'dc=tasks', 'attributes' => ['objectclass' => ['top', 'domain'], 'dc' => ['tasks']]];
        yield ['dn' => 'ou=users,dc=tasks', 'attributes' => ['objectclass' => ['top', 'organizationalUnit'], 'ou' => ['users']]];
        yield ['dn' => 'ou=groups,dc=tasks', 'attributes' => ['objectclass' => ['top', 'organizationalUnit'], 'ou' => ['groups']]];
        foreach (['alice', 'bob'] as $name) {
            yield ['dn' => 'uid=' . $name . ',ou=users,dc=tasks', 'attributes' => [
                'objectclass' => ['top', 'person', 'inetOrgPerson'],
                'uid' => [$name], 'cn' => [ucfirst($name)], 'sn' => [ucfirst($name)],
                'mail' => [$name . '@example.test'], 'memberof' => ['cn=user,ou=groups,dc=tasks'],
            ]];
        }
        yield ['dn' => 'cn=user,ou=groups,dc=tasks', 'attributes' => [
            'objectclass' => ['top', 'groupOfNames'], 'cn' => ['user'],
            'member' => ['uid=alice,ou=users,dc=tasks', 'uid=bob,ou=users,dc=tasks'],
        ]];
    }
}

/**
 * Fails immediately when a focused regression assertion does not hold.
 * @param bool $condition Expected invariant.
 * @param string $message Assertion description.
 * @return void
 */
function Check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * Encodes an LDAP request envelope.
 * @param int $tag Operation tag.
 * @param string $value Encoded operation.
 * @param string $controls Optional encoded controls.
 * @return string Encoded request.
 */
function Request(int $tag, string $value, string $controls = ''): string
{
    return Ber::Encode(48, Ber::Integer(1) . Ber::Encode($tag, $value) . $controls);
}

/**
 * Builds a simple Bind request.
 * @param string $name Identity.
 * @param string $password Password.
 * @return string Encoded Bind request.
 */
function BindRequest(string $name = 'alice', string $password = 'fixture-password'): string
{
    return Request(96, Ber::Integer(3) . Ber::Encode(4, $name) . Ber::Encode(128, $password));
}

/**
 * Builds a Search request for fixture tests.
 * @param string $base Search base.
 * @param int $scope Search scope.
 * @param string|null $filter Encoded filter.
 * @param array $attributes Requested attribute names.
 * @param int $limit Requested size limit.
 * @param bool $typesOnly Whether to omit values.
 * @return string Encoded Search request.
 */
function SearchRequest(string $base = 'dc=tasks', int $scope = 2, ?string $filter = null, array $attributes = [], int $limit = 0, bool $typesOnly = false): string
{
    $selection = '';
    foreach ($attributes as $attribute) {
        $selection .= Ber::Encode(4, $attribute);
    }
    return Request(99, Ber::Encode(4, $base) . Ber::Integer($scope, 10) . Ber::Integer(0, 10)
        . Ber::Integer($limit) . Ber::Integer(5) . Ber::Encode(1, $typesOnly ? "\xff" : "\0")
        . ($filter ?? Ber::Encode(135, 'objectClass')) . Ber::Encode(48, $selection));
}

/**
 * Decodes response entries and the final LDAP result code.
 * @param string $response Concatenated LDAP messages.
 * @return array Decoded entries and result code.
 */
function Decode(string $response): array
{
    $entries = [];
    $code = null;
    while ($response !== '') {
        $message = Ber::Read($response, 48)['value'];
        Check(Ber::Number(Ber::Read($message, 2)['value']) === 1, 'Response message ID');
        $operation = Ber::Read($message);
        if ($operation['tag'] === 100) {
            $dn = Ber::Read($operation['value'], 4)['value'];
            $attributes = Ber::Read($operation['value'], 48)['value'];
            $entry = [];
            while ($attributes !== '') {
                $attribute = Ber::Read($attributes, 48)['value'];
                $name = Ber::Read($attribute, 4)['value'];
                $values = Ber::Read($attribute, 49)['value'];
                $entry[$name] = [];
                while ($values !== '') {
                    $entry[$name][] = Ber::Read($values, 4)['value'];
                }
            }
            $entries[$dn] = $entry;
        } else {
            $code = Ber::Number(Ber::Read($operation['value'], 10)['value']);
        }
    }
    return ['entries' => $entries, 'code' => $code];
}

/**
 * Reads a complete Bind response from a network connection.
 * @param resource $socket Connected stream.
 * @return string Complete response.
 */
function ReadResponse($socket): string
{
    $buffer = '';
    do {
        $bytes = fread($socket, 8192);
        Check($bytes !== false && $bytes !== '', 'Network response timed out or closed');
        $buffer .= $bytes;
        $frame = Ber::TakeFrame($buffer);
    } while ($frame === null);
    return $frame;
}

if (($argv[1] ?? '') === 'serve') {
    require_once $argv[2] . '/vendor/colibri/core/src/Colibri/Threading/Worker.php';
    require_once __DIR__ . '/../../src/Auth/Threading/LdapWorker.php';
    $worker = (new ReflectionClass(LdapWorker::class))->newInstanceWithoutConstructor();
    $tls = ($argv[3] ?? '') !== '';
    $worker->Serve(new FixtureDirectory(), [
        'listen' => ($tls ? 'tls' : 'tcp') . '://127.0.0.1:0',
        'cert' => $argv[3] ?? '', 'key' => $argv[4] ?? '',
    ]);
    exit;
}

$root = realpath($argv[1] ?? __DIR__ . '/../../../../../..');
Check($root !== false && is_file($root . '/vendor/colibri/core/src/Colibri/Threading/Worker.php'), 'Pass the booth root as the first argument');
foreach ([0, 127, 128, 65535, 2147483647] as $number) {
    $encoded = Ber::Integer($number);
    Check(Ber::Number(Ber::Read($encoded, 2)['value']) === $number, 'BER integer roundtrip');
}
$frame = Ber::Encode(48, Ber::Encode(4, str_repeat('x', 300)));
for ($length = 0; $length < strlen($frame); $length++) {
    $fragment = substr($frame, 0, $length);
    Check(Ber::TakeFrame($fragment) === null && strlen($fragment) === $length, 'Fragmentation must preserve incomplete input');
}
foreach (["\x30\x80", "\x30\x85", "\x30\x84\x7f\xff\xff\xff", "\x01\x00"] as $invalid) {
    try {
        Ber::TakeFrame($invalid);
        throw new RuntimeException('Invalid BER accepted');
    } catch (InvalidArgumentException $expected) {
    }
}
$buffer = $frame . $frame;
Check(Ber::TakeFrame($buffer) === $frame && $buffer === $frame, 'Pipelined BER messages');
Check(Directory::EscapeDn(' #a,b ') === '\\20#a\\2cb\\20', 'DN escaping');

$directory = new FixtureDirectory();
$protocol = new Protocol($directory);
Check(Decode($protocol->Handle(SearchRequest()))['code'] === 50, 'Anonymous search must be denied');
$rootResponse = Decode($protocol->Handle(SearchRequest('', 0, null, ['namingContexts', 'supportedLDAPVersion'])));
Check($rootResponse['entries']['']['namingcontexts'] === ['dc=tasks'], 'Anonymous Root DSE');
Check(Decode($protocol->Handle(BindRequest('alice', '')))['code'] === 49, 'Empty password denied');
Check(Decode($protocol->Handle(BindRequest()))['code'] === 0, 'Valid Bind');
Check(count(Decode($protocol->Handle(SearchRequest()))['entries']) === 6, 'Subtree search');
Check(count(Decode($protocol->Handle(SearchRequest('dc=tasks', 1)))['entries']) === 2, 'One-level scope');
$equality = Ber::Encode(163, Ber::Encode(4, 'UID') . Ber::Encode(4, 'ALICE'));
$result = Decode($protocol->Handle(SearchRequest('ou=users,dc=tasks', 1, $equality, ['uid', 'memberOf'])));
Check(count($result['entries']) === 1 && count(current($result['entries'])) === 2, 'Equality and selected operational attributes');
$substring = Ber::Encode(164, Ber::Encode(4, 'mail') . Ber::Encode(48, Ber::Encode(128, 'ali') . Ber::Encode(130, '.test')));
Check(count(Decode($protocol->Handle(SearchRequest('ou=users,dc=tasks', 1, $substring)))['entries']) === 1, 'Substring filter');
$boolean = Ber::Encode(160, Ber::Encode(135, 'mail') . Ber::Encode(162, $equality));
Check(count(Decode($protocol->Handle(SearchRequest('dc=tasks', 2, $boolean)))['entries']) === 1, 'AND and NOT filters');
Check(count(Decode($protocol->Handle(SearchRequest('dc=tasks', 2, Ber::Encode(161, $equality . $substring))))['entries']) === 1, 'OR filter');
Check(Decode($protocol->Handle(SearchRequest('dc=other')))['code'] === 32, 'Foreign naming context denied');
Check(Decode($protocol->Handle(SearchRequest('uid=missing,ou=users,dc=tasks')))['code'] === 32, 'Missing search base');
Check(Decode($protocol->Handle(SearchRequest('dc=tasks', 2, null, [], 1)))['code'] === 4, 'Size limit result');
$types = Decode($protocol->Handle(SearchRequest('dc=tasks', 0, null, ['dc'], 0, true)));
Check($types['entries']['dc=tasks']['dc'] === [], 'Types-only response');
Check(current(Decode($protocol->Handle(SearchRequest('dc=tasks', 0, null, ['1.1'])))['entries']) === [], 'No-attributes selector');
$group = Decode($protocol->Handle(SearchRequest('ou=groups,dc=tasks', 1, null, ['member'])));
Check(count($group['entries']['cn=user,ou=groups,dc=tasks']['member']) === 2, 'Group membership');
$critical = Ber::Encode(160, Ber::Encode(48, Ber::Encode(4, '1.2.3') . Ber::Encode(1, "\xff")));
Check(Decode($protocol->Handle(Request(96, Ber::Integer(3) . Ber::Encode(4, 'alice') . Ber::Encode(128, 'fixture-password'), $critical)))['code'] === 12, 'Unsupported critical control');
Check(Decode($protocol->Handle(SearchRequest()))['code'] === 50, 'Rejected Bind must clear old identity');
$protocol->Handle(BindRequest());
$directory->Revoke();
Check(Decode($protocol->Handle(SearchRequest()))['code'] === 50, 'Revoked account denied without reconnect');
$protocol = new Protocol(new FixtureDirectory());
$protocol->Handle(BindRequest());
Check(Decode($protocol->Handle(BindRequest('alice', 'wrong')))['code'] === 49, 'Wrong password denied');
Check(Decode($protocol->Handle(SearchRequest()))['code'] === 50, 'Failed rebind clears identity');
Check(Decode($protocol->Handle(BindRequest('', '')))['code'] === 0, 'Anonymous Bind');
Check(Decode($protocol->Handle(Request(119, Ber::Encode(128, '1.3.6.1.4.1.1466.20037'))))['code'] === 53, 'StartTLS is explicitly unsupported');
Check(Decode($protocol->Handle(Request(104, '')))['code'] === 53, 'Writes denied');
Check($protocol->Handle(Request(66, '')) === null, 'Unbind closes connection');
echo "BER and LDAP protocol regression checks: OK\n";

$temporaryFiles = [];
try {
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    $csr = openssl_csr_new(['commonName' => 'localhost'], $key, ['digest_alg' => 'sha256']);
    $certificate = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
    Check($certificate !== false && openssl_x509_export($certificate, $certificateText) && openssl_pkey_export($key, $keyText), 'Generate temporary TLS fixture');
    $certificateFile = tempnam(sys_get_temp_dir(), 'auth-ldap-cert-');
    $keyFile = tempnam(sys_get_temp_dir(), 'auth-ldap-key-');
    $temporaryFiles = [$certificateFile, $keyFile];
    file_put_contents($certificateFile, $certificateText);
    file_put_contents($keyFile, $keyText);
    chmod($keyFile, 0600);
    foreach ([false, true] as $tls) {
        $command = [PHP_BINARY, '-d', 'xdebug.mode=off', __FILE__, 'serve', $root, $tls ? $certificateFile : '', $tls ? $keyFile : ''];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        Check(is_resource($process), 'Start fixture worker');
        try {
            stream_set_timeout($pipes[1], 10);
            $line = fgets($pipes[1]);
            Check(is_string($line) && preg_match('~(?:tcp|tls)://127\.0\.0\.1:(\d+)~', $line, $match) === 1, 'Worker startup: ' . (string)$line);
            $context = stream_context_create(['ssl' => ['cafile' => $certificateFile, 'peer_name' => 'localhost', 'verify_peer' => true, 'verify_peer_name' => true]]);
            $socket = stream_socket_client(($tls ? 'tls' : 'tcp') . '://127.0.0.1:' . $match[1], $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);
            Check(is_resource($socket), 'Connect to worker');
            stream_set_timeout($socket, 5);
            $request = BindRequest();
            fwrite($socket, substr($request, 0, 3));
            fwrite($socket, substr($request, 3));
            Check(Decode(ReadResponse($socket))['code'] === 0, 'Network fragmented Bind');
            fwrite($socket, BindRequest() . BindRequest());
            $responses = '';
            $count = 0;
            while ($count < 2) {
                $bytes = fread($socket, 8192);
                Check($bytes !== false && $bytes !== '', 'Read pipelined responses');
                $responses .= $bytes;
                while (($frame = Ber::TakeFrame($responses)) !== null) {
                    Check(Decode($frame)['code'] === 0, 'Network pipelined Bind');
                    $count++;
                }
            }
            fwrite($socket, SearchRequest('dc=tasks', 2, null, ['uid', 'member']));
            $responses = '';
            $entries = 0;
            $done = false;
            while (!$done) {
                $bytes = fread($socket, 8192);
                Check($bytes !== false && $bytes !== '', 'Read network Search');
                $responses .= $bytes;
                while (($frame = Ber::TakeFrame($responses)) !== null) {
                    $decoded = Decode($frame);
                    $entries += count($decoded['entries']);
                    if ($decoded['code'] !== null) {
                        Check($decoded['code'] === 0, 'Network Search success');
                        $done = true;
                    }
                }
            }
            Check($entries === 6, 'Network Search entries');
            for ($attempt = 0; $attempt < 28; $attempt++) {
                fwrite($socket, BindRequest());
                $bytes = fread($socket, 8192);
                if ($attempt < 27) {
                    Check($bytes !== false && $bytes !== '' && Decode($bytes)['code'] === 0, 'Bind below rate limit');
                } else {
                    Check($bytes === '' && feof($socket), 'Bind rate limit closes connection');
                }
            }
            fclose($socket);
            $socket = stream_socket_client(($tls ? 'tls' : 'tcp') . '://127.0.0.1:' . $match[1], $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);
            Check(is_resource($socket), 'Reconnect to worker');
            stream_set_timeout($socket, 5);
            fwrite($socket, BindRequest());
            Check(fread($socket, 8192) === '' && feof($socket), 'Bind rate limit survives reconnect');
            fclose($socket);
            $socket = stream_socket_client(($tls ? 'tls' : 'tcp') . '://127.0.0.1:' . $match[1], $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);
            stream_set_timeout($socket, 5);
            fwrite($socket, Request(66, ''));
            Check(fread($socket, 1) === '' && feof($socket), 'Network Unbind closes connection');
            fclose($socket);
            echo ($tls ? 'Verified TLS' : 'TCP') . " worker integration: OK\n";
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }
} finally {
    foreach ($temporaryFiles as $path) {
        unlink($path);
    }
}