<?php
 
 
namespace App\Modules\Auth;
 
class Installer
{

    private static function _loadConfig($file): ?array
    {
        return yaml_parse_file($file);
    }

    private static function _saveConfig($file, $config): void
    {
        yaml_emit_file($file, $config, \YAML_UTF8_ENCODING, \YAML_ANY_BREAK);
    }

    private static function _getMode($file): string
    {
        $appConfig = self::_loadConfig($file);
        return $appConfig['mode'];
    }

    private static function _injectIntoModuleConfig($file): void
    {

        $modules = self::_loadConfig($file);
        if(is_array($modules['entries'])) {
            foreach($modules['entries'] as $entry) {
                if($entry['name'] === 'Auth') {
                    return;
                }
            }
        }
        else {
            $modules['entries'] = [];
        }

        $modules['entries'] = array_merge($modules['entries'], [[
            'name' => 'Auth',
            'entry' => '\Auth\Module',
            'desc' => 'Система авторизации Colibri',
            'enabled' => true,
            'visible' => true,
            'for' => ['manage', 'auth'],
            'config' => 'include(/config/auth.yaml)'
        ]]);

        self::_saveConfig($file, $modules);

    }

    private static function _injrectIntoDomains($file, $mode): void
    {
        $hosts = self::_loadConfig($file);
        if(isset($hosts['domains']['auth'])) {
            return;
        }

        if($mode === 'local') {
            $hosts['domains']['auth'] = ['*_auth-v5.local.bsft.loc'];
        }
        else if($mode === 'test') {
            $hosts['domains']['manage'] = array_merge($hosts['domains']['manage'], ['backend.auth.test.colibrilab.ru']);
            $hosts['domains']['auth'] = ['*.auth.test.colibrilab.ru'];
        }
        else if($mode === 'prod') {
            // захватываем управление админкой
            // управляющий модуль должен быть один
            $hosts['domains']['manage'] = array_merge($hosts['domains']['manage'], ['backend.auth.ecolo-place.com']);
            $hosts['domains']['auth'] = ['*.auth.ecolo-place.com'];
        }
        self::_saveConfig($file, $hosts);
        
    }

    private static function _copyOrSymlink($mode, $pathFrom, $pathTo, $fileFrom, $fileTo): void 
    {
        print_r('Копируем '.$mode.' '.$pathFrom.' '.$pathTo.' '.$fileFrom.' '.$fileTo."\n");
        if(!file_exists($pathFrom.$fileFrom)) {
            print_r('Файл '.$pathFrom.$fileFrom.' не существует'."\n");
            return;
        }

        if(file_exists($pathTo.$fileTo)) {
            print_r('Файл '.$pathTo.$fileTo.' существует'."\n");
            return;
        }

        if($mode === 'local') {
            shell_exec('ln -s '.realpath($pathFrom.$fileFrom).' '.$pathTo.($fileTo != $fileFrom ? $fileTo : ''));
        }
        else {
            shell_exec('cp -R '.realpath($pathFrom.$fileFrom).' '.$pathTo.$fileTo);
        }

        // если это исполняемый скрипт
        if(strstr($pathTo.$fileTo, '/bin/') !== false) {
            chmod($pathTo.$fileTo, 0777);
        }
    }

    private static function _findStoragesConfigFiles($configDir): array
    {
        $storagesConfigs = [];
        $files = scandir($configDir);
        foreach($files as $file) {
            if(!in_array($file, ['databases.yaml', '.', '..'])) {
                // Ищем databases.storages
                $config = self::_loadConfig($configDir.$file);
                if(isset($config['databases']['storages'])) {
                    $storagesConfigs[] = str_replace(')', '', str_replace('include(', '', $config['databases']['storages']));
                }
            }
        }
        return $storagesConfigs;
    }

    private static function _updateDatabaseConnection(string $configDir, string $mode): void
    {

        $databases = self::_loadConfig($configDir.'databases.yaml');
        // обновляем данные основного подключения
        $databases['access-points']['connections']['default_connection']['host'] = 'localhost';
        $databases['access-points']['connections']['default_connection']['user'] = 'lotteryhub';
        if($mode === 'prod') {
            $databases['access-points']['connections']['default_connection']['password'] = 'vault(vault.repeatme.online:ef97938ae449337d2644daf48c01e336:auth_db_password)';
        }
        else {
            $databases['access-points']['connections']['default_connection']['password'] = '123456';
        }
        $databases['access-points']['points']['main']['database'] = 'auth';
        
        self::_saveConfig($configDir.'databases.yaml', $databases);
        $storagesConfigs = self::_findStoragesConfigFiles($configDir);
        foreach($storagesConfigs as $config) {
            $configData = self::_loadConfig($configDir.$config);
            foreach($configData as $storageName => $storageData) {
                $configData[$storageName]['access-point'] = 'main';
            }
            self::_saveConfig($configDir.$config, $configData);
        }

    }

    private static function _injectDefaultSettings($file, $mode): void
    {

        $settings = self::_loadConfig($file);

        // следить за темой системы
        if(!isset($settings['screen']['theme'])) {
            $settings['screen'] = ['theme' => 'follow-device'];
        }
        if(!isset($settings['errors']['404'])) {
            $settings['errors'] = ['404' => '/e404/', '500' => '/e500/', '0' => '/e404'];
        }
        self::_saveConfig($file, $settings);

    }

    private static function _injectCometSettings($file): void
    {

        $settings = self::_loadConfig($file);

        $settings['host'] = 'comet.colibrilab.ru';
        $settings['port'] = '3005';

        self::_saveConfig($file, $settings);

    }

 
    /**
     *
     * @param PackageEvent $event
     * @return void
     */
    public static function PostPackageInstall($event)
    {
 
        print_r('Установка и настройка модуля Colibri Auth'."\n");
 
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir').'/';
        $operation = $event->getOperation();
        $installedPackage = $operation->getPackage();
        $targetDir = $installedPackage->getName();
        $path = $vendorDir.$targetDir;

        $configPath = $path.'/src/Auth/config-template/';
        $configDir = './config/';
 
        if(!file_exists($configDir.'app.yaml')) {
            print_r('Не найден файл конфигурации app.yaml'."\n");
            return;
        }

        // берем точку входа
        $webRoot = \getenv('COLIBRI_WEBROOT');
        if(!$webRoot) {
            $webRoot = 'web'; 
        }
        $mode = self::_getMode($configDir.'app.yaml'); 
 
        // копируем конфиг
        print_r('Копируем файлы конфигурации'."\n");
        self::_copyOrSymlink($mode, $configPath, $configDir, 'module-'.$mode.'.yaml', 'auth.yaml');
        self::_copyOrSymlink($mode, $configPath, $configDir, 'auth-storages.yaml', 'auth-storages.yaml');
        self::_copyOrSymlink($mode, $configPath, $configDir, 'auth-langtexts.yaml', 'auth-langtexts.yaml');
        
        print_r('Встраиваем модуль'."\n");
        self::_injectIntoModuleConfig($configDir.'modules.yaml');
        self::_injrectIntoDomains($configDir.'hosts.yaml', $mode);
        self::_injectDefaultSettings($configDir.'settings.yaml', $mode);
        self::_injectCometSettings($configDir.'comet.yaml', $mode);

        if($mode !== 'local') {
            print_r('Обновляем доступы к базе данных'."\n");
            self::_updateDatabaseConnection($configDir, $mode);
        }

        print_r('Установка скриптов'."\n");
        self::_copyOrSymlink($mode, $path.'/src/Auth/bin/', './bin/', 'auth-migrate.sh', 'auth-migrate.sh');
        self::_copyOrSymlink($mode, $path.'/src/Auth/bin/', './bin/', 'auth-models-generate.sh', 'auth-models-generate.sh');

        print_r('Установка завершена'."\n");
 
    }
}