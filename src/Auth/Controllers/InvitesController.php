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

        /** @var \App\Modules\Auth\Models\Application|null $app */
        $app = Module::$instance->application;
        if (!$app) {
            throw new InvalidArgumentException('Application not found', 404);
        }

        $email = $post->email;
        $phone = $post->phone;
        $params = $post->params ?? [];

        if(!$email && !$phone) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Email or phone are required',
                'code' => 400
            ]);
        }

        $member = $email ? Members::LoadByEmail($email) : Members::LoadByPhone($phone);
        if($member) {
            // финишируем контроллер
            return $this->Finish(
                200,
                'ok',
                ['member' => $member->ExportForUserInterface(true)],
                'utf-8'
            );
        }
        
        $invitation = Invitations::CreateInvitation($email, $phone, $params);
        if(!$invitation) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-invitation-send-error}', 'code' => 400]);
        }

        $res = $invitation->Send($app->params->proxies);
        if (!$res) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-invitation-send-error}', 'code' => 400]);
        }

        return $this->Finish(
            $code,
            $message,
            $result,
            'utf-8'
        );

    }


}
