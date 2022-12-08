<?php

namespace App\Modules\Auth\Controllers;

use Colibri\Web\RequestCollection;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use App\Modules\Auth\Module;
use Colibri\Data\Storages\Storages;

class AppController extends WebController
{

    /**
     * Получение настроек приложения
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
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

