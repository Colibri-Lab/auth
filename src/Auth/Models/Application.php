<?php

namespace App\Modules\Auth\Models;

# region Uses:
use App\Modules\Auth\Models\Fields\Applications\ParamsObjectField;
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\ObjectField;
# endregion Uses;
use Colibri\App;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;

/**
 * Представление строки в таблице в хранилище Приложения
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property int $id ID строки
 * @property DateTimeField $datecreated Дата создания строки
 * @property DateTimeField $datemodified Дата последнего обновления строки
 * @property DateTimeField $datedeleted Дата удаления строки (если включно мягкое удаление)
 * @property string|null $key Ключ приложения  (наименование)
 * @property string|null $token Токен приложения (постоянный)
 * @property ParamsObjectField|null $params Параметры
 * endregion Properties;
 */
class Application extends BaseModelDataRow
{

    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            'id',
            'datecreated',
            'datemodified',
            # region SchemaRequired:

			# endregion SchemaRequired;

        ],
        'properties' => [
            'id' => ['type' => 'integer'],
            'datecreated' => ['type' => 'string', 'format' => 'db-date-time'],
            'datemodified' => ['type' => 'string', 'format' => 'db-date-time'],
            # region SchemaProperties:
			'key' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			'token' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 32, ] ] ],
			'params' => [  'oneOf' => [ ParamsObjectField::JsonSchema, [ 'type' => 'null'] ] ],
			# endregion SchemaProperties;

        ]
    ];

    # region Consts:

	# endregion Consts;

    public function ExportForUserInterface(): array
    {
        $arr = $this->ToArray(true);
        unset($arr['id']);
        unset($arr['datecreated']);
        unset($arr['datemodified']);
        unset($arr['params']['domains']);
        unset($arr['params']['roles']);
        return $arr;
    }

    public function CheckRole(string $role)
    {
        foreach ($this->params->roles as $r) {
            if ($r->name === $role) {
                return true;
            }
        }
        return false;
    }

    public function GetLink(): string
    {
        $hosts = App::$config->Query('hosts.domains.auth')->AsArray();
        $host = $hosts[0];
        $host = str_replace('*', $this->key, $host);
        return 'https://' . $host;
    }

}