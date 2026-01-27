<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ArrayField;

# region Uses:
use App\Modules\Auth\Models\Fields\Applications\ParamsRolesObjectField;
# endregion Uses;

/**
 * Представление поля в таблице в хранилище Роли
 * @author <author name and email>
 * @package App\Modules\Auth\Models\Fields\Applications\Fields
 * @method ParamsRolesObjectField Item(int $index)
 * @method ParamsRolesObjectField offsetGet(mixed $offset)
 */
class ParamsRolesArrayField extends ArrayField
{
    public const JsonSchema = [
        'type' => 'array',
        'items' => ParamsRolesObjectField::JsonSchema
    ];
}
