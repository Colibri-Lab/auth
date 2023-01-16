<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Models\Members;
use App\Modules\Auth\Models\Sessions;
use Colibri\Exceptions\ValidationException;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;

/**
 * Session controller
 */
class SessionController extends WebController
{

    /**
     * Starts a session
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function Start(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {
        try {
            $session = Sessions::LoadFromRequest();
        } catch (\InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => $e->getExceptionDataAsArray()]);
        } catch (\Throwable $e) {
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
     * Logins member into session
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function Login(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $session = Sessions::LoadFromRequest();
        if ($session->member) {
            return $this->Finish(403, 'Forbidden', ['message' => '#{auth-errors-session-allreadylogged}', 'code' => 403]);
        }


        $payloadArray = $payload->ToArray();
        $login = $payloadArray['login'] ?? $post->login;
        $password = $payloadArray['password'] ?? $post->password;
        $code = $payloadArray['password'] ?? $post->code;

        if (!$login || !$password) {
            return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-session-data-incorrect}', 'code' => 400]);
        }

        
        $member = Members::LoadByEmail($login);
        if (!$member) {
            $member = Members::LoadByPhone($login);
        }

        if (!$member || $member->blocked) {
            return $this->Finish(403, 'Forbidden', ['message' => '#{auth-errors-member-account-not-exists}', 'code' => 403]);
        }


        if (!$member->Authorize($password)) {
            return $this->Finish(403, 'Forbidden', ['message' => '#{auth-errors-member-invalid-creds}', 'code' => 403]);
        }

        try {
            
            if ($member->two_factor) {
                if ($code) {
                    if (!$member->ConfirmLogin($code)) {
                        return $this->Finish(403, 'Forbidden', ['message' => '#{auth-errors-member-property-tho-factor-error}', 'code' => 403]);
                    }
                } else {
                    if (!$member->SendTwoFactorAuthorizationMessage()) {
                        return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-property-send-error}', 'code' => 400]);
                    } else {
                        return $this->Finish(206, 'Tho factor authentification', ['message' => '#{auth-errors-member-property-two-factor-needed}', 'code' => 206]);
                    }
                }
            }
    
            $session->member = $member->token;
            $session->Save(true);


        } catch (\InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => $e->getExceptionDataAsArray()]);
        } catch (\Throwable $e) {
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
     * Logouts member from session
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function Logout(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        try {
            $session = Sessions::LoadFromRequest();            
            $session->member = null;
            $session->Save(true);
        } catch (\InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => $e->getExceptionDataAsArray()]);
        } catch (\Throwable $e) {
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
     * Logouts member from all sessions
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function LogoutFromAll(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        try {
            
            $session = Sessions::LoadFromRequest();
            if (!$session->member) {
                return $this->Finish(403, 'Forbidden', ['message' => '#{auth-errors-session-notlogged}', 'code' => 403]);
            }

            $sessions = Sessions::LoadByMember($session->member);
            foreach ($sessions as $s) {
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
        

        $session->member = null;

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
     * Decodes a session data
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function Decode(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        try {
            $session = Sessions::LoadFromRequest();
        } catch (\InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => $e->getExceptionDataAsArray()]);
        } catch (\Throwable $e) {
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
     * Checks if the member exists with given property data
     * @param RequestCollection $get
     * @param RequestCollection $post
     * @param PayloadCopy|null $payload
     * @return object
     */
    public function CheckProperty(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        try {
            $session = Sessions::LoadFromRequest();
        } catch (\InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => $e->getExceptionDataAsArray()]);
        } catch (\Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }

        $payloadArray = $payload->ToArray();
        $property = $payloadArray['property'] ?? $post->property;
        $value = $payloadArray['value'] ?? $post->value;
        
        if($property === 'email') {
            $members = Members::LoadByEmail($value);
        } elseif ($property === 'phone') {
            $members = Members::LoadByPhone($value);
        }

        $exists = $members && $members->Count() > 0;

        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            ['exists' => $exists],
            'utf-8',
            [],
            [$session->GenerateCookie(true)]
        );
    }


}
