<?php



namespace App\Modules\Auth\Controllers\V1;


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

        
        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            [],
            'utf-8'
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

        
        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            [],
            'utf-8'
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
        
        
        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            [],
            'utf-8'
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
        
        
        // финишируем контроллер
        return $this->Finish(
            200,
            'ok',
            [],
            'utf-8'
        );
    }


}