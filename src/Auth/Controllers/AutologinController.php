<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Models\Applications;
use App\Modules\Auth\Models\AutoLogins;
use App\Modules\Auth\Models\Members;
use App\Modules\Auth\Models\Sessions;
use Colibri\App;
use App\Modules\Auth\Module;
use Colibri\AppException;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;
use InvalidArgumentException;

/**
 * Make autologin
 * @author self
 * @package App\Modules\Auth\Controllers
 */
class AutologinController extends WebController
{
    /**
     * Autologin link
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Perform(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {

        $result = [];
        $message = 'Result message';
        $code = 200;

        $appName = App::$request->headers->{'X-AppName'};
        $app = Applications::LoadByKey($appName);
        // if(!$app->params->allowed_ip) {
        //     throw new AppException('Not allowed', 403);
        // }

        // if($app->params->allowed_ip !== App::$request->remoteip) {
        //     throw new AppException('Not allowed', 403);
        // }

        $code = $get->{'code'};
        if(!$code) {
            throw new AppException('Code not found', 403);
        }

        $autologin = AutoLogins::LoadByCode($code);
        if(!$autologin) {
            throw new AppException('Autologin not found', 403);
        }

        $memberToken = $autologin->token;
        if(!$memberToken) {
            throw new AppException('Member not found', 403);
        }

        $member = Members::LoadByToken($memberToken);
        if(!$memberToken) {
            throw new AppException('Member not found', 403);
        }

        $session = Sessions::LoadFromRequest();
        $session->member = $member->token;
        $session->Save(true);

        $autologin->Delete();

        // финишируем контроллер
        return $this->Finish(
            200,
            '<script>location = \''.$autologin->return_to.'\'</script>',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );


    }

}
