<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;
use Colibri\Common\RandomizationHelper;
use Firebase\JWT\JWT;

/**
 * Представление строки в таблице в хранилище #{auth-storages-sessions-desc;Сессии}
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property-read int $id ID строки
 * @property-read DateTimeField $datecreated Дата создания строки
 * @property-read DateTimeField $datemodified Дата последнего обновления строки
 * @property string|null $member #{auth-storages-sessions-fields-member-desc;Токен пользователя}
 * @property string|null $key #{auth-storages-sessions-fields-key-desc;Ключ сессии}
 * @property string|null $token #{auth-storages-sessions-fields-token-desc;Токен сессии (JWT)}
 * @property int|null $expires #{auth-storages-sessions-fields-expires-desc;Время жизни}
 * @property string|null $secret #{auth-storages-sessions-fields-secret-desc;Секретный ключ}
 * endregion Properties;
 */
class Session extends BaseModelDataRow {
    
    # region Consts:
    
    # endregion Consts;

    public function GenerateSecret()
    {
        $this->secret = md5(microtime(true));
    }

    public function GenerateToken(bool $force = false)
    {

        if($this->token && !$force) {
            return;
        }

        $arr = $this->ToArray(true);
        if(!$this->secret) {
            $this->GenerateSecret();
        }
        unset($arr['token']);
        unset($arr['key']);
        unset($arr['secret']);
        if($this->member) {
            $member = Members::LoadByKey($this->member);
            $arr['member'] = $member->ExportForUserInterface();
        }
        $this->token = JWT::encode($arr, $this->secret, 'HS256');
        $this->key = md5($this->token);

    }

    public function Save(): bool
    {
        $this->GenerateToken(true);
        return parent::Save();
    }

    public function ExportForUserInterface(): array
    {
        $arr = $this->ToArray(true);
        unset($arr['id']);
        unset($arr['datecreated']);
        unset($arr['datemodified']);
        unset($arr['secret']);
        return $arr;
    }

}