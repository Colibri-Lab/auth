<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;

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


}