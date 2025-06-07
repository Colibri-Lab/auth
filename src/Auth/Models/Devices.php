<?php

namespace App\Modules\Auth\Models;

use Colibri\Data\DataAccessPoint;
use Colibri\Data\SqlClient\IDataReader;
use Colibri\Data\Storages\Storages;
use Colibri\Data\Storages\Storage;
use Colibri\Utils\Logs\Logger;
use Colibri\Data\SqlClient\QueryInfo;
use Colibri\Data\Storages\Models\DataTable as BaseModelDataTable;
use App\Modules\Auth\Models\Device;

/**
 * Table class of Аутентификация по биометрии storage
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * @method Device[] getIterator()
 * @method Device _createDataRowObject()
 * @method Device _read()
 * @method Device offsetGet(mixed $offset)
 * 
 */
class Devices extends BaseModelDataTable 
{

    /**
     * Constructor
     * @param DataAccessPoint $point data access point
     * @param IDataReader|null $reader sql reader
     * @param string|\Closure $returnAs return as this class
     * @param Storage|null $storage storage object
     * @return void 
     */
    public function __construct(
        DataAccessPoint $point, 
        ?IDataReader $reader = null, 
        string $returnAs = 'Device', 
        Storage|null $storage = null
    )
    {
        parent::__construct($point, $reader, $returnAs, $storage);
    }

    
    /**
     * Create table by filters and sort
     * @param int $page page
     * @param int $pagesize page size
     * @param string $filter filters string
     * @param string $order sort order
     * @param array $params params
     * @param bool $calculateAffected if needed to return affected, default true
     * @return Devices
     */
    public static function LoadByFilter(
        int $page = -1, 
        int $pagesize = 20, 
        ?string $filter = null, 
        ?string $order = null, 
        array $params = [], 
        bool $calculateAffected = true
    ) : ?Devices
    {
        $storage = Storages::Instance()->Load('devices', 'auth');
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params, $calculateAffected);
    }

    /**
     * Create table by any filters
     * @param int $page page
     * @param int $pagesize page size
     * @param ?array $filtersArray filters array|object
     * @param string $sortField sort field
     * @param string $sortOrder sort order, default asc
     * @param bool $calculateAffected if needed to return affected, default true
     * @return ?Devices
     */
    public static function LoadBy(
        int $page = -1, 
        int $pagesize = 20, 
        ?string $searchTerm = null,
        ?array $filtersArray = null,
        ?string $sortField = null,
        string $sortOrder = 'asc', 
        bool $calculateAffected = true
    ) : ?Devices
    {
        $storage = Storages::Instance()->Load('devices', 'auth');
        [$filter, $order, $params] = $storage->accessPoint->ProcessFilters($storage, $searchTerm, $filtersArray, $sortField, $sortOrder);
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params, $calculateAffected);
    }

    /**
     * Loads all rows from table
     * @param int $page page
     * @param int $pagesize page size
     * @return Devices 
     */
    public static function LoadAll(
        int $page = -1, 
        int $pagesize = 20, 
        bool $calculateAffected = false
    ) : ?Devices
    {
        return self::LoadByFilter($page, $pagesize, null, null, [], $calculateAffected);
    }

    /**
     * Returns row model by ID
     * @param int $id ID of the row to fetch
     * @return Device|null
     */
    public static function LoadById(int $id) : Device|null 
    {
        $table = self::LoadByFilter(1, 1, '{id}=[[id:integer]]', null, ['id' => $id], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Returns row model by ID
     * @param string $rawId 
     * @param string $deviceId
     * @return Device|null
     */
    public static function LoadByCreds(string $rawId, ?string $deviceId = null) : Device|null 
    {
        $table = self::LoadByFilter(
            1, 1, 
            '{rawid}=[[rawid:string]]'.($deviceId ? ' and {device}=[[device:string]]' : ''), 
            null, [
                'rawid' => $rawId,
                'device' => $deviceId ?? null, 
        ], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Returns an empty row object
     * @return Device
     */
    public static function LoadEmpty() : Device
    {
        $table = self::LoadByFilter(-1, 20, '{id}=0', null, [], false);
        return $table->CreateEmptyRow();
    }

    /**
     * Deletes a rows by array of ID
     * @param int[] $ids ID array of ID
     * @return bool
     */
    public static function DeleteAllByIds(array $ids): bool
    {
        return self::DeleteAllByFilter('{id} in ('.implode(',', $ids).')');
    }

    /**
     * Restores a rows by array of ID, works only in softdelete mode
     * @param int[] $ids array of ID
     * @return bool
     */
    public static function RestoreAllByIds(array $ids): bool
    {
        $storage = Storages::Instance()->Load('devices', 'auth');
        return self::RestoreByFilter($storage, '{id} in ('.implode(',', $ids).')');
    }

    /**
     * Deletes a rows by filter
     * @param string $filter filter string, {field} form can be used
     * @return bool
     */
    public static function DeleteAllByFilter(string $filter): bool
    {
        $storage = Storages::Instance()->Load('devices', 'auth');
        return self::DeleteByFilter($storage, $filter);
    }

    /**
     * Migrates an object
     * @param ?Logger $logger
     */
    public static function DataMigrate(?Logger $logger = null): bool
    {
        return true;
    }

}