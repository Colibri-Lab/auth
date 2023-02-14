<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ObjectField;

# region Uses:

# endregion Uses;

/**
 * Представление поля в таблице в хранилище Роли
 * @author <author name and email>
 * @package App\Modules\Auth\Models\Fields\Applications\Fields
 * 
 * region Properties:
 * @property string|null $name Наименование роли
 * @property string|null $desc Описание роли
 * endregion Properties;
 */
class ParamsRolesObjectField extends ObjectField
{
    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            # region SchemaRequired:

            # endregion SchemaRequired;
        ],
        'properties' => [
            # region SchemaProperties:
			'name' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			'desc' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
            # endregion SchemaProperties;
        ]
    ];
}
