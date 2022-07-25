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

/**
 * Таблица, представление данных в хранилище #{auth-storages-sessions-desc;Сессии}
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * @method Session[] getIterator()
 * @method Session _createDataRowObject()
 * @method Session _read()
 * 
 */
class Sessions extends BaseModelDataTable {

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
    static function LoadByFilter(int $page = -1, int $pagesize = 20, string $filter = null, string $order = null, array $params = [], bool $calculateAffected = true) : ?Sessions
    {
        $storage = Storages::Create()->Load('sessions');
        $additionalParams = ['page' => $page, 'pagesize' => $pagesize, 'params' => $params];
        $additionalParams['type'] = $calculateAffected ? DataAccessPoint::QueryTypeReader : DataAccessPoint::QueryTypeBigData;
        return self::LoadByQuery(
            $storage,
            'select * from ' . $storage->name . 
                ($filter ? ' where ' . $filter : '') . 
                ($order ? ' order by ' . $order : ''), 
            $additionalParams
        );
    }

    /**
     * Загружает без фильтра
     * @param int $page страница
     * @param int $pagesize размер страницы
     * @return Sessions 
     */
    static function LoadAll(int $page = -1, int $pagesize = 20, bool $calculateAffected = false) : ?Sessions
    {
        return self::LoadByFilter($page, $pagesize, null, null, [], $calculateAffected);
    }

    /**
     * Возвращает модель по ID
     * @param int $id ID строки
     * @return Session|null
     */
    static function LoadById(int $id) : Session|null 
    {
        $table = self::LoadByFilter(1, 1, '{id}=[[id:integer]]', null, ['id' => $id], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Возвращает модель по key
     * @param string $key key строки
     * @return Session|null
     */
    static function LoadByKey(string $key) : Session|null 
    {
        $table = self::LoadByFilter(1, 1, '{key}=[[key:string]]', null, ['key' => $key], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    static function LoadFromRequest(): ?Session
    {
     
        $jwt = App::$request->cookie->{'cc-jwt'};
        if(!$jwt) {
            return self::CreateGuestSession();
        }

        $key = md5($jwt);

        if($session = self::LoadGuestSession($key)) {
            return $session;
        }

        return self::LoadByKey($key);
        
    }

    static function CreateGuestSession(): Session
    {
        $session = self::LoadEmpty();
        $session->GenerateToken();
        Mem::Write('sess'.$session->key, $session->ToArray(true));
        return $session;
    }

    static function LoadGuestSession(string $key): ?Session
    {
        if(!Mem::Exists('sess'.$key)) {
            return null;
        }
        $sessionData = Mem::Read('sess'.$key);
        $session = self::LoadEmpty();
        foreach($sessionData as $k => $v) {
            $session->$k = $v;
        }
        return $session;
    }

    /**
     * Создание модели по названию хранилища
     * @return Session
     */
    static function LoadEmpty() : Session
    {
        $table = self::LoadByFilter(-1, 20, 'false', null, [], false);
        return $table->CreateEmptyRow();
    }

    /**
     * Удаляет все по списку ID
     * @param int[] $ids ID строки
     * @return bool
     */
    static function DeleteAllByIds(array $ids): bool
    {
        return self::DeleteAllByFilter('{id} in ('.implode(',', $ids).')');
    }

    /**
     * Удаляет все по фильтру
     * @param string $filter фильтр, допускается использование элементов вида {field}
     * @return bool
     */
    static function DeleteAllByFilter(string $filter): bool
    {
        return self::DeleteByFilter('sessions', $filter);
    }

    static function DataMigrate(?Logger $logger = null): bool
    {
        // миграция данных
        return true;
    }

}