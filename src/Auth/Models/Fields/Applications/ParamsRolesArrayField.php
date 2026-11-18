<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ArrayField;

# region Uses:
use App\Modules\Auth\Models\Fields\Applications\ParamsRolesObjectField;
# endregion Uses;

/**
 * Представление поля в таблице в хранилище Роли
 * @class
 * @method ParamsRolesObjectField Item(int $index)
 * @method ParamsRolesObjectField offsetGet(mixed $offset)
 */
class ParamsRolesArrayField extends ArrayField
{
    /**
     * JsonSchema
     * @const array
     * @public
     */
    public const JsonSchema = [
        'type' => 'array',
        'items' => ParamsRolesObjectField::JsonSchema
    ];

    /**
     * Search for the role in list
     * @param string $name name of the role
     * @public
     */
    public function Search(string $name): ?ParamsRolesObjectField 
    {
        foreach($this as $item) {
            if($item->name === $name) {
                return $item;
            }
        }
        return null;
    }

}
