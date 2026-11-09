<?php

namespace App\Modules\Auth\Controllers;

use Colibri\App;
use App\Modules\Auth\Threading\LdapWorker;
use Colibri\Exceptions\BadRequestException;
use Colibri\Exceptions\PermissionDeniedException;
use Colibri\Threading\Process;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;

/**
 * CLI-only entry point for starting the Auth LDAP worker.
 * @class
 * @memberof App\Modules\Auth\Controllers
 * @extends WebController
 */
class LdapController extends WebController
{

    

    /**
     * Starts the LDAP worker from a CLI controller invocation.
     * @public
     * @param RequestCollection $get RequestCollection containing GET data
     * @param RequestCollection $post RequestCollection containing POST data
     * @param ?PayloadCopy $payload Request payload data as PayloadCopy object
     * @return object
     */
    public function Start(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        if (PHP_SAPI !== 'cli' || App::$request->server->{'request_method'} || App::$domainKey !== 'auth') {
            throw new PermissionDeniedException('LDAP startup requires an Auth CLI invocation', 403);
        }
        $application = trim((string)$get->{'application'});
        $token = getenv('AUTH_LDAP_APP_TOKEN');
        if ($application === '' || $token === false || $token === '') {
            throw new BadRequestException('Required: application and AUTH_LDAP_APP_TOKEN environment variable', 400);
        }
        unset($token);
        $worker = new LdapWorker(0, 0, 'auth-ldap-' . hash('sha256', $application));
        $process = new Process($worker);
        $process->Run((object)[
            'application' => $application,
            'listen' => (string)($get->{'listen'} ?? 'tcp://127.0.0.1:1389'),
            'cert' => (string)($get->{'cert'} ?? ''),
            'key' => (string)($get->{'key'} ?? ''),
            'allow-insecure' => $get->{'allow-insecure'} === 'true'
        ]);
        return $this->Finish(200, 'started', ['pid' => $process->pid]);
    }


    
}