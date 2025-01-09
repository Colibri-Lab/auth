<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Models\Invitation;
use App\Modules\Auth\Models\Invitations;
use App\Modules\Auth\Models\Members;
use App\Modules\Auth\Models\Sessions;
use Colibri\App;
use App\Modules\Auth\Module;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;
use InvalidArgumentException;

/**
 * Send check or accept invites
 * @author self
 * @package App\Modules\Auth\Controllers
 */
class InvitesController extends WebController
{
    /**
     * Creates an invite
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Create(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {

        $result = [];
        $message = 'Result message';
        $code = 200;

        $session = Sessions::LoadFromRequest();
        if(!$session || !$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        /** @var \App\Modules\Auth\Models\Application|null $app */
        $app = Module::$instance->application;
        if (!$app) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Application not found', 'code' => 400]);
        }

        $email = $post->email;
        $fio = $post->fio;
        $params = $post->params ?? [];

        if(!$email) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Email or phone are required',
                'code' => 400
            ]);
        }

        $member = Members::LoadByEmail($email);
        if($member) {
            return $this->Finish(
                200,
                'ok',
                ['member' => $member->ExportForUserInterface(true), 'params' => $params],
                'utf-8'
            );
        }
        
        $invitation = Invitations::CreateInvitation($email, $fio, $params);
        if(!$invitation) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-invitation-send-error}', 'code' => 400]);
        }

        $res = $invitation->Send($app->params->proxies);
        if (!$res) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-invitation-send-error}', 'code' => 400]);
        }

        $result['invitation'] = $invitation->ExportForUserInterface();
        $result['params'] = $params;

        return $this->Finish(
            $code,
            $message,
            $result,
            'utf-8'
        );

    }


    /**
     * Gets the invitation by code
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Get(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $result = [];
        $message = 'Result message';
        $code = 200;

        $code2 = $post->{'code'};
        if(!$code2) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Code is required', 'code' => 400]);
        }

        $invitation = Invitations::LoadByCode($code2);
        if(!$invitation) {
            return $this->Finish(404, 'Not Found', ['message' => 'Invitation not found', 'code' => 404]);
        }    

        return $this->Finish(
            $code,
            $message,
            $invitation->ExportForUserInterface(),
            'utf-8'
        );

    }


    
}
