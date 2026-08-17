<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ObjectField;

# region Uses:
use App\Modules\Auth\Models\Fields\Applications\ParamsDesignImagesArrayField;
use Colibri\Data\Storages\Fields\ArrayField;
# endregion Uses;

/**
 * Представление поля в таблице в хранилище Макет
 * @class
 * 
 * region Properties:
 * @property ParamsDesignImagesArrayField|null $images Изображения
 * endregion Properties;
 */
class ParamsDesignObjectField extends ObjectField
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
			'images' => [  'oneOf' => [ ParamsDesignImagesArrayField::JsonSchema, [ 'type' => 'null'] ] ],
			# endregion SchemaProperties;
        ]
    ];
}
