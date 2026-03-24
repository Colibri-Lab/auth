<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Models\Applications;
use App\Modules\Auth\Models\AutoLogins;
use App\Modules\Auth\Models\Members;
use App\Modules\Auth\Models\Sessions;
use App\Modules\Auth\Module;
use Colibri\App;
use Colibri\AppException;
use Colibri\Data\Storages\Storages;
use Colibri\Exceptions\ValidationException;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;

/**
 * Application controller
 */
class BackendController extends WebController
{

    /**
     * Request autologin for member
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function RequestAutologin(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $result = [];
        $message = 'Result message';
        $code = 200;

        try {
            
            $app = Applications::LoadByToken($post->{'app'});
            if(!$app->params->autologin) {
                return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-autologin}', 'code' => 400]);
            }


            $payloadArray = $payload->ToArray();
            $token = $payloadArray['token'] ?? $post->{'token'};
            $returnTo = $payloadArray['return'] ?? $post->{'return'};
            if(!$token) {
                return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-provide-token}', 'code' => 400]);
            }

            $member = Members::LoadByToken($token);
            if(!$member) {
                return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-found}', 'code' => 400]);
            }

            $autologin = AutoLogins::CreateForMember($app, $member, $returnTo);
            $result['link'] = $autologin->GenerateLink($app);
            

        } catch (\InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => $e->getExceptionDataAsArray()]);
        } catch (\Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }
            
        return $this->Finish(
            $code,
            $message,
            $result,
            'utf-8'
        );

    }


    
}