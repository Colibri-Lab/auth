<?php



namespace App\Modules\Auth\Controllers;


use Colibri\App;
use Colibri\Events\EventsContainer;
use Colibri\IO\FileSystem\File;
use Colibri\Utils\Cache\Bundle;
use Colibri\Utils\Debug;
use Colibri\Utils\ExtendedObject;
use Colibri\Web\RequestCollection;
use Colibri\Web\Controller as WebController;
use Colibri\Web\Templates\PhpTemplate;
use Colibri\Web\View;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use Colibri\Web\PayloadCopy;
use App\Modules\Auth\Models\Sessions;
use App\Modules\Auth\Models\Members;


class SessionController extends WebController
{

    /**
     * Создание сессии
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Start(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        
        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [], 
            [ $session->GenerateCookie(true) ]
        );
    }

    /**
     * Создание сессии
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Login(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        if($session->member) {
            return $this->Finish(403, 'Forbidden', ['message' => '#{auth-errors-session-allreadylogged;Пользователь уже залогинен}', 'code' => 403]);
        }

        
        $payloadArray = $payload->ToArray();
        $login = $payloadArray['login'] ?? $post->login;
        $password = $payloadArray['password'] ?? $post->password;

        if(!$login || !$password) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-session-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        $member = Members::LoadByEmail($login);
        if(!$member) {
            $member = Members::LoadByPhone($login);
        }

        if(!$member || $member->blocked) {
            return $this->Finish(403, 'Forbidden', ['message' => '#{auth-errors-member-account-not-exists;Учетная запись не найдена}', 'code' => 403]);
        }


        if(!$member->Authorize($password)) {
            return $this->Finish(403, 'Forbidden', ['message' => '#{auth-errors-member-invalid-creds;Некорректные учетные данные}', 'code' => 403]);
        }

        $session->member = $member->token;
        $session->Save();

        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [], 
            [ $session->GenerateCookie(true) ]
        );
    }

    /**
     * Создание сессии
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Logout(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        
        $session = Sessions::LoadFromRequest();
        $session->member = null;
        $session->Save();

        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [], 
            [ $session->GenerateCookie(true) ]
        );
    }

    /**
     * Создание сессии
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function LogoutFromAll(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(403, 'Forbidden', ['message' => '#{auth-errors-session-notlogged;Пользователь не залогинен}', 'code' => 403]);
        }

        $sessions = Sessions::LoadByMember($session->member);
        foreach($sessions as $s) {
            $s->member = null;
            $s->Save();
        }

        $session->member = null;

        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [], 
            [ $session->GenerateCookie(true) ]
        );
    }

    /**
     * Создание сессии
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Decode(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();

        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [], 
            [ $session->GenerateCookie(true) ]
        );
    }


}