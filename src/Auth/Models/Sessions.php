<?php

namespace App\Modules\Auth\Models;

use Colibri\Data\DataAccessPoint;
use Colibri\Data\SqlClient\IDataReader;
use Colibri\Data\Storages\Storages;
use Colibri\Data\Storages\Storage;
use Colibri\Utils\Logs\Logger;
use Colibri\Data\Storages\Models\DataTable as BaseModelDataTable;
use App\Modules\Auth\Models\Session;
use Colibri\App;
use Colibri\Utils\Cache\Mem;
use Colibri\Data\Storages\Fields\DateTimeField;

/**
 * Таблица, представление данных в хранилище Сессии
 * @author <author name and email>
 * @package App\Modules\Auth\Models 
 * 
 * @method Session[] getIterator()
 * @method Session _createDataRowObject()
 * @method Session _read()
 * 
 */
class Sessions extends BaseModelDataTable
{

    /**
     * Конструктор
     * @param DataAccessPoint $point точка доступа
     * @param IDataReader|null $reader ридер
     * @param string|\Closure $returnAs возвращать в виде класса
     * @param Storage|null $storage хранилище
     * @return void 
     */
    public function __construct(DataAccessPoint $point, IDataReader $reader = null, string $returnAs = 'Session', Storage|null $storage = null)
    {
        parent::__construct($point, $reader, $returnAs, $storage);
    }


    /**
     * Создание модели по названию хранилища
     * @param int $page страница
     * @param int $pagesize размер страницы
     * @param string $filter строка фильтрации
     * @param string $order сортировка
     * @param array $params параметры к запросу
     * @return Sessions
     */
    static function LoadByFilter(int $page = -1, int $pagesize = 20, string $filter = null, string $order = null, array $params = [], bool $calculateAffected = true): ? Sessions
    {
        $storage = Storages::Instance()->Load('sessions', 'auth');
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params, $calculateAffected);
    }

    /**
     * Create table by any filters
     * @param int $page page
     * @param int $pagesize page size
     * @param ?array $filtersArray filters array|object
     * @param string $sortField sort field
     * @param string $sortOrder sort order, default asc
     * @return ?Sessions
     */
    public static function LoadBy(
        int $page = -1, 
        int $pagesize = 20, 
        ?string $searchTerm = null,
        ?array $filtersArray = null,
        ?string $sortField = null,
        string $sortOrder = 'asc'
    ) : ?Sessions
    {
        $storage = Storages::Instance()->Load('sessions', 'auth');
        [$filter, $order, $params] = $storage->accessPoint->ProcessFilters($storage, $searchTerm, $filtersArray, $sortField, $sortOrder);
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params);
    }

    /**
     * Загружает без фильтра
     * @param int $page страница
     * @param int $pagesize размер страницы
     * @return Sessions 
     */
    static function LoadAll(int $page = -1, int $pagesize = 20, bool $calculateAffected = false): ? Sessions
    {
        return self::LoadByFilter($page, $pagesize, null, null, [], $calculateAffected);
    }

    /**
     * Возвращает модель по ID
     * @param int $id ID строки
     * @return Session|null
     */
    static function LoadById(int $id): Session|null
    {
        $table = self::LoadByFilter(1, 1, '{id}=[[id:integer]]', null, ['id' => $id], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Возвращает модель по пользователю
     * @param Member|string $member ID строки
     * @return Sessions|null
     */
    static function LoadByMember(Member|string $member): Sessions|null
    {
        return self::LoadByFilter(1, 1, '{member}=[[member:string]]', null, ['member' => $member instanceof Member ? $member->token : $member], false);
    }

    /**
     * Возвращает модель по key
     * @param string $key key строки
     * @return Session|null
     */
    static function LoadByKey(string $key): Session|null
    {
        $table = self::LoadByFilter(1, 1, '{key}=[[key:string]]', null, ['key' => $key], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    static function LoadFromRequest(): ? Session
    {
        App::$monitoring->StartTimer('load-from-request');
        $jwt = App::$request->cookie->{'cc-jwt'} ?? App::$request->headers->{'authorization'};
        if (!$jwt) {
            $return = self::CreateGuestSession();
        }
        else {

            $key = md5($jwt);
    
            if ($session = self::LoadGuestSession($key)) {
                $return = $session;
            }
            else {
                
                // на всякий удаляем из памяти, чтобы не было переполнения
                Mem::Delete('sess' . $key);

                $session = self::LoadByKey($key);
                if (!$session) {
                    $session = self::CreateGuestSession();
                }
        
                $return = $session;
            }
    
        }
        App::$monitoring->EndTimer('load-from-request');
        return $return;


    }

    static function CreateGuestSession(): Session
    {
        $session = self::LoadEmpty();
        $session->expires = 3600;
        $session->member = null;
        $session->datecreated = new DateTimeField('now');
        $session->Save(true);
        return $session;
    }

    static function LoadGuestSession(string $key): ? Session
    {
        if (!Mem::Exists('sess' . $key)) {
            return null;
        }
        return self::LoadEmpty(Mem::Read('sess' . $key));
    }

    /**
     * Создание модели по названию хранилища
     * @return Session
     */
    static function LoadEmpty(object|array $data = []): Session
    {
        $table = self::LoadByFilter(-1, 20, 'false', null, [], false);
        return $table->CreateEmptyRow($data);
    }

    /**
     * Удаляет все по списку ID
     * @param int[] $ids ID строки
     * @return bool
     */
    static function DeleteAllByIds(array $ids): bool
    {
        return self::DeleteAllByFilter('{id} in (' . implode(',', $ids) . ')');
    }

    /**
     * Удаляет все по фильтру
     * @param string $filter фильтр, допускается использование элементов вида {field}
     * @return bool
     */
    static function DeleteAllByFilter(string $filter): bool
    {
        $storage = Storages::Instance()->Load('sessions');
        return self::DeleteByFilter($storage, $filter);

    }

    static function DataMigrate(? Logger $logger = null): bool
    {
        // миграция данных
        return true;
    }

}