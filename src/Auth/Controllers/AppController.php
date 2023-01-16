<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Module;
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

        $app = Module::$instance->application;
        $settings = $app->ExportForUserInterface();

        $membersStorage = Storages::Create()->Load('members');
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

}