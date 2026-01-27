<?php

namespace App\Modules\Auth\Models;

use Colibri\Data\DataAccessPoint;
use Colibri\Data\SqlClient\IDataReader;
use Colibri\Data\Storages\Storages;
use Colibri\Data\Storages\Storage;
use Colibri\Utils\Logs\Logger;
use Colibri\Data\Storages\Models\DataTable as BaseModelDataTable;
use App\Modules\Auth\Models\Confirmation;

/**
 * Таблица, представление данных в хранилище Коды верификации
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * @method Confirmation[] getIterator()
 * @method Confirmation _createDataRowObject()
 * @method Confirmation _read()
 * 
 */
class Confirmations extends BaseModelDataTable
{

    /**
     * Конструктор
     * @param DataAccessPoint $point точка доступа
     * @param IDataReader|null $reader ридер
     * @param string|\Closure $returnAs возвращать в виде класса
     * @param Storage|null $storage хранилище
     * @return void 
     */
    public function __construct(DataAccessPoint $point, ?IDataReader $reader = null, string $returnAs = 'Confirmation', Storage|null $storage = null)
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
     * @return Confirmations
     */
    static function LoadByFilter(int $page = -1, int $pagesize = 20, ?string $filter = null, ?string $order = null, array $params = [], bool $calculateAffected = true): ? Confirmations
    {
        $storage = Storages::Instance()->Load('confirmations', 'auth');
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params, $calculateAffected);
    }

    /**
     * Create table by any filters
     * @param int $page page
     * @param int $pagesize page size
     * @param ?array $filtersArray filters array|object
     * @param string $sortField sort field
     * @param string $sortOrder sort order, default asc
     * @return ?Confirmations
     */
    public static function LoadBy(
        int $page = -1, 
        int $pagesize = 20, 
        ?string $searchTerm = null,
        ?array $filtersArray = null,
        ?string $sortField = null,
        string $sortOrder = 'asc'
    ) : ?Confirmations
    {
        $storage = Storages::Instance()->Load('confirmations', 'auth');
        [$filter, $order, $params] = $storage->accessPoint->ProcessFilters($storage, $searchTerm, $filtersArray, $sortField, $sortOrder);
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params);
    }

    /**
     * Загружает без фильтра
     * @param int $page страница
     * @param int $pagesize размер страницы
     * @return Confirmations 
     */
    static function LoadAll(int $page = -1, int $pagesize = 20, bool $calculateAffected = false): ? Confirmations
    {
        return self::LoadByFilter($page, $pagesize, null, null, [], $calculateAffected);
    }

    /**
     * Возвращает модель по ID
     * @param int $id ID строки
     * @return Confirmation|null
     */
    static function LoadById(int $id): Confirmation|null
    {
        $table = self::LoadByFilter(1, 1, '{id}=[[id:integer]]', null, ['id' => $id], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Возвращает модель по пользователю
     * @param string $property что подтверждаем
     * @param string $member пользователь
     * @return Confirmation|null
     */
    static function LoadByMember(string $property, string $member): Confirmation|null
    {
        $table = self::LoadByFilter(1, 1, '{property}=[[property:string]] and {member}=[[member:string]]', null, ['property' => $property, 'member' => $member], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Возвращает модель по значению
     * @param string $property что подтверждаем
     * @param string $member пользователь
     * @return Confirmation|null
     */
    static function LoadByValue(string $property, string $value): Confirmation|null
    {
        $table = self::LoadByFilter(1, 1, '{property}=[[property:string]] and {value}=[[value:string]]', null, ['property' => $property, 'value' => $value], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Создание модели по названию хранилища
     * @return Confirmation
     */
    static function LoadEmpty(): Confirmation
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
        return self::DeleteAllByFilter('{id} in (' . implode(',', $ids) . ')');
    }

    /**
     * Удаляет все по фильтру
     * @param string $filter фильтр, допускается использование элементов вида {field}
     * @return bool
     */
    static function DeleteAllByFilter(string $filter): bool
    {
        $storage = Storages::Instance()->Load('confirmations');
        return self::DeleteByFilter($storage, $filter);

    }

    static function DataMigrate(? Logger $logger = null): bool
    {
        // миграция данных
        return true;
    }

}