<?php

namespace App\Modules\Auth\Controllers;

use Colibri\App;
use Colibri\Exceptions\PermissionDeniedException;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;
use App\Modules\Auth\Tests\Security\Regression;

/**
 * Runs opt-in security tests through the application bootstrap.
 * @class
 * @memberof App\Modules\Auth\Controllers
 * @extends WebController
 */
class SecurityTestsController extends WebController
{

    /**
     * Executes the security suite and returns a process status for deployment gates.
     * @public
     * @param RequestCollection $get CLI arguments.
     * @param RequestCollection $post POST arguments.
     * @param PayloadCopy|null $payload Request payload.
     * @return object Framework response type required by controller dispatch.
     */
    public function Run(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        if (PHP_SAPI !== 'cli' || App::$request->server->{'request_method'} || App::$domainKey !== 'auth') {
            throw new PermissionDeniedException('Security tests require an Auth CLI invocation', 403);
        }
        if (getenv('AUTH_SECURITY_TESTS') !== '1') {
            fwrite(STDERR, "Set AUTH_SECURITY_TESTS=1 to allow isolated security fixtures on this deployment.\n");
            exit(2);
        }

        require_once dirname(__DIR__, 3) . '/tests/security/Regression.php';
        try {
            $suite = new Regression('https://' . App::$request->host);
            $report = $suite->Run();
            fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
            exit($report['failed'] === 0 ? 0 : 1);
        } catch (\Throwable $exception) {
            fwrite(STDERR, 'Security suite infrastructure/cleanup error: ' . get_class($exception) . "\n");
            exit(2);
        }
    }
}