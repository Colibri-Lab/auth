<?php

namespace App\Modules\Auth\Models;

use Colibri\Data\DataAccessPoint;
use Colibri\Data\SqlClient\IDataReader;
use Colibri\Data\Storages\Storages;
use Colibri\Data\Storages\Storage;
use Colibri\Utils\Logs\Logger;
use Colibri\Data\SqlClient\QueryInfo;
use Colibri\Data\Storages\Models\DataTable as BaseModelDataTable;
use App\Modules\Auth\Models\AutoLogin;
use Colibri\Common\RandomizationHelper;

/**
 * Table class of Запрос на автовход storage
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * @method AutoLogin[] getIterator()
 * @method AutoLogin _createDataRowObject()
 * @method AutoLogin _read()
 * @method AutoLogin offsetGet(mixed $offset)
 * 
 */
class AutoLogins extends BaseModelDataTable 
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
        IDataReader $reader = null, 
        string $returnAs = 'AutoLogin', 
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
     * @return AutoLogins
     */
    public static function LoadByFilter(
        int $page = -1, 
        int $pagesize = 20, 
        string $filter = null, 
        string $order = null, 
        array $params = [], 
        bool $calculateAffected = true
    ) : ?AutoLogins
    {
        $storage = Storages::Create()->Load('autologin', 'auth');
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params, $calculateAffected);
    }

    /**
     * Create table by any filters
     * @param int $page page
     * @param int $pagesize page size
     * @param ?array $filtersArray filters array|object
     * @param string $sortField sort field
     * @param string $sortOrder sort order, default asc
     * @return ?AutoLogins
     */
    public static function LoadBy(
        int $page = -1, 
        int $pagesize = 20, 
        ?string $searchTerm = null,
        ?array $filtersArray = null,
        ?string $sortField = null,
        string $sortOrder = 'asc'
    ) : ?AutoLogins
    {
        $storage = Storages::Create()->Load('autologin', 'auth');
        [$filter, $order, $params] = $storage->accessPoint->ProcessFilters($storage, $searchTerm, $filtersArray, $sortField, $sortOrder);
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params);
    }

    /**
     * Loads all rows from table
     * @param int $page page
     * @param int $pagesize page size
     * @return AutoLogins 
     */
    public static function LoadAll(
        int $page = -1, 
        int $pagesize = 20, 
        bool $calculateAffected = false
    ) : ?AutoLogins
    {
        return self::LoadByFilter($page, $pagesize, null, null, [], $calculateAffected);
    }

    /**
     * Returns row model by ID
     * @param int $id ID of the row to fetch
     * @return AutoLogin|null
     */
    public static function LoadById(int $id) : AutoLogin|null 
    {
        $table = self::LoadByFilter(1, 1, '{id}=[[id:integer]]', null, ['id' => $id], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Returns row model by ID
     * @param string $code code of the row to fetch
     * @return AutoLogin|null
     */
    public static function LoadByCode(string $code) : AutoLogin|null 
    {
        $table = self::LoadByFilter(1, 1, '{code}=[[code:string]]', null, ['code' => $code], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Returns row model by token
     * @param Application $app Application object
     * @param Member $member Member object
     * @return AutoLogin|null
     */
    public static function LoadByMember(Application $app, Member $member) : AutoLogin|null 
    {
        $table = self::LoadByFilter(1, 1, '{application}=[[app:string]] and {token}=[[token:string]]', null, ['app' => $app->token, 'token' => $member->token], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Returns an empty row object
     * @return AutoLogin
     */
    public static function LoadEmpty() : AutoLogin
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
        $storage = Storages::Create()->Load('autologin', 'auth');
        return self::RestoreByFilter($storage, '{id} in ('.implode(',', $ids).')');
    }

    /**
     * Deletes a rows by filter
     * @param string $filter filter string, {field} form can be used
     * @return bool
     */
    public static function DeleteAllByFilter(string $filter): bool
    {
        $storage = Storages::Create()->Load('autologin', 'auth');
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

    public static function CreateForMember(Application $app, Member $member, string $returnTo): AutoLogin
    {
        $row = self::LoadByMember($app, $member);
        if(!$row) {
            $row = self::LoadEmpty();
            $row->application = $app->token;
            $row->token = $member->token;
        }
        $row->return_to = $returnTo;
        $row->code = RandomizationHelper::Mixed(256);
        $row->Save(true);
        return $row;
    }

}