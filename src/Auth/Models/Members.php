<?php

namespace App\Modules\Auth\Models;

use Colibri\Data\DataAccessPoint;
use Colibri\Data\SqlClient\IDataReader;
use Colibri\Data\Storages\Storages;
use Colibri\Data\Storages\Storage;
use Colibri\Utils\Logs\Logger;
use Colibri\Data\Storages\Models\DataTable as BaseModelDataTable;
use App\Modules\Auth\Models\Member;

/**
 * Таблица, представление данных в хранилище Пользователи
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * @method Member[] getIterator()
 * @method Member _createDataRowObject()
 * @method Member _read()
 * @method Member offsetGet(mixed $offset)
 * 
 */
class Members extends BaseModelDataTable
{

    /**
     * Конструктор
     * @param DataAccessPoint $point точка доступа
     * @param IDataReader|null $reader ридер
     * @param string|\Closure $returnAs возвращать в виде класса
     * @param Storage|null $storage хранилище
     * @return void 
     */
    public function __construct(DataAccessPoint $point, IDataReader $reader = null, string $returnAs = 'Member', Storage|null $storage = null)
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
     * @return Members
     */
    static function LoadByFilter(int $page = -1, int $pagesize = 20, string $filter = null, string $order = null, array $params = [], bool $calculateAffected = true): ? Members
    {
        $storage = Storages::Create()->Load('members');
        $additionalParams = ['page' => $page, 'pagesize' => $pagesize, 'params' => $params];
        $additionalParams['type'] = $calculateAffected ? DataAccessPoint::QueryTypeReader : DataAccessPoint::QueryTypeBigData;
        return self::LoadByQuery(
            $storage,
            'select * from ' . $storage->table .
            ($filter ? ' where ' . $filter : '') .
            ($order ? ' order by ' . $order : ''),
            $additionalParams
        );
    }

    /**
     * Загружает без фильтра
     * @param int $page страница
     * @param int $pagesize размер страницы
     * @return Members 
     */
    static function LoadAll(int $page = -1, int $pagesize = 20, bool $calculateAffected = false): ? Members
    {
        return self::LoadByFilter($page, $pagesize, null, null, [], $calculateAffected);
    }

    /**
     * Возвращает модель по ID
     * @param int $id ID строки
     * @return Member|null
     */
    static function LoadById(int $id): Member|null
    {
        $table = self::LoadByFilter(1, 1, '{id}=[[id:integer]]', null, ['id' => $id], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Возвращает модель по token
     * @param string $token ID строки
     * @return Member|null
     */
    static function LoadByToken(string $token): Member|null
    {
        $table = self::LoadByFilter(1, 1, '{token}=[[token:string]]', null, ['token' => $token], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Возвращает модели по token-ам
     * @param array $tokens
     * @return Members|null
     */
    static function LoadByTokens(array $tokens): Members|null
    {
        return self::LoadByFilter(1, 100, '{token} in (\'' . implode('\',\'', $tokens) . '\')', null, [], false);
    }

    /**
     * Возвращает модели по роли
     * @param array $role
     * @return Members|null
     */
    static function LoadByRole(string|array $role): Members|null
    {
        if (is_array($role)) {
            return self::LoadByFilter(1, 100, '{role} in (\'' . implode('\',\'', $role) . '\')', null, [], false);
        } else {
            return self::LoadByFilter(1, 100, '{role}=[[role:string]]', null, ['role' => $role], false);
        }
    }

    /**
     * Возвращает модель по email
     * @param string $email ID строки
     * @return Member|null
     */
    static function LoadByEmail(string $email): Member|null
    {
        $table = self::LoadByFilter(1, 1, '{email}=[[email:string]]', null, ['email' => $email], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Возвращает модель по phone
     * @param string $phone ID строки
     * @return Member|null
     */
    static function LoadByPhone(string $phone): Member|null
    {
        $table = self::LoadByFilter(1, 1, '{phone}=[[phone:string]]', null, ['phone' => $phone], false);
        return $table && $table->Count() > 0 ? $table->First() : null;
    }

    /**
     * Создание модели по названию хранилища
     * @return Member
     */
    static function LoadEmpty(): Member
    {
        $table = self::LoadByFilter(-1, 20, 'false', null, [], false);
        return $table->CreateEmptyRow();
    }

    /**
     * Регистрация пользователя
     * @return Member
     */
    static function Register(string $email, string $phone, string $password): Member
    {
        $model = self::LoadEmpty();
        $model->email = $email;
        $model->phone = $phone;
        $model->password = $password;
        return $model;
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
        $storage = Storages::Create()->Load('members');
        return self::DeleteByFilter($storage->table, $filter);

    }

    static function DataMigrate(? Logger $logger = null): bool
    {
        // миграция данных
        return true;
    }

}