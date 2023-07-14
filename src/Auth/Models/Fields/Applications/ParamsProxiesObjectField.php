<?php

namespace App\Modules\Auth\Models\Fields\Applications;

use Colibri\Data\Storages\Fields\ObjectField;

# region Uses:

# endregion Uses;

/**
 * Представление поля в таблице в хранилище Прокси для коммуникаций
 * @author <author name and email>
 * @package App\Modules\Auth\Models\Fields\Applications\Fields
 * 
 * region Properties:
 * @property string|null $email Эл. почта
 * @property string|null $phone Отправка СМС
 * endregion Properties;
 */
class ParamsProxiesObjectField extends ObjectField
{
    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            # region SchemaRequired:

			# endregion SchemaRequired;
        ],
        'properties' => [
            # region SchemaProperties:
			'email' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 1024, ] ] ],
			'phone' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 1024, ] ] ],
			# endregion SchemaProperties;
        ]
    ];
}
