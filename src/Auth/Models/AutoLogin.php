<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
# endregion Uses;
use App\Modules\Auth\Controllers\AppController;
use App\Modules\Auth\Controllers\AutologinController;
use App\Modules\Auth\Module;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;
use Colibri\App;
use Colibri\Utils\Debug;

/**
 * Представление строки в таблице в хранилище Запрос на автовход
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property int $id ID строки
 * @property DateTimeField $datecreated Дата создания строки
 * @property DateTimeField $datemodified Дата последнего обновления строки
 * @property DateTimeField $datedeleted Дата удаления строки (если включно мягкое удаление)
 * @property string|null $application Приложение
 * @property string $token Токен пользователя
 * @property string|null $code Код
 * @property string|null $return_to Возврат
 * endregion Properties;
 */
class AutoLogin extends BaseModelDataRow 
{

    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            'id',
            'datecreated',
            'datemodified',
            # region SchemaRequired:
			'token',
			# endregion SchemaRequired;
        ],
        'properties' => [
            'id' => ['type' => 'integer'],
            'datecreated' => ['type' => 'string', 'format' => 'db-date-time'],
            'datemodified' => ['type' => 'string', 'format' => 'db-date-time'],
            'datedeleted' => [  'oneOf' => [ ['type' => 'null'], ['type' => 'string', 'format' => 'db-date-time'] ] ],
            # region SchemaProperties:
			'application' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 256, ] ] ],
			'token' => ['type' => 'string', 'maxLength' => 32, ],
			'code' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 256, ] ] ],
			'return_to' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 512, ] ] ],
			# endregion SchemaProperties;
        ]
    ];

    # region Consts:

	# endregion Consts;

    protected static array $casts = [
    # region Casts:
		
	# endregion Casts;
    ];

    public static function Create(?int $id = null): AutoLogin
    {
        if(!$id) {
            return AutoLogins::LoadEmpty();
        }
        return AutoLogins::LoadById($id);
    }

    public function GenerateLink(): string
    {
        $app = Module::$instance->application;
        return $app->GetLink() . AutologinController::GetEntryPoint('perform', 'html', ['code' => $this->code]);
    }

}