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
use Colibri\Data\Storages\Fields\DateTimeField;
use Psr\Log\InvalidArgumentException;
use Throwable;
use App\Modules\Auth\Module;


class MemberController extends WebController
{

    /**
     * Создание сессии
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Register(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        
        $payloadArray = $payload->ToArray();
        $email = $payloadArray['email'] ?? $post->{'email'};
        $phone = $payloadArray['phone'] ?? $post->{'phone'};
        $firstName = $payloadArray['firstName'] ?? $post->{'firstName'};
        $lastName = $payloadArray['lastName'] ?? $post->{'lastName'};
        $patronymic = $payloadArray['patronymic'] ?? $post->{'patronymic'};
        $gender = $payloadArray['gender'] ?? $post->{'gender'};
        $birthdate = $payloadArray['birthdate'] ?? $post->{'birthdate'};
        $password = $payloadArray['password'] ?? $post->{'password'};
        $confirmation = $payloadArray['confirmation'] ?? $post->{'confirmation'};
        $role = $payloadArray['role'] ?? $post->{'role'};

        if(!$email || !$phone || !$password || !$confirmation || !$firstName || !$lastName) {
            return $this->Finish(400, 'Bad Request');
        }

        if(Members::LoadByEmail($email) !== null || Members::LoadByPhone($phone) !== null) {
            return $this->Finish(400, 'Member with this email and/or phone allready exists');
        }

        if($password != $confirmation) {
            return $this->Finish(400, 'Password is not confirmed');
        }

        if(!$role) {
            $role = Module::$instance->application->params->defaultrole;
        }

        if(!Module::$instance->application->CheckRole($role)) {
            return $this->Finish(400, 'Role does not exists in application params');
        }

        try {
            $member = Members::Register($email, $phone, $password);
            $member->first_name = $firstName;
            $member->last_name = $lastName;
            $member->patronymic = $patronymic;
            $member->birthdate = new DateTimeField($birthdate);
            $member->gender = $gender;
            $member->role = $role;
            $member->Save();
        }
        catch(InvalidArgumentException $e) {
            return $this->Finish(400, $e->getMessage());
        }
        catch(Throwable $e) {
            return $this->Finish(500, $e->getMessage());
        }

        $session->member = $member->token;
        $session->Save();

        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            $session->ExportForUserInterface(),
            'utf-8',
            [], 
            [ $session->GenerateCookie(true) ]
        );
        
    }

    /**
     * Начинает процесс подтверждения
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function BeginConfirmationProcess(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(400, 'Member is not logged on');
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Error in data consistency');
        }
        
        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->{'property'};
        if(!$property) {
            return $this->Finish(400, 'Bad request');
        }

        $confirmed = $member->{$property.'_confirmed'};
        if($confirmed) {
            return $this->Finish(400, 'Allready confirmed');
        }
        
        if(!$member->SendConfirmationMessage($property)) {
            return $this->Finish(400, 'Can not send confirmation message');
        }

        return $this->Finish(
            200,
            'ok',
            $session->ExportForUserInterface(),
            'utf-8',
            [], 
            [ $session->GenerateCookie(true) ]
        );
       
    }

    /**
     * Подтверждает свойство
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function ConfirmProperty(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(400, 'Member is not logged on');
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Error in data consistency');
        }
        
        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->{'property'};
        $code = $payloadArray['code'] ?? $post->{'code'};
        
        if(!$property || !$code) {
            return $this->Finish(400, 'Bad request');
        }

        $member->ConfirmProperty($property, $code);

        return $this->Finish(
            200,
            'ok',
            $session->ExportForUserInterface(),
            'utf-8',
            [], 
            [ $session->GenerateCookie(true) ]
        );
       
    }

}