<?php

namespace App\Modules\Auth\Controllers;

use Colibri\App;
use Colibri\AppException;
use Colibri\Events\EventsContainer;
use Colibri\IO\FileSystem\File;
use Colibri\IO\FileSystem\Finder;
use Colibri\IO\Request\Request;
use Colibri\Utils\Cache\Bundle;
use Colibri\Utils\ExtendedObject;
use Colibri\Web\Controller as WebController;
use Colibri\Web\Templates\PhpTemplate;
use Colibri\Web\View;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use VaultApiClient\Client;
use Colibri\Web\RequestCollection;
use Colibri\Web\PayloadCopy;
use Colibri\Utils\Minifiers\Javascript as Minifier;
use Colibri\Exceptions\ApplicationErrorException;
use Colibri\IO\FileSystem\Directory;

/**
 * Default controller
 * @author Vahan P. Grigoryan
 * @package App\Modules\Auth\Controllers
 */
class Controller extends WebController
{
    /**
     * Initialized the bundle handlers
     * @return void
     */
    private function _initDefaultBundleHandlers()
    {
        // Обработка scss
        App::Instance()->HandleEvent(EventsContainer::BundleComplete, function ($event, $args) {
            try {
                if (in_array('scss', $args->exts)) {
                    $scss = new Compiler();
                    $scss->setOutputStyle(App::$isDev ? OutputStyle::EXPANDED : OutputStyle::COMPRESSED);
                    $args->content = $scss->compileString($args->content)->getCss();
                } elseif (in_array('js', $args->exts) && !App::$isDev) {
                    $args->content = Minifier::Minify($args->content);
                }
            } catch (\Throwable $e) {
                App::$log->emergency($e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
                throw new ApplicationErrorException('Can not compile assets: ' . $e->getMessage());
            }
            return true;
        });

        // если есть бандлер то нужно запустить генерация javascript из шаблонов
        App::Instance()->HandleEvent(EventsContainer::BundleFile, function ($event, $args) {
            $file = new File($args->file);
            if ($file->extension !== 'html') {
                return true;
            }

            // компилируем html в javascript
            $componentName = $file->filename;
            if (preg_match('/ComponentName="([^"]*)"/i', $args->content, $matches) > 0) {
                $componentName = $matches[1];
            }

            $compiledContent = str_replace(
                ['\'', "\n", "\r", 'ComponentName="' . $componentName . '"'],
                ['\\\'', "' + \n'", "", 'namespace="' . $componentName . '"'],
                $args->content
            );

            $args->content = 'Colibri.UI.AddTemplate(\'' . $componentName . '\', ' . "\n" . '\'' . $compiledContent . '\');' . "\n";

            return true;
        });
    }

    /**
     * Default action
     * @param mixed $get
     * @param mixed $post
     * @param mixed $payload
     * @return object
     */
    public function Index(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload): object
    {

        $this->_initDefaultBundleHandlers();

        // создаем класс View
        $view = View::Create();

        // создаем обьект шаблона
        $template = PhpTemplate::Create(App::$appRoot . '/templates/index');

        try {
            // пытаемся сгенерировать страницу
            $html = $view->Render($template, new ExtendedObject([
                'get' => $get,
                'post' => $post,
                'payload' => $payload
            ]));
            $code = 200;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $code = $e->getCode() ?: 404;
            $file = $e->getFile();
            $line = $e->getLine();
            $trace = $e->getTraceAsString();
            ob_start();
            require('error.php');
            $html = ob_get_contents();
            ob_end_clean();
        }

        return $this->Finish($code, $html);

    }

    /**
     * Returns the application settings
     * @param RequestCollection $get data from get request
     * @param RequestCollection $post a request post data
     * @param mixed $payload payload object in POST/PUT request
     * @return object
     */
    public function Settings(RequestCollection $get, RequestCollection $post, ?PayloadCopy $payload = null): object
    {

        $appConfig = App::$config;
        $result = array_merge($appConfig->Query('settings')->AsArray(), [
            'mode' => App::$mode,
            'hosts' => $appConfig->Query('hosts')->AsArray(),
            'current' => App::$domainKey,
            'comet' => $appConfig->Query('comet')->AsArray(),
            'res' => '/' . $appConfig->Query('res')->GetValue()
        ]);
        return $this->Finish(200, 'Settings', $result);

    }

    /**
     * Download keys/passwords from colibri vault
     * @param mixed $get
     * @param mixed $post
     * @param mixed $payload
     * @return \stdClass
     */
    public function Vault($get, $post, $payload)
    {

        if (App::$request->server->{'commandline'}) {
            $fi = new Finder();
            $files = $fi->Files(App::$appRoot . 'config/');
            foreach ($files as $file) {
                /** @var File $file */
                Client::Vault($file->path);
            }
        } else {
            throw new AppException('This command is allowed only in commandline mode');
        }


        return $this->Finish(200, 'ok', []);

    }

    /**
     * Bundles the scripts and styles
     * @param mixed $get
     * @param mixed $post
     * @param mixed $payload
     * @return \stdClass
     */
    /**
     * Bundles the scripts and styles
     * @param mixed $get
     * @param mixed $post
     * @param mixed $payload
     * @return \stdClass
     */
    public function Bundle($get, $post, $payload)
    {

        $this->_initDefaultBundleHandlers();

        $files = [];
        $langModule = App::$moduleManager->Get('lang');
        $selectedLangs = $get?->langs ? explode(',', $get?->langs) : [];

        $themeFile = null;
        $themeKey = '';

        if(App::$moduleManager->Get('tools')) {
            $themeFile = App::$moduleManager->Get('tools')->Theme(App::$domainKey);
            $themeKey = md5($themeFile);
        }

        $jsa = $cssa = [];
        if (!$langModule) {
            // языки не подключены
            $css = Bundle::Automate(
                App::$domainKey,
                ($themeKey ? '.' . $themeKey : '') . '.assets.css',
                'scss',
                array_merge(
                    [['path' => App::$appRoot . 'vendor/colibri/ui/src/']],
                    [['path' => $themeFile]],
                    [['path' => App::$webRoot . 'res/css/']],
                    App::$moduleManager->GetPathsFromModuleConfig(),
                    App::$moduleManager->GetPaths('web/res/css/'),
                    App::$moduleManager->GetPaths('.Bundle/'),
                    App::$moduleManager->GetPaths('templates/')
                )
            );
            $files[] = $css;
            $cssa[] = $css;
            $js = Bundle::Automate(
                App::$domainKey,
                '.assets.js',
                'js',
                array_merge(
                    [['path' => App::$appRoot . 'vendor/colibri/ui/src/', 'exts' => ['js', 'html']]],
                    App::$moduleManager->GetPathsFromModuleConfig(['exts' => ['js', 'html']]),
                    App::$moduleManager->GetPaths('.Bundle/', ['exts' => ['js', 'html']]),
                    App::$moduleManager->GetPaths('templates/', ['exts' => ['js', 'html']]),
                )
            );
            $jsa[] = $js;
            $files[] = $js;
        } else {
            // языки подключены

            $oldLangKey = $langModule->current;
            $langs = $langModule->Langs();
            foreach ($langs as $langKey => $langData) {
                if(!empty($selectedLangs) && !in_array($langKey, $selectedLangs)) {
                    continue;
                }
                
                $langModule->InitCurrent($langKey);

                $css = Bundle::Automate(
                    App::$domainKey,
                    ($langKey . '.') . ($themeKey ? $themeKey . '.' : '') . 'assets.css',
                    'scss',
                    array_merge(
                        [['path' => App::$appRoot . 'vendor/colibri/ui/src/']],
                        [['path' => $themeFile]],
                        [['path' => App::$webRoot . 'res/css/']],
                        App::$moduleManager->GetPathsFromModuleConfig(),
                        App::$moduleManager->GetPaths('web/res/css/'),
                        App::$moduleManager->GetPaths('.Bundle/'),
                        App::$moduleManager->GetPaths('templates/'),
                    )
                );
                $files[] = $css; 
                $cssa[] = $css;
                $js = Bundle::Automate(
                    App::$domainKey,
                    ($langKey . '.') . 'assets.js',
                    'js',
                    array_merge(
                        [['path' => App::$appRoot . 'vendor/colibri/ui/src/', 'exts' => ['js', 'html']]],
                        App::$moduleManager->GetPathsFromModuleConfig(['exts' => ['js', 'html']]),
                        App::$moduleManager->GetPaths('.Bundle/', ['exts' => ['js', 'html']]),
                        App::$moduleManager->GetPaths('templates/', ['exts' => ['js', 'html']]),
                    )
                );
                $files[] = $js;
                $jsa[] = $js;

            }
            $langModule->InitCurrent($oldLangKey);
        }

        $serviceWorkerCacheFiles = '\'' . implode("',\n\t'", $files) . '\'';
        if(File::Exists(App::$webRoot . 'service-worker.js')) {
            $content = File::Read(App::$webRoot . 'service-worker.js');
            $content = str_replace('\'[[cache]]\'', $serviceWorkerCacheFiles, $content);
            File::Write(App::$webRoot . 'service-worker.js', $content);
        }

        $cachePath = App::$webRoot . 'res/';
        $externalPaths = App::$moduleManager->GetExternalScriptsFromModuleConfig();
        foreach($externalPaths as $externalPath => $folder) {
            $pathInfo = Directory::PathInfo($externalPath);
            $resultFileName = $pathInfo['basename'];
            $response = Request::Get($externalPath);
            if($response->status === 200) {
                File::Write((string)$cachePath . $folder . '/' . $resultFileName, $response->data, true, '777');
            }
        }

        if (!App::$request->server->{'commandline'}) {
            return $this->Finish(
                200,
                'Bundle created successfuly',
                (object) [
                    'js' => $jsa,
                    'css' => $cssa
                ],
                'utf-8'
            );
            //throw new AppException('This command is allowed only in commandline mode');
        }


        return $this->Finish(200, 'ok', []);

    }

}
