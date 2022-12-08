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

class Controller extends WebController
{

    /**
     * Экшен по умолчанию
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     */
    public function Index(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $module = App::$moduleManager->carriergate;

        // создаем обьект View
        $view = View::Create();

        // создаем обьект шаблона
        $template = PhpTemplate::Create($module->modulePath . 'templates/index');

        // собираем аргументы
        $args = new ExtendedObject([
            'get' => $get,
            'post' => $post,
            'payload' => $payload
        ]);

        try {
            // пробуем запустить генерацию html
            $html = $view->Render($template, $args);
        } catch (\Throwable $e) {
            // если что то не так то выводим ошибку
            $html = $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine();
        }

        // финишируем контроллер
        return $this->Finish(
            200,
            $html,
            [],
            'utf-8',
            [
                'tab_key' => 'auth-list',
                'tab_type' => 'tab',
                'tab_title' => 'Tunnel Auth',
                'tab_color' => 'orange',
                'tab_header' => 'Tunnel Auth',
            ]
        );
    }

    /**
     * Возвращает бандл для работы внутренних js моделей
     *
     * @param RequestCollection $get
     * @param RequestCollection $post
     * @param object|null $payload
     * @return object
     */
    public function Bundle(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload): object
    {

        App::$instance->HandleEvent(EventsContainer::BundleComplete, function ($event, $args) {
            if (in_array('scss', $args->exts)) {
                try {
                    $scss = new Compiler();
                    $scss->setOutputStyle(OutputStyle::EXPANDED);
                    $args->content = $scss->compileString($args->content)->getCss();
                } catch (\Exception $e) {
                    Debug::Out($e->getMessage());
                }
            }
            return true;
        });

        App::$instance->HandleEvent(EventsContainer::BundleFile, function ($event, $args) {

            $file = new File($args->file);
            if ($file->extension == 'html') {
                // компилируем html в javascript
                $componentName = $file->filename;
                $res = preg_match('/ComponentName="([^"]*)"/i', $args->content, $matches);
                if ($res > 0) {
                    $componentName = $matches[1];
                }
                $compiledContent = str_replace('\'', '\\\'', str_replace("\n", "", str_replace("\r", "", $args->content)));
                $compiledContent = str_replace('ComponentName="' . $componentName . '"', 'namespace="' . $componentName . '"', $compiledContent);
                $args->content = 'Colibri.UI.AddTemplate(\'' . $componentName . '\', \'' . $compiledContent . '\');' . "\n";
            }

        });

        $jsBundle = Bundle::Automate(App::$domainKey, 'assets.bundle.js', 'js', [
            ['path' => App::$moduleManager->auth->modulePath . '.Bundle/', 'exts' => ['js', 'html']],
        ]);
        $cssBundle = Bundle::Automate(App::$domainKey, 'assets.bundle.css', 'scss', array(
            ['path' => App::$moduleManager->auth->modulePath . 'web/res/css/'],
            ['path' => App::$moduleManager->auth->modulePath . '.Bundle/'],
        ), 'https://' . App::$request->host);

        return $this->Finish(
            200,
            'Bundle created successfuly',
            (object) [
                'js' => str_replace('http://', 'https://', App::$request->address) . $jsBundle,
                'css' => str_replace('http://', 'https://', App::$request->address) . $cssBundle
            ],
            'utf-8'
        );
    }


}