<?php

use App\Modules\Auth\Threading\LdapWorker;
use Colibri\App;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['root:', 'host:', 'application:', 'listen:', 'cert:', 'key:', 'allow-insecure', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: AUTH_LDAP_APP_TOKEN=<token> php ldap.php --root=/path/to/booth --host=tasks_auth-v5.example --application=tasks [--listen=tcp://127.0.0.1:1389] [--cert=/path/fullchain.pem --key=/path/key.pem] [--allow-insecure]\n");
    exit(0);
}

try {
    $root = realpath((string)($options['root'] ?? ''));
    $host = (string)($options['host'] ?? '');
    $application = (string)($options['application'] ?? '');
    $token = getenv('AUTH_LDAP_APP_TOKEN');
    if ($root === false || !is_file($root . '/vendor/autoload.php') || !is_dir($root . '/web')
        || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/D', $host) || $application === '' || $token === false || $token === '') {
        throw new InvalidArgumentException('Required: --root, --host, --application and AUTH_LDAP_APP_TOKEN environment variable');
    }
    chdir($root . '/web');
    require_once $root . '/vendor/autoload.php';
    $_SERVER['argv'] = ['index.php', $host, '/'];
    $_SERVER['argc'] = 3;
    unset($_SERVER['REQUEST_METHOD']);
    set_time_limit(0);
    App::Instance()->Initialize();
    if (App::$domainKey !== 'auth') {
        throw new InvalidArgumentException('--host must resolve to the Auth domain');
    }
    $params = [
        'application' => $application,
        'token' => $token,
        'listen' => (string)($options['listen'] ?? 'tcp://127.0.0.1:1389'),
        'cert' => (string)($options['cert'] ?? ''),
        'key' => (string)($options['key'] ?? ''),
        'allow-insecure' => isset($options['allow-insecure']),
    ];
    putenv('AUTH_LDAP_APP_TOKEN');
    unset($token);
    $worker = new LdapWorker(0, 0, 'auth-ldap-' . $application);
    $worker->Prepare($worker->PrepareParams((object)$params));
    unset($params);
    $worker->Run();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Auth LDAP startup failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}