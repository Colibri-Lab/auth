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
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-password-not-confirmed;Пароль не подтвержден}', 
                'code' => 400, 
                'validation' => [
                    'confirmation' => '#{auth-errors-member-password-not-confirmed;Пароль не подтвержден}'
                ]
            ]);
        }

        if(!$role) {
            $role = Module::$instance->application->params->defaultrole;
        }

        if(!Module::$instance->application->CheckRole($role)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-role-not-exists;Не найдена роль в приложении}', 'code' => 400]);
        }

        if( ($strength = Member::CheckPasswordStrength($email, $password)) < 40 ) {
            return $this->Finish(400, 'Bad request', [
                'message' => '#{auth-errors-member-password-strength-not-match;Пароль должен быть не менее 40% сложности, у вас }' . $strength . '%', 
                'code' => 400,
                'validation' => [
                    'password' => '#{auth-errors-member-password-strength-not-match;Пароль должен быть не менее 40% сложности, у вас }' . $strength . '%'
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
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged;Пользователь не залогинен}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => '#{auth-errors-member-data-consistency;Ошибка консистентности данных}', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->property;
        if(!$property) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        $confirmed = $member->{$property.'_confirmed'};
        if($confirmed) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-property-confirmed;Уже подтверждено}', 'code' => 400]);
        }
        
        if(!$member->SendConfirmationMessage($property)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-property-send-error;Ошибка отправки сообщения}', 'code' => 400]);
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
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-logged;Пользователь залогинен}', 'code' => 400]);
        }

        $payloadArray = $payload->ToArray();
        $email = $payloadArray['email'] ?? $post->email;
        $phone = $payloadArray['phone'] ?? $post->phone;
        
        if(!$email || !$phone) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-field-required;Это поле обязательно для заполнения}',
                    'password' => '#{auth-errors-member-field-required;Это поле обязательно для заполнения}',
                ]
            ]);
        }

        $member = Members::LoadByEmail($email);
        if(!$member) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-not-found;Пользователь не найден}', 
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-with-email-not-exists;Пользователь с таким email-ом не существует}'
                ]
            ]);
        }

        if($member->phone != $phone) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-phone-incorrect;Неверный номер телефона}', 
                'code' => 400,
                'validation' => [
                    'phone' => '#{auth-errors-member-with-phone-not-exists;Пользователь с таким телефоном не существует}='
                ]
            ]);
        }

        if(!$member->SendResetMessage()) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-property-send-error;Ошибка отправки сообщения}', 
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-property-send-error;Ошибка отправки сообщения}'
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
     * Обновляет идентификационные данные
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function BeginIdentityUpdateProcess(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged;Пользователь не залогинен}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => '#{auth-errors-member-data-consistency;Ошибка консистентности данных}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->property;
        $value = $payloadArray['value'] ?? $post->value;
        
        if(!$property || !$value) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        if( ($property === 'email' && $member->email === $value) || ($property === 'phone' && $member->phone === $value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        if($property === 'email' && Members::LoadByEmail($value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-with-email-exists;Пользователь с таким email-ом существует}', 'code' => 400]);
        }
        else if($property === 'phone' && Members::LoadByPhone($value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-with-phone-exists;Пользователь с таким телефоном существует}', 'code' => 400]);
        }

        if(!$member->SendConfirmationMessage($property, $value)) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-property-send-error;Ошибка отправки сообщения}', 
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-property-send-error;Ошибка отправки сообщения}'
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
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged;Пользователь не залогинен}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => '#{auth-errors-member-data-consistency;Ошибка консистентности данных}', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->property;
        $code = $payloadArray['code'] ?? $post->code;
        
        if(!$property || !$code) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        if(!$member->ConfirmProperty($property, $code)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-confirmation-error;Невозможно подтвердить свойство}', 'code' => 400]);
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
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-logged;Пользователь залогинен}', 'code' => 400]);
        }

        $payloadArray = $payload->ToArray();
        $email = $payloadArray['email'] ?? $post->email;
        $phone = $payloadArray['phone'] ?? $post->phone;
        $code = $payloadArray['code'] ?? $post->code;
        $password = $payloadArray['password'] ?? $post->password;
        $confirmation = $payloadArray['confirmation'] ?? $post->confirmation;
        
        if(!$email || !$phone || !$code || !$password) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        $member = Members::LoadByEmail($email);
        if(!$member) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-not-found;Пользователь не найден}', 
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-with-email-not-exists;Пользователь с таким email-ом не существует}'
                ]
            ]);
        }
        
        if($member->phone != $phone) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-phone-incorrect;Неверный номер телефона}', 
                'code' => 400,
                'validation' => [
                    'phone' => '#{auth-errors-member-with-phone-not-exists;Пользователь с таким телефоном не существует}='
                ]
            ]);
        }

        if($password != $confirmation) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-password-not-confirmed;Пароль не подтвержден}', 
                'code' => 400, 
                'validation' => [
                    'confirmation' => '#{auth-errors-member-password-not-confirmed;Пароль не подтвержден}'
                ]
            ]);
        }

        if(!$member->ResetPassword($code, $password)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-reset-error;Невозможно сохранить этот пароль}', 'code' => 400]);
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
    public function ChangeIdentity(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if(!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged;Пользователь не залогинен}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => '#{auth-errors-member-data-consistency;Ошибка консистентности данных}', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->property;
        $code = $payloadArray['code'] ?? $post->code;
        $value = $payloadArray['value'] ?? $post->value;
        
        if(!$property || !$code || !$value) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        if( ($property === 'email' && $member->email === $value) || ($property === 'phone' && $member->phone === $value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        if($property === 'email' && Members::LoadByEmail($value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-with-email-exists;Пользователь с таким email-ом существует}', 'code' => 400]);
        }
        else if($property === 'phone' && Members::LoadByPhone($value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-with-phone-exists;Пользователь с таким телефоном существует}', 'code' => 400]);
        }

        if(!$member->UpdateIdentify($property, $code, $value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-update-error;Невозможно обновить свойство}', 'code' => 400]);
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
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged;Пользователь не залогинен}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);

        $payloadArray = $payload->ToArray();
        $original = $payloadArray['original'] ?? $post->original;
        $password = $payloadArray['password'] ?? $post->password;
        $confirmation = $payloadArray['confirmation'] ?? $post->confirmation;
        
        if(!$original || !$password || !$confirmation) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }
        
        if(!$member->Authorize($original)) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-password-error;Неверный текущий пароль}', 
                'code' => 400, 
                'validation' => [
                    'original' => '#{auth-errors-member-password-error;Неверный пароль}'
                ]
            ]);
        }

        if($password != $confirmation) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-password-not-confirmed;Пароль не подтвержден}', 
                'code' => 400, 
                'validation' => [
                    'confirmation' => '#{auth-errors-member-password-not-confirmed;Пароль не подтвержден}'
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
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged;Пользователь не залогинен}', 'code' => 400]);
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
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged;Пользователь не залогинен}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => '#{auth-errors-member-data-consistency;Ошибка консистентности данных}', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $firstName = $payloadArray['first_name'] ?? $post->first_name;
        $lastName = $payloadArray['last_name'] ?? $post->last_name;
        $patronymic = $payloadArray['patronymic'] ?? $post->patronymic;
        $gender = $payloadArray['gender'] ?? $post->gender;
        $birthdate = $payloadArray['birthdate'] ?? $post->birthdate;
        
        if(!$firstName || !$lastName) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        if(!$member->UpdateProfile($firstName, $lastName, $patronymic, $gender, $birthdate)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-error-profile;Не смогли сохранить профиль}', 'code' => 400]);
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
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged;Пользователь не залогинен}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if(!$member) {
            return $this->Finish(500, 'Bad Request', ['message' => '#{auth-errors-member-data-consistency;Ошибка консистентности данных}', 'code' => 500]);
        }
        
        $payloadArray = $payload->ToArray();
        $currentPassword = $payloadArray['current'] ?? $post->current;
        $newPassword = $payloadArray['new'] ?? $post->new;
        
        if(!$currentPassword || !$newPassword) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect;Неверные данные в запросе}', 'code' => 400]);
        }

        if(!$member->UpdatePassword($currentPassword, $newPassword)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-error-password;Не смогли обновить пароль}', 'code' => 400]);
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