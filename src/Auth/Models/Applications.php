<?php

namespace App\Modules\Auth\Models;

use Colibri\Data\DataAccessPoint;
use Colibri\Data\SqlClient\IDataReader;
use Colibri\Data\Storages\Storages;
use Colibri\Data\Storages\Storage;
use Colibri\Utils\Logs\Logger;
use Colibri\Data\Storages\Models\DataTable as BaseModelDataTable;
use App\Modules\Auth\Models\Application;
use Colibri\App;

/**
 * Таблица, представление данных в хранилище #{auth-storages-applications-desc;Приложения}
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * @method Application[] getIterator()
 * @method Application _createDataRowObject()
 * @method Application _read()
 * 
 */
class Applications extends BaseModelDataTable {

    /**
     * Конструктор
     * @param DataAccessPoint $point точка доступа
     * @param IDataReader|null $reader ридер
     * @param string|\Closure $returnAs возвращать в виде класса
     * @param Storage|null $storage хранилище
     * @return void 
     */
    public function __construct(DataAccessPoint $point, IDataReader $reader = null, string $returnAs = 'Application', Storage|null $storage = null)
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
     * @return Applications
     */
    static function LoadByFilter(int $page = -1, int $pagesize = 20, string $filter = null, string $order = null, array $params = [], bool $calculateAffected = true) : ?Applications
    {
        $storage = Storages::Create()->Load('applications');
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
     * @return Applications 
     */
    static function LoadAll(int $page = -1, int $pagesize = 20, bool $calculateAffected = false) : ?Applications
    {
        return self::LoadByFilter($page, $pagesize, null, null, [], $calculateAffected);
    }

    /**
     * Возвращает модель по ID
     * @param int $id ID строки
     * @return Application|null
     */
    static function LoadById(int $id) : Application|null 
    {
        $table = self::LoadByFilter(1, 1, '{id}=[[id:integer]]', null, ['id' => $id], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Берем приложение из заголовков запроса
     */
    static function LoadFromRequest(): ?Application
    {
        $appName = App::$request->headers->{'X-AppName'};
        $appToken = App::$request->headers->{'X-AppToken'};
        $table = self::LoadByFilter(1, 1, '{key}=[[name:string]] and {token}=[[token:string]]', '', ['name' => $appName, 'token' => $appToken]);
        return $table->Count() > 0 ? $table->First() : null;
    }


    /**
     * Создание модели по названию хранилища
     * @return Application
     */
    static function LoadEmpty() : Application
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
        return self::DeleteByFilter('applications', $filter);
    }

    static function DataMigrate(?Logger $logger = null): bool
    {
        // миграция данных
        return true;
    }

}