<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ObjectField;

# region Uses:

# endregion Uses;

/**
 * Представление поля в таблице в хранилище Изображения
 * @author <author name and email>
 * @package App\Modules\Auth\Models\Fields\Applications\Fields
 * 
 * region Properties:
 * @property string|null $key Ключ изображения
 * @property string|null $image Изображение (SVG)
 * endregion Properties;
 */
class ParamsDesignImagesObjectField extends ObjectField
{
    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            # region SchemaRequired:

			# endregion SchemaRequired;
        ],
        'properties' => [
            # region SchemaProperties:
			'key' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 50, ] ] ],
			'image' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', ] ] ],
			# endregion SchemaProperties;
        ]
    ];
}
