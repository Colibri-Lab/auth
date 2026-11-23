<?php

namespace App\Modules\Auth\Models\Fields\Members;

use Colibri\Data\Storages\Fields\ObjectField;

# region Uses:

# endregion Uses;

/**
 * Represents one assigned member role.
 * @class
 * @memberof App\Modules\Auth\Models\Fields\Members
 * @extends ObjectField
 * 
 * region Properties:
 * @property string $role Application role name.
 * endregion Properties;
 */
class RoleObjectField extends ObjectField
{
    /**
     * Json schema for the model properties
     * @public
     * @const array
     */
    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            # region SchemaRequired:
			'role',
            # endregion SchemaRequired;
        ],
        'properties' => [
            # region SchemaProperties:
			'role' => ['type' => 'string', 'maxLength' => 20, ],
            # endregion SchemaProperties;
        ]
    ];

    # region Consts:
    
    # endregion Consts;

    /**
     * Casts for type of model properties
     * @protected
     * @static
     * @var array
     */
    protected static array $casts = [
    # region Casts:
		
    # endregion Casts;
    ];

}
