<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;
use Colibri\App;
use Colibri\Utils\Debug;

/**
 * Представление строки в таблице в хранилище Аутентификация по биометрии
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property int $id ID строки
 * @property DateTimeField $datecreated Дата создания строки
 * @property DateTimeField $datemodified Дата последнего обновления строки
 * @property DateTimeField $datedeleted Дата удаления строки (если включно мягкое удаление)
 * @property string $token Токен пользователя
 * @property string $device Устройство
 * @property string $rawid Секретный ключ для поиска
 * endregion Properties;
 */
class Device extends BaseModelDataRow 
{

    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            'id',
            'datecreated',
            'datemodified',
            # region SchemaRequired:
			'token',
			'device',
			'rawid',
			# endregion SchemaRequired;
        ],
        'properties' => [
            'id' => ['type' => 'integer'],
            'datecreated' => ['type' => 'string', 'format' => 'db-date-time'],
            'datemodified' => ['type' => 'string', 'format' => 'db-date-time'],
            'datedeleted' => [  'oneOf' => [ ['type' => 'null'], ['type' => 'string', 'format' => 'db-date-time'] ] ],
            # region SchemaProperties:
			'token' => ['type' => 'string', 'maxLength' => 32, ],
			'device' => ['type' => 'string', 'maxLength' => 256, ],
			'rawid' => ['type' => 'string', 'maxLength' => 256, ],
			# endregion SchemaProperties;
        ]
    ];

    # region Consts:

	# endregion Consts;

    protected static array $casts = [
    # region Casts:
		
	# endregion Casts;
    ];

    public static function Create(?int $id = null): Device
    {
        if(!$id) {
            return Devices::LoadEmpty();
        }
        return Devices::LoadById($id);
    }

}