<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\ObjectField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;

/**
 * Представление строки в таблице в хранилище #{auth-storages-applications-desc;Приложения}
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property-read int $id ID строки
 * @property-read DateTimeField $datecreated Дата создания строки
 * @property-read DateTimeField $datemodified Дата последнего обновления строки
 * @property string|null $key #{auth-storages-applications-fields-key-desc;Ключ приложения  (наименование)}
 * @property string|null $token #{auth-storages-applications-fields-token-desc;Токен приложения (постоянный)}
 * @property ObjectField|null $params #{auth-storages-applications-fields-params-desc;Параметры}
 * endregion Properties;
 */
class Application extends BaseModelDataRow {
    
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

}