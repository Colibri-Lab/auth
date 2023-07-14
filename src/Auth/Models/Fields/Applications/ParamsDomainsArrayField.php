<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ArrayField;

# region Uses:
use App\Modules\Auth\Models\Fields\Applications\ParamsDomainsObjectField;
# endregion Uses;

/**
 * Представление поля в таблице в хранилище Домены
 * @author <author name and email>
 * @package App\Modules\Auth\Models\Fields\Applications\Fields
 * @method ParamsDomainsObjectField Item(int $index)
 * @method ParamsDomainsObjectField offsetGet(mixed $offset)
 */
class ParamsDomainsArrayField extends ArrayField
{
    public const JsonSchema = [
        'type' => 'array',
        'items' => ParamsDomainsObjectField::JsonSchema
    ];
}
