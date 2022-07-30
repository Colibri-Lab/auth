<?php



/**
 * Search
 *
 * @author Author Name <author.name@action-media.ru>
 * @copyright 2019 Colibri
 * @package App\Modules\Auth
 */
namespace App\Modules\Auth;


use Colibri\Modules\Module as BaseModule;
use Colibri\Utils\Menu\Item;
use Colibri\Events\EventsContainer;
use Colibri\App;
use App\Modules\Auth\Models\LogTable;
use App\Modules\Auth\Models\Customer;
use App\Modules\Auth\Models\Customers;
use Colibri\Utils\Logs\Logger;
use App\Modules\Auth\Models\Histories;
use App\Modules\Auth\Models\Lotteries;
use App\Modules\Auth\Models\Sources;
use App\Modules\Auth\Models\Applications;
use App\Modules\Auth\Models\Application;
use Colibri\Utils\Debug;
use App\Modules\Auth\Controllers\SessionController;
use App\Modules\Auth\Controllers\MemberController;
use App\Modules\Auth\Controllers\AppController;


/**
 * Описание модуля
 * @package App\Modules\Auth
 *
 * @property-read Application $application
 *
 */
class Module extends BaseModule
{

    /**
     * Синглтон
     *
     * @var Module
     */
    public static ?Module $instance = null;

    private ?Application $_app = null;

    const NeedAuthorization = [
        SessionController::class,
        MemberController::class,
        AppController::class
    ];

    /**
     * Инициализация модуля
     * @return void
     */
    public function InitializeModule(): void
    {
        self::$instance = $this;

        App::$instance->HandleEvent(EventsContainer::RpcGotRequest, function($event, $args) {
            if(isset($args->class) && in_array(trim($args->class, '\\'), self::NeedAuthorization)) {
                if(App::$request->server->request_method === 'OPTIONS') {
                    App::$response->Origin();
                    App::$response->Close(200, 'ok');
                    exit;
                }

                if(!Module::$instance->LoadApplication()) {
                    $args->cancel = true;
                    $args->result = (object)[
                        'code' => 403,
                        'message' => 'Unauthorized',
                        'result' => []
                    ];
                }
            }
        });

        // @No Code
        // App::$instance->HandleEvent([EventsContainer::RpcRequestProcessed, EventsContainer::RpcRequestError], function($event, $args) {
        //     if(isset($args->class) && strstr($args->class, '\\Auth') !== false) {
        //         $customerKey = App::$request->headers->customer;
        //         if($customerKey && ($customer = Customers::LoadByKey($customerKey))) {
        //             $emptyLog = LogTable::LoadEmpty();
        //             $emptyLog->customer = $customer;
        //             $emptyLog->module = 'Auth';
        //             $emptyLog->controller = $args->class;
        //             $emptyLog->method = $args->method;
        //             $emptyLog->params = ['get' => $args->get->ToArray(), 'post' => $args->post->ToArray(), 'payload' => $args->payload->ToArray()];
        //             $emptyLog->results = $args->result;
        //             $emptyLog->Save();
        //         }
        //     }
        // });

    }

    /**
     * Вызывается для получения Меню болванкой
     */
    public function GetTopmostMenu(bool $hideExecuteCommand = true): Item|array
    {
        return [
           
        ];

    }

    public function GetPermissions(): array
    {
        $permissions = parent::GetPermissions();
        $permissions['auth'] = 'Доступ к модулю Auth';
        return $permissions;
    }

    public function Backup(Logger $logger, string $path) {
        // Do nothing   
        
        $modulePath = $path . 'modules/Auth/';

        // $logger->debug('Exporting Sources...');
        // $table = Sources::LoadAll();
        // $table->ExportJson($modulePath . 'sources.json');


    }
    

    /**
     * Загрижает текущее приложение из запроса
     */
    public function LoadApplication(): bool
    {
        $this->_app = Applications::LoadFromRequest();
        if(!$this->_app) {
            return false;
        }
        return true;
    }

    public function __get(string $prop): mixed
    {
        if(strtolower($prop) == 'application') {
            return $this->_app;
        }
        else {
            return parent::__get($prop); 
        }
    }

}