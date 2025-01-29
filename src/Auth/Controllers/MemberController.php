<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Models\AutoLogins;
use App\Modules\Auth\Models\Confirmation;
use App\Modules\Auth\Models\Confirmations;
use App\Modules\Auth\Models\Invitations;
use App\Modules\Auth\Models\Member;
use App\Modules\Auth\Models\Members;
use App\Modules\Auth\Models\Sessions;
use App\Modules\Auth\Module;
use App\Modules\Tools\Models\Notices;
use Colibri\App;
use Colibri\Common\RandomizationHelper;
use Colibri\Common\StringHelper;
use Colibri\Common\VariableHelper;
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Exceptions\ValidationException;
use Colibri\Utils\Debug;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;
use Psr\Log\InvalidArgumentException;
use Throwable;

/**
 * Members controller
 */
class MemberController extends WebController
{

    /**
     * Registers a member
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function Register(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();

        $payloadArray = $payload->ToArray();
        $email = $payloadArray['email'] ?? $post->{'email'};
        $email_confirmed = $payloadArray['email_confirmed'] ?? $post->{'email_confirmed'};
        $phone = $payloadArray['phone'] ?? $post->{'phone'};
        $phone_confirmed = $payloadArray['phone_confirmed'] ?? $post->{'phone_confirmed'};
        $firstName = $payloadArray['first_name'] ?? $post->{'first_name'};
        $lastName = $payloadArray['last_name'] ?? $post->{'last_name'};
        $patronymic = $payloadArray['patronymic'] ?? $post->{'patronymic'};
        $gender = $payloadArray['gender'] ?? $post->{'gender'};
        $birthdate = $payloadArray['birthdate'] ?? $post->{'birthdate'};
        $password = $payloadArray['password'] ?? $post->{'password'};
        $confirmation = $payloadArray['confirmation'] ?? $post->{'confirmation'};
        $role = $payloadArray['role'] ?? $post->{'role'};
        $invitationCode = $payloadArray['invitation'] ?? $post->{'invitation'};
        if($invitationCode) {
            $invitation = Invitations::LoadByCode($invitationCode);
            if(!$invitation || $invitation->accepted) { 
                return $this->Finish(400, 'Bad Request', [
                    'message' => 'Invalid data in request',
                    'code' => 400
                ]);
            }

            $email = $invitation->email;
            $phone = $invitation->phone ?: $phone;
            $role = $invitation->params?->role ?: $role;

            $invitation->accepted = new DateTimeField('now');
            $invitation->Save(true);
        }

        if (!$email || !$phone || !$password || !$confirmation) {
            $validation = [];
            if (!$email) {
                $validation['email'] = 'Field «email» is required';
            }
            if (!$phone) {
                $validation['phone'] = 'Field «phone» is required';
            }
            if (!$password) {
                $validation['password'] = 'Field «password» is required';
            }
            if (!$confirmation) {
                $validation['confirmation'] = 'Field «confirmation» is required';
            }
            return $this->Finish(400, 'Bad Request', [
                'message' => 'Invalid data in request',
                'code' => 400,
                'validation' => $validation
            ]);
        }

        if (!StringHelper::IsEmail($email)) {
            $validation['email'] = 'Field «email» contains invalid email address';
        }

        if (Members::LoadByEmail($email) !== null) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-with-email-exists}',
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-with-email-exists}'
                ]
            ]);
        }
        if (Members::LoadByPhone($phone) !== null) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-with-phone-exists}',
                'code' => 400,
                'validation' => [
                    'phone' => '#{auth-errors-member-with-phone-exists}'
                ]
            ]);
        }

        if ($password != $confirmation) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-password-not-confirmed}',
                'code' => 400,
                'validation' => [
                    'confirmation' => '#{auth-errors-member-password-not-confirmed}'
                ]
            ]);
        }

        if (!$role) {
            $role = Module::$instance->application->params->defaultrole;
        }

        if (!Module::$instance->application->CheckRole($role)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-role-not-exists}', 'code' => 400]);
        }

        if (($strength = Member::CheckPasswordStrength($email, $password)) < 40) {
            return $this->Finish(400, 'Bad request', [
                'message' => sprintf('#{auth-errors-member-password-strength-not-match}', $strength),
                'code' => 400,
                'validation' => [
                    'password' => sprintf('#{auth-errors-member-password-strength-not-match}', $strength)
                ]
            ]);
        }

        try {
            $member = Members::Register($email, $phone, $password);
            $member->first_name = $firstName;
            $member->last_name = $lastName;
            $member->patronymic = $patronymic;
            $member->birthdate = $birthdate ? new DateTimeField($birthdate) : null;
            $member->gender = $gender;
            $member->role = $role;
            $member->email_confirmed = $email_confirmed ?: false;
            $member->phone_confirmed = $phone_confirmed ?: false;
            $member->blocked = false;
            $member->two_factor = true;

            if (($res = $member->Save(true)) !== true) {
                /** @var \Colibri\Data\SqlClient\QueryInfo $res */
                throw new InvalidArgumentException($res->error, 400);
            }

            $session->member = $member->token;
            if (($res = $session->Save(true)) !== true) {
                /** @var \Colibri\Data\SqlClient\QueryInfo $res */
                throw new InvalidArgumentException($res->error, 400);
            }

        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Begins a confirmation process
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function BeginConfirmationProcess(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();

        /** @var \App\Modules\Auth\Models\Application|null $app */
        $app = Module::$instance->application;
        if (!$app) {
            throw new InvalidArgumentException('Application not found', 404);
        }

        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->{'property'};
        $value = $payloadArray['value'] ?? $post->{'value'};
        if (!$property || !$value) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        // проверяем нет ли пользователя с такими свойством и значением этого свойства
        if($property === Confirmation::PropertyEmail) {
            $member = Members::LoadByEmail($value);
        } else if($property === Confirmation::PropertyPhone) {
            $member = Members::LoadByPhone($value);
        }

        // if($member && $member->{$property.'_verified'}) {
        //     return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        // }

        $confirmation = Confirmations::LoadByValue($property, $value);
        if(!$confirmation) {
            $confirmation = Confirmations::LoadEmpty();
            $confirmation->property = $property;
            $confirmation->value = $value;
        }

        $confirmation->code = RandomizationHelper::Numeric(6);
        $confirmation->verified = false;
        $confirmation->Save();

        if(App::$isDev) {

            return $this->Finish(
                200,
                'ok',
                ['session' => $session->ExportForUserInterface(), 'code' => $confirmation->code],
                'utf-8',
                [],
                [$session->GenerateCookie(true)]
            );
    
        }

        $res = $confirmation->Send($value, $app->params->proxies);
        if (!$res) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-property-send-error}', 'code' => 400]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Begins a password reset process
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function BeginPasswordResetProcess(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if ($session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-logged}', 'code' => 400]);
        }

        $payloadArray = $payload->ToArray();
        $email = $payloadArray['email'] ?? $post->{'email'};
        $phone = $payloadArray['phone'] ?? $post->{'phone'};

        if (!$email || !$phone) {
            $validation = [];
            if (!$email) {
                $validation['email'] = '#{auth-errors-member-field-required}';
            }
            if (!$phone) {
                $validation['phone'] = '#{auth-errors-member-field-required}';
            }
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-data-incorrect}',
                'code' => 400,
                'validation' => $validation
            ]);
        }

        $member = Members::LoadByEmail($email);
        if (!$member) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-not-found}',
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-with-email-not-exists}'
                ]
            ]);
        }

        if ($member->phone != $phone) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-phone-incorrect}',
                'code' => 400,
                'validation' => [
                    'phone' => '#{auth-errors-member-with-phone-not-exists}'
                ]
            ]);
        }

        if(App::$isDev) {

            /** @var \App\Modules\Auth\Models\Application|null $app */
            $app = Module::$instance->application;

            $confirmation = Confirmations::LoadByMember(Confirmation::PropertyReset, $member->token);
            if (!$confirmation) {
                $confirmation = Confirmations::LoadEmpty();
                $confirmation->property = Confirmation::PropertyReset;
                $confirmation->member = $member->token;
            }

            $confirmation->code = RandomizationHelper::Numeric(6);
            if (($res = $confirmation->Save(true)) !== true) {
                throw new InvalidArgumentException($res->error, 500);
            }

            return $this->Finish(
                200,
                'ok',
                ['session' => $session->ExportForUserInterface(), 'code' => $confirmation->code],
                'utf-8',
                [],
                [$session->GenerateCookie(true)]
            );
    
        }

        if (!$member->SendResetMessage()) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-property-send-error}',
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-property-send-error}'
                ]
            ]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }


    /**
     * Begins an identity update process
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function BeginIdentityUpdateProcess(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if (!$member) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->{'property'};
        $value = $payloadArray['value'] ?? $post->{'value'};

        if (!$property || !$value) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        if (($property === 'email' && $member->email === $value) || ($property === 'phone' && $member->phone === $value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        if ($property === 'email' && Members::LoadByEmail($value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-with-email-exists}', 'code' => 400]);
        } elseif ($property === 'phone' && Members::LoadByPhone($value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-with-phone-exists}', 'code' => 400]);
        }

        if(App::$isDev) {

            /** @var \App\Modules\Auth\Models\Application|null $app */
            $app = Module::$instance->application;

            $confirmation = Confirmations::LoadByMember($property, $member->token);
            if (!$confirmation) {
                $confirmation = Confirmations::LoadEmpty();
                $confirmation->property = $property;
                $confirmation->member = $member->token;
            }

            $confirmation->code = RandomizationHelper::Numeric(6);
            if (($res = $confirmation->Save(true)) !== true) {
                throw new InvalidArgumentException($res->error, 500);
            }

            return $this->Finish(
                200,
                'ok',
                ['session' => $session->ExportForUserInterface(), 'code' => $confirmation->code],
                'utf-8',
                [],
                [$session->GenerateCookie(true)]
            );
    
        }


        if (!$member->SendConfirmationMessage($property, $value)) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-property-send-error}',
                'code' => 400,
                'validation' => [
                    'property' => '#{auth-errors-member-property-send-error}'
                ]
            ]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Confirms a property
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function ConfirmProperty(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();

        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->{'property'};
        $value = $payloadArray['value'] ?? $post->{'value'};
        $code = $payloadArray['code'] ?? $post->{'code'};

        if (!$property || !$code || !$value) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        $confirmation = Confirmations::LoadByValue($property, $value);
        if(!$confirmation || $confirmation->verified) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        try {
            
            if($confirmation->code !== $code) {
                throw new InvalidArgumentException('Invalid code', 403);
            }

            $confirmation->verified = true;
            if( ($res = $confirmation->Save()) !== true) {
                throw new InvalidArgumentException($res->error, 500);
            }

        } catch (\InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-confirmation-code-error}',
                'code' => 400,
                'validation' => [
                    'code' => '#{auth-errors-member-confirmation-code-error}'
                ]
            ]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-confirmation-error}', 'code' => 400]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Resets a password
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function ResetPassword(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if ($session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-logged}', 'code' => 400]);
        }

        $payloadArray = $payload->ToArray();
        $email = $payloadArray['email'] ?? $post->{'email'};
        $phone = $payloadArray['phone'] ?? $post->{'phone'};
        $code = $payloadArray['code'] ?? $post->{'code'};
        $password = $payloadArray['password'] ?? $post->{'password'};
        $confirmation = $payloadArray['confirmation'] ?? $post->{'confirmation'};

        if (!$email || !$phone || !$code || !$password) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        $member = Members::LoadByEmail($email);
        if (!$member) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-not-found}',
                'code' => 400,
                'validation' => [
                    'email' => '#{auth-errors-member-with-email-not-exists}'
                ]
            ]);
        }

        if ($member->phone != $phone) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-phone-incorrect}',
                'code' => 400,
                'validation' => [
                    'phone' => '#{auth-errors-member-with-phone-not-exists}'
                ]
            ]);
        }

        if ($password != $confirmation) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-password-not-confirmed}',
                'code' => 400,
                'validation' => [
                    'confirmation' => '#{auth-errors-member-password-not-confirmed}'
                ]
            ]);
        }

        try {
            $member->ResetPassword($code, $password);
        } catch (\InvalidArgumentException $e) {
            if ($e->getCode() == 400) {
                return $this->Finish(400, 'Bad Request', [
                    'message' => '#{auth-errors-member-reset-code-error}',
                    'code' => 400,
                    'validation' => [
                        'code' => '#{auth-errors-member-reset-code-error}'
                    ]
                ]);
            }
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-reset-error}', 'code' => 400]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }


    /**
     * Changes an identity
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function ChangeIdentity(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if (!$member) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->{'property'};
        $code = $payloadArray['code'] ?? $post->{'code'};
        $value = $payloadArray['value'] ?? $post->{'value'};

        if (!$property || !$code || !$value) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        if (($property === 'email' && $member->email === $value) || ($property === 'phone' && $member->phone === $value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        if ($property === 'email' && Members::LoadByEmail($value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-with-email-exists}', 'code' => 400]);
        } elseif ($property === 'phone' && Members::LoadByPhone($value)) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-with-phone-exists}', 'code' => 400]);
        }

        try {

            if (!$member->UpdateIdentify($property, $code, $value)) {
                throw new InvalidArgumentException('#{auth-errors-member-update-error}', 400);
            }

            $session->member = $member->token;
            if (($res = $session->Save(true)) !== true) {
                /** @var \Colibri\Data\SqlClient\QueryInfo $res */
                throw new InvalidArgumentException($res->error, 400);
            }

        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }


        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Changes a password
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function ChangePassword(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);

        $payloadArray = $payload->ToArray();
        $original = $payloadArray['original'] ?? $post->{'original'};
        $password = $payloadArray['password'] ?? $post->{'password'};
        $confirmation = $payloadArray['confirmation'] ?? $post->{'confirmation'};

        if (!$original || !$password || !$confirmation) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        if (!$member->Authorize($original)) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-password-error}',
                'code' => 400,
                'validation' => [
                    'original' => '#{auth-errors-member-password-error}'
                ]
            ]);
        }

        if ($password != $confirmation) {
            return $this->Finish(400, 'Bad Request', [
                'message' => '#{auth-errors-member-password-not-confirmed}',
                'code' => 400,
                'validation' => [
                    'confirmation' => '#{auth-errors-member-password-not-confirmed}'
                ]
            ]);
        }

        try {

            $member->password = $password;
            if (($res = $member->Save(true)) !== true) {
                /** @var \Colibri\Data\SqlClient\QueryInfo $res */
                throw new InvalidArgumentException($res->error, 400);
            }

        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Blocks a member account
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function BlockAccount(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        try {

            $member = Members::LoadByToken($session->member);
            $member->blocked = 1;
            if (($res = $member->Save(true)) !== true) {
                /** @var \Colibri\Data\SqlClient\QueryInfo $res */
                throw new InvalidArgumentException($res->error, 400);
            }

            $session->member = null;
            if (($res = $session->Save(true)) !== true) {
                /** @var \Colibri\Data\SqlClient\QueryInfo $res */
                throw new InvalidArgumentException($res->error, 400);
            }

        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Toggles a two-factor authorization on or off
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function ToggleTwoFactorAuth(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        try {
            $member->two_factor = !$member->two_factor;
            if (($res = $member->Save(true)) !== true) {
                /** @var \Colibri\Data\SqlClient\QueryInfo $res */
                throw new InvalidArgumentException($res->error, 400);
            }
        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Updates a member profile
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function UpdateProfile(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if (!$member) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $firstName = $payloadArray['first_name'] ?? $post->{'first_name'};
        $lastName = $payloadArray['last_name'] ?? $post->{'last_name'};
        $patronymic = $payloadArray['patronymic'] ?? $post->{'patronymic'};
        $gender = $payloadArray['gender'] ?? $post->{'gender'};
        $birthdate = $payloadArray['birthdate'] ?? $post->{'birthdate'};

        if (!$firstName || !$lastName) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        try {
            if (!$member->UpdateProfile($firstName, $lastName, $patronymic, $gender, $birthdate)) {
                throw new InvalidArgumentException('#{auth-errors-member-error-profile}', 400);
            }
        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }


    /**
     * Updates a password
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function UpdatePassword(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if (!$member) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $currentPassword = $payloadArray['current'] ?? $post->{'current'};
        $newPassword = $payloadArray['new'] ?? $post->{'new'};

        if (!$currentPassword || !$newPassword) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-data-incorrect}', 'code' => 400]);
        }

        try {
            if (!$member->UpdatePassword($currentPassword, $newPassword)) {
                throw new InvalidArgumentException('#{auth-errors-member-error-password}', 400);
            }
        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Returns a list of members for moderators and administrators
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function List(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if (!$member) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        if ($member->role === 'user') {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $tokens = $payloadArray['tokens'] ?? $post->{'tokens'};

        $ret = [];
        $members = Members::LoadByTokens((array) $tokens);
        foreach ($members as $member) {
            /** @var Member $member */
            $ret[$member->token] = $member->ExportForUserInterface(true);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface(), 'list' => $ret],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Returns a list of roles
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function ListByRole(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if (!$member) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $role = $payloadArray['role'] ?? $post->{'role'};
        if (!$role) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $ret = [];
        $members = Members::LoadByRole($role);
        foreach ($members as $member) {
            /** @var Member $member */
            $ret[$member->token] = $member->ExportForUserInterface(true);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface(), 'list' => $ret],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Searches for members
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function Search(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if (!$member) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        if ($member->role === 'user') {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $term = $payloadArray['term'] ?? $post->{'term'};

        $ret = [];
        $members = Members::LoadByFilter(-1, 20, 'concat({last_name}, \' \', {first_name}, \' \', {patronymic}, \' \', {phone}, \' \', {email}) like \'%' . $term . '%\'');
        foreach ($members as $member) {
            /** @var Member $member */
            $ret[$member->token] = $member->ExportForUserInterface(true);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface(), 'list' => $ret],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Performs a mutation fo member data
     * @param RequestCollection $get
     * @param RequestCollection $post
     * @param PayloadCopy|null $payload
     * @return object
     */
    public function PerformMutation(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if (!$member) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        if ($member->role !== 'administrator') {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $memberToken = $payloadArray['member'] ?? $post->{'member'};
        $mutation = $payloadArray['mutation'] ?? $post->{'mutation'};

        $member = Members::LoadByToken($memberToken);

        try {

            $member->Update($mutation);

            $data = [];
            foreach ($mutation as $key => $value) {
                $field = $member->Storage()->fields->$key;
                if ($field->type === 'bool') {
                    $data[] = $field->desc . ': ' . ($value ? '#{auth-bool-data-true}' : '#{auth-bool-data-false}');
                } else {
                    $data[] = $field->desc . ': ' . $value;
                }
            }

            $dataAsString = implode('<br />', $data);
            if (App::$moduleManager->Get('lang')) {
                /** @var \App\Modules\Lang\Module */
                $langModule = App::$moduleManager->Get('lang');
                $dataAsString = $langModule->ParseString($dataAsString);
            }

            $langModule = App::$moduleManager->{'lang'};
            $notice = Notices::LoadByName('administrator_reset' . ($langModule ? '_' . App::$moduleManager->{'lang'}->current : ''));
            $notice->Apply(['data' => $dataAsString, 'first_name' => $member->first_name]);
            Notices::Send($member->email, $notice);

        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface(), 'member' => $member->ExportForUserInterface(true)],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Changes a member role
     * @param RequestCollection $get
     * @param RequestCollection $post
     * @param PayloadCopy|null $payload
     * @return object
     */
    public function ChangeRole(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        $member = Members::LoadByToken($session->member);
        if (!$member) {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        if ($member->role !== 'administrator') {
            return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-data-consistency}', 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $memberEmail = $payloadArray['email'] ?? $post->{'email'};
        $memberRole = $payloadArray['role'] ?? $post->{'role'};

        $member = Members::LoadByEmail($memberEmail);
        if (!$member) {
            return $this->Finish(404, 'Not Found', ['message' => '#{auth-errors-member-not-found}', 'code' => 404]);
        }

        try {
            if (!$member->UpdateRole($memberRole)) {
                return $this->Finish(500, 'Application error', ['message' => '#{auth-errors-member-role-incorrect}', 'code' => 500]);
            }
        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        $langModule = App::$moduleManager->{'lang'};
        $notice = Notices::LoadByName('administrator_invite' . ($langModule ? '_' . App::$moduleManager->{'lang'}->current : ''));
        $notice->Apply(['first_name' => $member->first_name]);
        Notices::Send($member->email, $notice);

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface(), 'member' => $member->ExportForUserInterface(true)],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

    /**
     * Logout member from all windows
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function ForceLogout(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $result = [];
        $message = 'Result message';
        $code = 200;

        try {
            
            $session = Sessions::LoadFromRequest();
            if (!$session->member) {
                return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
            }

            $payloadArray = $payload->ToArray();
            $token = $payloadArray['token'] ?? $post->{'token'};

            $sessions = Sessions::LoadByMember($token);
            foreach ($sessions as $s) {
                /** @var \App\Modules\Auth\Models\Session $s */
                $s->member = null;
                $s->Save(true);
            }

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

    /**
     * Block account
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function ForceBlock(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        if (!$session->member) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
        }

        try {

            $payloadArray = $payload->ToArray();
            $token = $payloadArray['token'] ?? $post->{'token'};

            $member = Members::LoadByToken($token);
            $member->blocked = 1;
            if (($res = $member->Save(true)) !== true) {
                /** @var \Colibri\Data\SqlClient\QueryInfo $res */
                throw new InvalidArgumentException($res->error, 400);
            }

            $sessions = Sessions::LoadByMember($token);
            foreach ($sessions as $s) {
                /** @var \App\Modules\Auth\Models\Session $s */
                $s->member = null;
                $s->Save(true);
            }

        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => Debug::Rout($e->getExceptionData())]);
        } catch (Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        return $this->Finish(
            200,
            'ok',
            ['session' => $session->ExportForUserInterface()],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );

    }

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
            
            $session = Sessions::LoadFromRequest();
            if (!$session->member) {
                return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-logged}', 'code' => 400]);
            }

            $app = Module::$instance->application;
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
            $result['link'] = $autologin->GenerateLink();
            $result['session'] = $session->ExportForUserInterface();

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