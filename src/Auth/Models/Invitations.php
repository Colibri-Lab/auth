<?php

namespace App\Modules\Auth\Models;

use Colibri\Data\DataAccessPoint;
use Colibri\Data\SqlClient\IDataReader;
use Colibri\Data\Storages\Storages;
use Colibri\Data\Storages\Storage;
use Colibri\Utils\Logs\Logger;
use Colibri\Data\SqlClient\QueryInfo;
use Colibri\Data\Storages\Models\DataTable as BaseModelDataTable;
use App\Modules\Auth\Models\Invitation;
use App\Modules\Auth\Module;
use Colibri\Common\RandomizationHelper;
use Colibri\Data\Storages\Fields\DateTimeField;

/**
 * Table class of Приглашения storage
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * @method Invitation[] getIterator()
 * @method Invitation _createDataRowObject()
 * @method Invitation _read()
 * @method Invitation offsetGet(mixed $offset)
 * 
 */
class Invitations extends BaseModelDataTable 
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
        string $returnAs = 'Invitation', 
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
     * @return Invitations
     */
    public static function LoadByFilter(
        int $page = -1, 
        int $pagesize = 20, 
        ?string $filter = null, 
        ?string $order = null, 
        array $params = [], 
        bool $calculateAffected = true
    ) : ?Invitations
    {
        $storage = Storages::Instance()->Load('invites', 'auth');
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params, $calculateAffected);
    }

    /**
     * Create table by any filters
     * @param int $page page
     * @param int $pagesize page size
     * @param ?array $filtersArray filters array|object
     * @param string $sortField sort field
     * @param string $sortOrder sort order, default asc
     * @return ?Invitations
     */
    public static function LoadBy(
        int $page = -1, 
        int $pagesize = 20, 
        ?string $searchTerm = null,
        ?array $filtersArray = null,
        ?string $sortField = null,
        string $sortOrder = 'asc'
    ) : ?Invitations
    {
        $storage = Storages::Instance()->Load('invites', 'auth');
        [$filter, $order, $params] = $storage->accessPoint->ProcessFilters($storage, $searchTerm, $filtersArray, $sortField, $sortOrder);
        return parent::_loadByFilter($storage, $page, $pagesize, $filter, $order, $params);
    }

    /**
     * Loads all rows from table
     * @param int $page page
     * @param int $pagesize page size
     * @return Invitations 
     */
    public static function LoadAll(
        int $page = -1, 
        int $pagesize = 20, 
        bool $calculateAffected = false
    ) : ?Invitations
    {
        return self::LoadByFilter($page, $pagesize, null, null, [], $calculateAffected);
    }

    /**
     * Returns row model by ID
     * @param int $id ID of the row to fetch
     * @return Invitation|null
     */
    public static function LoadById(int $id) : Invitation|null 
    {
        $table = self::LoadByFilter(1, 1, '{id}=[[id:integer]]', null, ['id' => $id], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Returns row model by ID
     * @param int $id ID of the row to fetch
     * @return Invitation|null
     */
    public static function LoadByCode(string $code) : Invitation|null 
    {
        $table = self::LoadByFilter(1, 1, '{code}=[[code:string]]', null, ['code' => $code], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Returns row model by email and phone
     * @param ?string $email email
     * @param ?string $phone phone
     * @return Invitation|null
     */
    public static function LoadByEmail(?string $email) : Invitation|null 
    {
        if(!$email) {
            return null;
        }

        $filter = [];
        $params = [];
        if($email) {
            $filter[] = '{email}=[[email:string]]';
            $params['email'] = $email;
        }   
        

        $table = self::LoadByFilter(1, 1, implode(' and ', $filter), null, $params, false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Returns an empty row object
     * @return Invitation
     */
    public static function LoadEmpty() : Invitation
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
        $storage = Storages::Instance()->Load('invites', 'auth');
        return self::RestoreByFilter($storage, '{id} in ('.implode(',', $ids).')');
    }

    /**
     * Deletes a rows by filter
     * @param string $filter filter string, {field} form can be used
     * @return bool
     */
    public static function DeleteAllByFilter(string $filter): bool
    {
        $storage = Storages::Instance()->Load('invites', 'auth');
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

    public static function CreateInvitation(?string $email, ?string $fio, ?array $params): Invitation
    {
        $invitation = self::LoadByEmail($email);
        if(!$invitation) {
            $invitation = self::LoadEmpty();
            $invitation->application = Module::Instance()->application->key;
            $invitation->email = $email;
        }

        $invitation->fio = $fio;
        $invitation->code = md5($invitation->email);
        $invitation->date = new DateTimeField('now');
        $invitation->params = $params ?? [];
        $invitation->accepted = null;
        $invitation->Save(true);
        return $invitation;
    } 
}