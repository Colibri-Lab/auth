<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\ObjectField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;

/**
 * Представление строки в таблице в хранилище Приложения
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property-read int $id ID строки
 * @property-read DateTimeField $datecreated Дата создания строки
 * @property-read DateTimeField $datemodified Дата последнего обновления строки
 * @property string|null $key Ключ приложения  (наименование)
 * @property string|null $token Токен приложения (постоянный)
 * @property ObjectField|null $params Параметры
 * endregion Properties;
 */
class Application extends BaseModelDataRow
{

    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            'id',
            'datecreated',
            'datemodified',
            # region SchemaRequired:

			# endregion SchemaRequired;

        ],
        'properties' => [
            'id' => ['type' => 'integer'],
            'datecreated' => ['type' => 'string', 'format' => 'db-date-time'],
            'datemodified' => ['type' => 'string', 'format' => 'db-date-time'],
            # region SchemaProperties:
			'key' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			'token' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 32, ] ] ],
			'params' => [  'oneOf' => [ [ 'type' => 'null' ], ['type' => 'object', 'required' => ['defaultrole',], 'properties' => ['livetime' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'integer', ] ] ],'domains' => [  'oneOf' => [ [ 'type' => 'null' ], ['type' => 'array', 'items' => ['type' => 'object', 'required' => [], 'properties' => ['pattern' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],]]]]],'allowrenew' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],] ] ],'roles' => [  'oneOf' => [ [ 'type' => 'null' ], ['type' => 'array', 'items' => ['type' => 'object', 'required' => [], 'properties' => ['name' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],'desc' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],]]]]],'defaultrole' => ['type' => 'string', 'maxLength' => 255, ],'enable_two_factor_authentication' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],] ] ],'design' => [  'oneOf' => [ [ 'type' => 'null' ], ['type' => 'object', 'required' => [], 'properties' => ['images' => [  'oneOf' => [ [ 'type' => 'null' ], ['type' => 'array', 'items' => ['type' => 'object', 'required' => [], 'properties' => ['key' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 50, ] ] ],'image' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', ] ] ],]]]]],]]]],'proxies' => [  'oneOf' => [ [ 'type' => 'null' ], ['type' => 'object', 'required' => [], 'properties' => ['email' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 1024, ] ] ],'sms' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 1024, ] ] ],]]]],'allowed_ip' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 256, ] ] ],]]]],
			# endregion SchemaProperties;

        ]
    ];

    # region Consts:

	# endregion Consts;

    public function ExportForUserInterface(): array
    {
        $arr = $this->ToArray(true);
        unset($arr['id']);
        unset($arr['datecreated']);
        unset($arr['datemodified']);
        unset($arr['params']['domains']);
        unset($arr['params']['roles']);
        return $arr;
    }

    public function CheckRole(string $role)
    {
        foreach ($this->params->roles as $r) {
            if ($r->name === $role) {
                return true;
            }
        }
        return false;
    }

}