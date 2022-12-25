<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;
use Colibri\Common\RandomizationHelper;
use Firebase\JWT\JWT;
use Colibri\App;
use Colibri\Utils\Cache\Mem;

/**
 * Представление строки в таблице в хранилище Сессии
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property-read int $id ID строки
 * @property-read DateTimeField $datecreated Дата создания строки
 * @property-read DateTimeField $datemodified Дата последнего обновления строки
 * @property string $member Токен пользователя
 * @property string $key Ключ сессии
 * @property string $token Токен сессии (JWT)
 * @property int $expires Время жизни
 * @property string $secret Секретный ключ
 * endregion Properties;
 */
class Session extends BaseModelDataRow
{

    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            'id',
            'datecreated',
            'datemodified',
            # region SchemaRequired:
			'member',
			'key',
			'token',
			'expires',
			'secret',
			# endregion SchemaRequired;

        ],
        'properties' => [
            'id' => ['type' => 'integer'],
            'datecreated' => ['type' => 'string', 'format' => 'db-date-time'],
            'datemodified' => ['type' => 'string', 'format' => 'db-date-time'],
            # region SchemaProperties:
			'member' => ['type' => 'string', 'maxLength' => 32, ],
			'key' => ['type' => 'string', 'maxLength' => 32, ],
			'token' => ['type' => 'string', 'maxLength' => 8192, ],
			'expires' => ['type' => 'integer', ],
			'secret' => ['type' => 'string', 'maxLength' => 32, ],
			# endregion SchemaProperties;

        ]
    ];

    # region Consts:

	# endregion Consts;

    private function _generateSecret()
    {
        $this->_data['sessions_secret'] = md5(microtime(true));
    }

    private function _generateToken(bool $force = false)
    {

        if (($this->_data['sessions_token'] ?? null) && !$force) {
            return;
        }

        $arr = $this->ToArray(true);
        if (!($this->_data['sessions_secret'] ?? null)) {
            $this->_generateSecret();
        }

        unset($arr['token']);
        unset($arr['key']);
        unset($arr['secret']);

        $this->_data['sessions_token'] = JWT::encode($arr, $this->_data['sessions_secret'], 'HS256');

        if (($this->_data['sessions_key'] ?? null)) {
            Mem::Delete('sess' . $this->_data['sessions_key']);
        }
        $this->_data['sessions_key'] = md5($this->_data['sessions_token']);

    }

    public function _typeExchange(string $mode, string $property, $value = false): mixed
    {
        $ret = parent::_typeExchange($mode, $property, $value);
        if ($mode === 'set' && in_array($property, ['sessions_member', 'sessions_secret', 'sessions_key'])) {
            $this->_generateToken(true);
        }
        return $ret;
    }

    public function Save(bool $performValidationBeforeSave = false): bool
    {
        $this->_generateToken();
        if (!$this->member) {
            if ($this->id) {
                Sessions::DeleteAllByIds([$this->id]);
            }
            Mem::Write('sess' . $this->key, $this->ExportForMemcached(), $this->expires ?: 3600);
            return true;
        } else {
            return parent::Save($performValidationBeforeSave);
        }
    }

    public function ExportForUserInterface(): array
    {
        $arr = $this->ToArray(true);
        unset($arr['id']);
        unset($arr['datemodified']);
        unset($arr['secret']);
        if ($arr['member'] ?? null) {
            $member = Members::LoadByToken($arr['member']);
            $arr['member'] = $member->ExportForUserInterface();
        }
        return $arr;
    }

    public function ExportForMemcached(): array
    {
        $arr = $this->ToArray(false);
        unset($arr['sessions_id']);
        unset($arr['sessions_datemodified']);
        return $arr;
    }

    public function GenerateCookie(bool $secure = true): object
    {
        // $this->expires
        return (object) ['name' => 'cc-jwt', 'value' => $this->token, 'expire' => time() + 365 * 86400, 'domain' => App::$request->host, 'path' => '/', 'secure' => $secure];
    }

}