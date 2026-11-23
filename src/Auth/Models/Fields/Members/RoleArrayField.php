<?php

namespace App\Modules\Auth\Models\Fields\Members;

use Colibri\Data\Storages\Fields\ArrayField;

# region Uses:
use App\Modules\Auth\Models\Fields\Members\RoleObjectField;
# endregion Uses;

/**
 * Represents assigned member roles.
 * @class
 * @memberof App\Modules\Auth\Models\Fields\Members
 * @extends ArrayField
 * @method RoleObjectField Item(int $index)
 * @method RoleObjectField offsetGet(mixed $offset)
 */
class RoleArrayField extends ArrayField
{
    /**
     * Json schema for the model properties
     * @public
     * @const array
     */
    public const JsonSchema = [
        'type' => 'array',
        'items' => RoleObjectField::JsonSchema
    ];

    /**
     * Returns an item by index
     * @public
     * @param int $index Item index.
     * @return RoleObjectField Assigned role.
     */
    public function Item(int $index): RoleObjectField
    {
        return $this->data[$index] instanceof RoleObjectField ? $this->data[$index] : new RoleObjectField($this->data[$index], $this->_storage, $this->_field);
    }

}
