<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ObjectField;

# region Uses:
use App\Modules\Auth\Models\Fields\Applications\ParamsDesignObjectField;
use App\Modules\Auth\Models\Fields\Applications\ParamsDomainsArrayField;
use App\Modules\Auth\Models\Fields\Applications\ParamsProxiesObjectField;
use App\Modules\Auth\Models\Fields\Applications\ParamsRolesArrayField;
use Colibri\Data\Storages\Fields\ArrayField;
# endregion Uses;

/**
 * Представление поля в таблице в хранилище Параметры
 * @author <author name and email>
 * @package App\Modules\Auth\Models\Fields\Applications\Fields
 * 
 * region Properties:
 * @property int|null $livetime Время жизни
 * @property ParamsDomainsArrayField|null $domains Домены
 * @property bool|null $allowrenew Разрешить восстановление по короткому токену
 * @property ParamsRolesArrayField|null $roles Роли
 * @property string $defaultrole Роль по умолчанию
 * @property bool|null $enable_two_factor_authentication Включить двухфакторную аутентификацию
 * @property ParamsDesignObjectField|null $design Макет
 * @property ParamsProxiesObjectField|null $proxies Прокси для коммуникаций
 * @property string|null $allowed_ip IP с которого разрешено восстановление по короткому токену
 * endregion Properties;
 */
class ParamsObjectField extends ObjectField
{
    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            # region SchemaRequired:
			'defaultrole',
			# endregion SchemaRequired;
        ],
        'properties' => [
            # region SchemaProperties:
			'livetime' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'integer', ] ] ],
			'domains' => [  'oneOf' => [ ParamsDomainsArrayField::JsonSchema, [ 'type' => 'null'] ] ],
			'allowrenew' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],] ] ],
			'roles' => [  'oneOf' => [ ParamsRolesArrayField::JsonSchema, [ 'type' => 'null'] ] ],
			'defaultrole' => ['type' => 'string', 'maxLength' => 255, ],
			'enable_two_factor_authentication' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],] ] ],
			'design' => [  'oneOf' => [ ParamsDesignObjectField::JsonSchema, [ 'type' => 'null'] ] ],
			'proxies' => [  'oneOf' => [ ParamsProxiesObjectField::JsonSchema, [ 'type' => 'null'] ] ],
			'allowed_ip' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 256, ] ] ],
			# endregion SchemaProperties;
        ]
    ];
}
