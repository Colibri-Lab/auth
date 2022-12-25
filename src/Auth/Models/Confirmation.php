<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\ValueField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;
use App\Modules\Tools\Models\Notices;
use Colibri\IO\Request\Encryption;
use Colibri\IO\Request\Request;
use Colibri\IO\Request\Type;

/**
 * Представление строки в таблице в хранилище Коды верификации
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property-read int $id ID строки
 * @property-read DateTimeField $datecreated Дата создания строки
 * @property-read DateTimeField $datemodified Дата последнего обновления строки
 * @property string $member Пользователь
 * @property ValueField $property Свойство
 * @property string $code Код
 * @property bool $verified Верифицирован
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
			'member' => ['type' => 'string', 'maxLength' => 32, ],
			'property' => ['type' => 'string', 'enum' => ['email', 'phone', 'reset', 'login']],
			'code' => ['type' => 'string', 'maxLength' => 10, ],
			'verified' => ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],],
			# endregion SchemaProperties;

		]
	];

	# region Consts:
	/** Эл. адрес */
	public const PropertyEmail = 'email';
	/** Телефон */
	public const PropertyPhone = 'phone';
	/** Восстановление пароля */
	public const PropertyReset = 'reset';
	/** Вход/Двух-факторная авторизация */
	public const PropertyLogin = 'login';
	# endregion Consts;

	public function Send(?string $value = null, mixed $proxies = null): bool
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

		$property = (string) $this->property;
		if ($property === Confirmation::PropertyLogin) {
			$property = Confirmation::PropertyPhone;
		}

		if (!is_null($proxies) && isset($proxies->$property)) {
			
			$url = $proxies->$property;
			$request = new Request($url, Type::Post, Encryption::JsonEncoded);
			$request->timeout = 10;
			$request->sslVerify = false;
			$response = $request->Execute(json_encode([
				'recipient' => ($value ? $value : $member->email),
				'subject' => $notice->subject,
				'body' => $notice->body,
				'attachments' => []
			]));

			if ($response->status !== 200) {
				return false;
			}

			return true;


		} else {
			return Notices::Send(($value ? $value : $member->email), $notice);
		}


	}

}