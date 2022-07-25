<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\DateField;
use Colibri\Data\Storages\Fields\ValueField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;

/**
 * Представление строки в таблице в хранилище #{auth-storages-members-desc;Пользователи}
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property-read int $id ID строки
 * @property-read DateTimeField $datecreated Дата создания строки
 * @property-read DateTimeField $datemodified Дата последнего обновления строки
 * @property string|null $token #{auth-storages-members-fields-token-desc;Токен пользователя}
 * @property string|null $email #{auth-storages-members-fields-email-desc;Эл. адрес пользователя}
 * @property string|null $phone #{auth-storages-members-fields-phone-desc;Телефон}
 * @property string|null $password #{auth-storages-members-fields-password-desc;Пароль}
 * @property string|null $firstName #{auth-storages-members-fields-firstName-desc;Имя}
 * @property string|null $lastName #{auth-storages-members-fields-lastName-desc;Фамилия}
 * @property string|null $patronymic #{auth-storages-members-fields-patronymic-desc;Отчество}
 * @property DateField|null $birthdate #{auth-storages-members-fields-birthdate-desc;Дата рождения}
 * @property ValueField|null $gender #{auth-storages-members-fields-gender-desc;Пол}
 * endregion Properties;
 */
class Member extends BaseModelDataRow {
    
    # region Consts:
    	/** #{auth-storages-members-fields-gender-values-male;Мужской} */
	public const GenderMale = 'male';
	/** #{auth-storages-members-fields-gender-values-female;Женский} */
	public const GenderFemale = 'female';
    # endregion Consts;


}