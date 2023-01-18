<?php

namespace App\Modules\Auth\Controllers;

use Colibri\App;
use Colibri\Events\EventsContainer;
use Colibri\IO\FileSystem\File;
use Colibri\Utils\Cache\Bundle;
use Colibri\Utils\Debug;
use Colibri\Utils\ExtendedObject;
use Colibri\Utils\Minifiers\Javascript as Minifier;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;
use Colibri\Web\Templates\PhpTemplate;
use Colibri\Web\View;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

/**
 * Default controller
 */
class Controller extends WebController
{

    private function _initBundleEventHandlers()
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
            } elseif (in_array('js', $args->exts) && !App::$isDev) {
                try {
                    $args->content = Minifier::Minify($args->content);
                } catch (\Throwable $e) {
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
    }

    /**
     * Default action
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
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
     * Returns a bundle for integrate to other colibri sites
     *
     * @param RequestCollection $get
     * @param RequestCollection $post
     * @param object|null $payload
     * @return object
     */
    public function Bundle(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload): object
    {

        $this->_initBundleEventHandlers();

        $langModule = App::$moduleManager->lang;
        $themeFile = null;
        $themeKey = '';

        if (App::$moduleManager->tools) {
            $themeFile = App::$moduleManager->tools->Theme(App::$domainKey);
            $themeKey = md5($themeFile);
        }

        if (!App::$request->server->commandline) {
            $jsBundle = Bundle::Automate(App::$domainKey, ($langModule ? $langModule->current . '.' : '') . 'assets.bundle.js', 'js', [
                ['path' => App::$moduleManager->auth->modulePath . '.Bundle/', 'exts' => ['js', 'html']],
            ]);
            $cssBundle = Bundle::Automate(App::$domainKey, ($langModule ? $langModule->current . '.' : '') . ($themeKey ? $themeKey . '.' : '') . 'assets.bundle.css', 'scss', [
                ['path' => App::$moduleManager->auth->modulePath . 'web/res/css/'],
                ['path' => App::$moduleManager->auth->modulePath . '.Bundle/'],
                ['path' => $themeFile],
            ], 'https://' . App::$request->host);

            return $this->Finish(
                200,
                'Bundle created successfuly',
                (object) [
                    'js' => str_replace('http://', 'https://', App::$request->address) . $jsBundle,
                    'css' => str_replace('http://', 'https://', App::$request->address) . $cssBundle
                ],
                'utf-8'
            );
        } else if ($langModule) {

            // bundle all languages
            $oldLangKey = $langModule->current;
            $langs = $langModule->Langs();
            foreach ($langs as $langKey => $langData) {
                $langModule->InitCurrent($langKey);
                Bundle::Automate(App::$domainKey, ($langKey . '.') . 'assets.bundle.js', 'js', [
                    ['path' => App::$moduleManager->auth->modulePath . '.Bundle/', 'exts' => ['js', 'html']],
                ]);
                Bundle::Automate(App::$domainKey, ($langKey . '.') . ($themeKey ? $themeKey . '.' : '') . 'assets.bundle.css', 'scss', [
                    ['path' => App::$moduleManager->auth->modulePath . 'web/res/css/'],
                    ['path' => App::$moduleManager->auth->modulePath . '.Bundle/'],
                    ['path' => $themeFile],
                ], 'https://' . App::$request->host);

            }
            $langModule->InitCurrent($oldLangKey);
            exit;
        } else {
            Bundle::Automate(App::$domainKey, 'assets.bundle.js', 'js', [
                ['path' => App::$moduleManager->auth->modulePath . '.Bundle/', 'exts' => ['js', 'html']],
            ]);
            Bundle::Automate(App::$domainKey, ($themeKey ? $themeKey . '.' : '') . 'assets.bundle.css', 'scss', [
                ['path' => App::$moduleManager->auth->modulePath . 'web/res/css/'],
                ['path' => App::$moduleManager->auth->modulePath . '.Bundle/'],
                ['path' => $themeFile],
            ], 'https://' . App::$request->host);
            exit;
        }
    }


}