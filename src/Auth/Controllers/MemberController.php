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
use Colibri\Common\VariableHelper;
use Colibri\Common\StringHelper;
use App\Modules\Auth\Models\Member;


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
        $email = $payloadArray['email'] ?? $post->email;
        $phone = $payloadArray['phone'] ?? $post->phone;
        $firstName = $payloadArray['first_name'] ?? $post->first_name;
        $lastName = $payloadArray['last_name'] ?? $post->last_name;
        $patronymic = $payloadArray['patronymic'] ?? $post->patronymic;
        $gender = $payloadArray['gender'] ?? $post->gender;
        $birthdate = $payloadArray['birthdate'] ?? $post->birthdate;
        $password = $payloadArray['password'] ?? $post->password;
        $confirmation = $payloadArray['confirmation'] ?? $post->confirmation;
        $role = $payloadArray['role'] ?? $post->role;

        if(!$email || !$phone || !$password || !$confirmation || !$firstName || !$lastName) {
            $validation = [];
            if(!$email) {
                $validation['email'] = 'Field «email» is required';
            }
            if(!$phone) {
                $validation['phone'] = 'Field «phone» is required';
            }
            if(!$password) {
                $validation['password'] = 'Field «password» is required';
            }
            if(!$confirmation) {
                $validation['confirmation'] = 'Field «confirmation» is required';
            }
            if(!$firstName) {
                $validation['firstName'] = 'Field «firstName» is required';
            }
            if(!$lastName) {
                $validation['lastName'] = 'Field «lastName» is required';
            }
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Invalid data in request', 
                'code' => 400,
                'validation' => $validation
            ]);
        }

        if(!StringHelper::IsEmail($email)) {
            $validation['email'] = 'Field «email» contains invalid email address';
        }

        if(Members::LoadByEmail($email) !== null) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-with-email-exists;Пользователь с таким email-ом существует}', 
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-with-email-exists;Пользователь с таким email-ом существует}'
                ]
            ]);
        }
        if(Members::LoadByPhone($phone) !== null) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-with-phone-exists;Пользователь с таким телефоном существует}', 
                'code' => 400,
                'validation' => [
                    'phone' => '#{auth-errors-member-with-phone-exists;Пользователь с таким телефоном существует}'
                ]
            ]);
        }

        if($password != $confirmation) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Password is not confirmed', 'code' => 400, 'validation' => [
                'confirmation' => 'Password is not confirmed'
            ]]);
        }

        if(!$role) {
            $role = Module::$instance->application->params->defaultrole;
        }

        if(!Module::$instance->application->CheckRole($role)) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Role does not exists in application params', 'code' => 400]);
        }

        if( ($strength = Member::CheckPasswordStrength($email, $password)) < 40 ) {
            return $this->Finish(400, 'Bad request', [
                'message' => 'Password strength must be at least 40%, you got ' . $strength . '%', 
                'code' => 400,
                'validation' => [
                    'password' => 'Password strength must be at least 40%, you got ' . $strength . '%'
                ]
            ]);
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
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        }
        catch(Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
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
            return $this->Finish(400, 'Bad Request', ['message' => 'Member is not logged on', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => 'Error in data consistency', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->property;
        if(!$property) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Invalid data in request', 'code' => 400]);
        }

        $confirmed = $member->{$property.'_confirmed'};
        if($confirmed) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Allready confirmed', 'code' => 400]);
        }
        
        if(!$member->SendConfirmationMessage($property)) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Can not send confirmation message', 'code' => 400]);
        }

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
     * Начинает процесс подтверждения
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function BeginPasswordResetProcess(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if($session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Member is logged on', 'code' => 400]);
        }

        $payloadArray = $payload->ToArray();
        $email = $payloadArray['email'] ?? $post->email;
        $phone = $payloadArray['phone'] ?? $post->phone;
        
        if(!$email || !$phone) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Invalid data in request', 
                'code' => 400,
                'validation' => [
                    'email' => 'This field is required',
                    'password' => 'This field is required',
                ]
            ]);
        }

        $member = Members::LoadByEmail($email);
        if(!$member) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Member not found', 
                'code' => 400,
                'validation' => [
                    'email' => 'Member with this email is not found'
                ]
            ]);
        }

        if($member->phone != $phone) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Incorrect phone number', 
                'code' => 400,
                'validation' => [
                    'phone' => 'Member with this phone is not found'
                ]
            ]);
        }

        if(!$member->SendResetMessage()) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Can not send reset message', 
                'code' => 400,
                'validation' => [
                    'email' => 'Can not send reset message'
                ]
            ]);
        }

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
            return $this->Finish(400, 'Bad Request', ['message' => 'Member is not logged on', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => 'Error in data consistency', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->property;
        $code = $payloadArray['code'] ?? $post->code;
        
        if(!$property || !$code) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Invalid data in request', 'code' => 400]);
        }

        if(!$member->ConfirmProperty($property, $code)) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Error confirming property', 'code' => 400]);
        }

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
     * Подтверждает свойство
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function ResetPassword(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if($session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Member is logged on', 'code' => 400]);
        }

        $payloadArray = $payload->ToArray();
        $email = $payloadArray['email'] ?? $post->email;
        $phone = $payloadArray['phone'] ?? $post->phone;
        $code = $payloadArray['code'] ?? $post->code;
        $password = $payloadArray['password'] ?? $post->password;
        $confirmation = $payloadArray['confirmation'] ?? $post->confirmation;
        
        if(!$email || !$phone || !$code || !$password) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Bad request', 'code' => 400]);
        }

        $member = Members::LoadByEmail($email);
        if(!$member) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Member not found', 
                'code' => 400,
                'validation' => [
                    'email' => 'Can not find the member with that email'
                ]
            ]);
        }
        
        if($member->phone != $phone) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Incorrect phone number', 
                'code' => 400,
                'validation' => [
                    'phone' => 'Can not find the member with that phone'
                ]
            ]);
        }

        if($password != $confirmation) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Password is not confirmed', 
                'code' => 400,
                'validation' => [
                    'password' => 'Password is not confirmed properly'
                ]
            ]);
        }

        if(!$member->ResetPassword($code, $password)) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Can not save this password', 'code' => 400]);
        }

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
     * Изменяет пароль
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function ChangePassword(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Member is not logged on', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);

        $payloadArray = $payload->ToArray();
        $original = $payloadArray['original'] ?? $post->original;
        $password = $payloadArray['password'] ?? $post->password;
        $confirmation = $payloadArray['confirmation'] ?? $post->confirmation;
        
        if(!$original || !$password || !$confirmation) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Bad request', 'code' => 400]);
        }
        
        if(!$member->Authorize($original)) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Incorrect current password', 
                'code' => 400,
                'validation' => [
                    'original' => 'Incorrect current password'
                ]
            ]);
        }

        if($password != $confirmation) {
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Password is not confirmed', 
                'code' => 400,
                'validation' => [
                    'password' => 'Password is not confirmed properly'
                ]
            ]);
        }

        $member->password = $password;
        $member->Save();

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
     * Блокирует доступ пользователя
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function BlockAccount(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Member is not logged on', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        $member->blocked = 1;
        $member->Save();

        $session->member = null;
        $session->Save();

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
     * Обновляет профиль
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function UpdateProfile(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Member is not logged on', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => 'Error in data consistency', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $firstName = $payloadArray['first_name'] ?? $post->first_name;
        $lastName = $payloadArray['last_name'] ?? $post->last_name;
        $patronymic = $payloadArray['patronymic'] ?? $post->patronymic;
        $gender = $payloadArray['gender'] ?? $post->gender;
        $birthdate = $payloadArray['birthdate'] ?? $post->birthdate;
        
        if(!$firstName || !$lastName) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Invalid data in request', 'code' => 400]);
        }

        if(!$member->UpdateProfile($firstName, $lastName, $patronymic, $gender, $birthdate)) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Can not update profile', 'code' => 400]);
        }

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
     * Обновляет идентификационные данные
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function UpdateIdentity(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Member is not logged on', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => 'Error in data consistency', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $email = $payloadArray['email'] ?? $post->email;
        $phone = $payloadArray['phone'] ?? $post->phone;
        
        if(!$email || !$phone) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Invalid data in request', 'code' => 400]);
        }

        if($member->email != $email && Members::LoadByEmail($email)) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Email allready exists', 'code' => 400]);
        }
        if($member->email != $email && !$member->email_confirmed) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Please confirm current email before changing', 'code' => 400]);
        }
        if($member->phone != $email && Members::LoadByPhone($phone)) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Phone allready exists', 'code' => 400]);
        }
        if($member->phone != $phone && !$member->phone_confirmed) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Please confirm current phone before changing', 'code' => 400]);
        }

        if(!$member->UpdateIdentity($email, $phone)) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Can not update identity', 'code' => 400]);
        }

        $session->member = $member->token;
        $session->Save();

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
     * Обновляет пароль
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function UpdatePassword(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Member is not logged on', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => 'Error in data consistency', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $currentPassword = $payloadArray['current'] ?? $post->current;
        $newPassword = $payloadArray['new'] ?? $post->new;
        
        if(!$currentPassword || !$newPassword) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Invalid data in request', 'code' => 400]);
        }

        if(!$member->UpdatePassword($currentPassword, $newPassword)) {
            return $this->Finish(400, 'Bad Request', ['message' => 'Can not update password', 'code' => 400]);
        }

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