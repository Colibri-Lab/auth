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
 * @property int $id ID строки
 * @property DateTimeField $datecreated Дата создания строки
 * @property DateTimeField $datemodified Дата последнего обновления строки
 * @property DateTimeField $datedeleted Дата удаления строки (если включно мягкое удаление)
 * @property ValueField|string|ValueField $property Свойство
 * @property string|null $value Значение свойства
 * @property string|null $member Пользователь
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
			'property' => ['type' => 'string', 'enum' => ['email', 'phone', 'reset', 'login']],
			'value' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			'member' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 36, ] ] ],
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

		$property = (string) $this->property;
		if ($property === Confirmation::PropertyLogin) {
			$confirmationData = $member->ExportForUserInterface();
			$value = $value ?: $member->email;
			$property = 'email';
		} else {
			$confirmationData = [
				$property => $this->value
			];
			$value = $value ?: $this->value;
		}

		$confirmationData['code'] = $this->code;

		$noticeName = 'confirmation_' . $this->property;
		$notice = Notices::LoadByName($noticeName);
		$notice->Apply($confirmationData);


		if (!is_null($proxies) && isset($proxies->$property)) {

			$url = $proxies->$property;
			$request = new Request($url, Type::Post, Encryption::JsonEncoded);
			$request->timeout = 10;
			$request->sslVerify = false;
			$response = $request->Execute(json_encode([
				'recipient' => $value,
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