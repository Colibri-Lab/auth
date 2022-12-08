<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\ValueField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;
use App\Modules\Tools\Models\Notices;

/**
 * Представление строки в таблице в хранилище #{auth-storages-confirmations-desc;Коды верификации}
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property-read int $id ID строки
 * @property-read DateTimeField $datecreated Дата создания строки
 * @property-read DateTimeField $datemodified Дата последнего обновления строки
 * @property string|null $member #{auth-storages-confirmations-fields-member-desc;Пользователь}
 * @property ValueField|null $property #{auth-storages-confirmations-fields-property-desc;Свойство}
 * @property string|null $code #{auth-storages-confirmations-fields-code-desc;Код}
 * @property bool|null $verified #{auth-storages-confirmations-fields-verified-desc;Верифицирован}
 * endregion Properties;
 */
class Confirmation extends BaseModelDataRow
{

	public const JsonSchema = [
		'type' => 'object',
		'required' => [
			'id',
			'datecreated',
			'datemodified',
			# region SchemaRequired:
			'member',
			'property',
			'code',
			'verified',
			# endregion SchemaRequired;

		],
		'properties' => [
			'id' => ['type' => 'integer'],
			'datecreated' => ['type' => 'string', 'format' => 'db-date-time'],
			'datemodified' => ['type' => 'string', 'format' => 'db-date-time'],
			# region SchemaProperties:
			'member' => ['type' => 'string', 'maxLength' => 32],
			'property' => ['type' => 'string', 'enum' => ['email', 'phone', 'reset', 'login']],
			'code' => ['type' => 'string', 'maxLength' => 10],
			'verified' => ['type' => 'boolean',
			],
			# endregion SchemaProperties;

		]
	];

	# region Consts:
	/** #{auth-storages-confirmations-fields-property-values-email;Эл. адрес} */
	public const PropertyEmail = 'email';
	/** #{auth-storages-confirmations-fields-property-values-phone;Телефон} */
	public const PropertyPhone = 'phone';
	/** #{auth-storages-confirmations-fields-property-values-reset;Восстановление пароля} */
	public const PropertyReset = 'reset';
	/** #{auth-storages-confirmations-fields-property-values-login;Вход/Двух-факторная авторизация} */
	public const PropertyLogin = 'login';
	# endregion Consts;

	public function Send(?string $value = null): bool
	{
		$member = Members::LoadByToken($this->member);
		if (!$member) {
			return false;
		}
		$memberData = $member->ExportForUserInterface();
		$memberData['code'] = $this->code;

		$noticeName = 'confirmation_' . $this->property;
		$notice = Notices::LoadByName($noticeName);
		$notice->Apply($memberData);
		if ((string) $this->property === 'email') {
			return Notices::Send($value ? $value : $member->email, $notice);
		} else {
			return Notices::Send($member->email, $notice);
			// надо отправить SMS
			// return false;
		}

	}

}