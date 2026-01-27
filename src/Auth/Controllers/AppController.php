<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Models\AutoLogins;
use App\Modules\Auth\Models\Members;
use App\Modules\Auth\Models\Sessions;
use App\Modules\Auth\Module;
use Colibri\App;
use Colibri\AppException;
use Colibri\Data\Storages\Storages;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;

/**
 * Application controller
 */
class AppController extends WebController
{

    /**
     * Returns an application settings
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function Settings(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $app = Module::Instance()->application;
        $settings = $app->ExportForUserInterface();

        $membersStorage = Storages::Instance()->Load('members');
        $memberForm = $membersStorage->ToArray();

        unset($memberForm['fields']['token']);
        unset($memberForm['fields']['role']);
        unset($memberForm['fields']['email_confirmed']);
        unset($memberForm['fields']['phone_confirmed']);
        $memberForm['fields']['email']['params']['readonly'] = false;
        $memberForm['fields']['phone']['params']['readonly'] = false;
        $memberForm['fields']['password']['params']['readonly'] = false;
        $memberForm['fields']['email']['note'] = '';
        $memberForm['fields']['phone']['note'] = '';
        $memberForm['fields']['password']['note'] = '';

        $settings['forms']['register'] = $memberForm;

        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            $settings,
            'utf-8'
        );
    }

    /**
     * Gets an active session for user token
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Session(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $code = 200;
        $result = [];
        $message = 'Result message';
        
        $app = Module::Instance()->application;
        if(!$app->params->allowed_ip) {
            throw new AppException('Not allowed', 403);
        }

        if($app->params->allowed_ip !== App::$request->remoteip) {
            throw new AppException('Not allowed', 403);
        }

        $payloadArray = $payload->ToArray();
        $token = $payloadArray['token'] ?? $post->{'token'};
        if(!$token) {
            throw new AppException('Bad request', 400);
        }

        $member = Members::LoadByToken($token);
        if(!$member) {
            throw new AppException('Member not found', 404);
        }

        $sessions = Sessions::LoadByMember($member);
        if(!$sessions || $sessions->Count() == 0) {
            throw new AppException('Session not found', 404);
        }

        /** @var \App\Modules\Auth\Models\Session */
        $session = $sessions->First();
        $result = $session->ExportForUserInterface();

        return $this->Finish(
            $code,
            $message,
            $result,
            'utf-8'
        );

    }




    
}