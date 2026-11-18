<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ObjectField;

# region Uses:

# endregion Uses;

/**
 * Представление поля в таблице в хранилище Роли
 * @class
 * 
 * region Properties:
 * @property int|null $priority Приоритет
 * @property string|null $name Наименование роли
 * @property string|null $desc Описание роли
 * endregion Properties;
 */
class ParamsRolesObjectField extends ObjectField
{
    /**
     * JsonSchema
     * @const array
     * @public
     */
    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            # region SchemaRequired:

			# endregion SchemaRequired;
        ],
        'properties' => [
            # region SchemaProperties:
			'priority' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'integer', ] ] ],
			'name' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			'desc' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			# endregion SchemaProperties;
        ]
    ];

    /**
     * Get the property priority
     * @return int
     * @public
     */
    public function getPropertyPriority(): int
    {
        return $this->data['priority'] === null ? PHP_INT_MAX : $this->data['priority'];
    }
}
