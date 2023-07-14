<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ArrayField;

# region Uses:
use App\Modules\Auth\Models\Fields\Applications\ParamsDesignImagesObjectField;
# endregion Uses;

/**
 * Представление поля в таблице в хранилище Изображения
 * @author <author name and email>
 * @package App\Modules\Auth\Models\Fields\Applications\Fields
 * @method ParamsDesignImagesObjectField Item(int $index)
 * @method ParamsDesignImagesObjectField offsetGet(mixed $offset)
 */
class ParamsDesignImagesArrayField extends ArrayField
{
    public const JsonSchema = [
        'type' => 'array',
        'items' => ParamsDesignImagesObjectField::JsonSchema
    ];
}
