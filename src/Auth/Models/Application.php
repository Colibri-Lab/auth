<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\ObjectField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;

/**
 * Представление строки в таблице в хранилище #{auth-storages-applications-desc;Приложения}
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property-read int $id ID строки
 * @property-read DateTimeField $datecreated Дата создания строки
 * @property-read DateTimeField $datemodified Дата последнего обновления строки
 * @property string|null $key #{auth-storages-applications-fields-key-desc;Ключ приложения  (наименование)}
 * @property string|null $token #{auth-storages-applications-fields-token-desc;Токен приложения (постоянный)}
 * @property ObjectField|null $params #{auth-storages-applications-fields-params-desc;Параметры}
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
			'key' => ['type' => ['string', 'null'], 'maxLength' => 255],
			'token' => ['type' => ['string', 'null'], 'maxLength' => 32],
			'params' => ['type' => 'object', 'required' => ['defaultrole',], 'properties' => ['livetime' => ['type' => ['integer', 'null'], ],'domains' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => [], 'properties' => ['pattern' => ['type' => ['string', 'null'], 'maxLength' => 255],]]],'allowrenew' => ['type' => ['boolean', 'null'], ],'roles' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => [], 'properties' => ['name' => ['type' => ['string', 'null'], 'maxLength' => 255],'desc' => ['type' => ['string', 'null'], 'maxLength' => 255],]]],'defaultrole' => ['type' => 'string', 'maxLength' => 255],'enable_two_factor_authentication' => ['type' => ['boolean', 'null'], ],'design' => ['type' => 'object', 'required' => [], 'properties' => ['images' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => [], 'properties' => ['key' => ['type' => ['string', 'null'], 'maxLength' => 50],'image' => ['type' => ['string', 'null'], ],]]],]],'proxies' => ['type' => 'object', 'required' => [], 'properties' => ['email' => ['type' => ['string', 'null'], 'maxLength' => 1024],'sms' => ['type' => ['string', 'null'], 'maxLength' => 1024],]],]],
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
