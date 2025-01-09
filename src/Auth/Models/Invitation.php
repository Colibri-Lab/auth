<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\ObjectField;
# endregion Uses;
use App\Modules\Tools\Models\Notices;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;
use Colibri\App;
use Colibri\IO\Request\Encryption;
use Colibri\IO\Request\Request;
use Colibri\IO\Request\Type;
use Colibri\Utils\Debug;

/**
 * Представление строки в таблице в хранилище Приглашения
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property int $id ID строки
 * @property DateTimeField $datecreated Дата создания строки
 * @property DateTimeField $datemodified Дата последнего обновления строки
 * @property DateTimeField $datedeleted Дата удаления строки (если включно мягкое удаление)
 * @property string|null $application Приложение
 * @property string|null $email Эл. почта
 * @property string $phone Телефон
 * @property string $code Код
 * @property DateTimeField|null $date Дата отправки
 * @property DateTimeField|null $accepted Приглашение принято
 * @property ObjectField|null $params Параметры
 * endregion Properties;
 */
class Invitation extends BaseModelDataRow 
{

    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            'id',
            'datecreated',
            'datemodified',
            # region SchemaRequired:
			'phone',
			'code',
			# endregion SchemaRequired;
        ],
        'properties' => [
            'id' => ['type' => 'integer'],
            'datecreated' => ['type' => 'string', 'format' => 'db-date-time'],
            'datemodified' => ['type' => 'string', 'format' => 'db-date-time'],
            'datedeleted' => [  'oneOf' => [ ['type' => 'null'], ['type' => 'string', 'format' => 'db-date-time'] ] ],
            # region SchemaProperties:
			'application' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 256, ] ] ],
			'email' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 256, ] ] ],
			'phone' => ['type' => 'string', 'maxLength' => 50, ],
			'code' => ['type' => 'string', 'maxLength' => 10, ],
			'date' => [ 'anyOf' => [ ['type' => ['string', 'null'], 'format' => 'db-date-time'], ['type' => ['string', 'null'], 'maxLength' => 0] ] ],
			'accepted' => [ 'anyOf' => [ ['type' => ['string', 'null'], 'format' => 'db-date-time'], ['type' => ['string', 'null'], 'maxLength' => 0] ] ],
			'params' => [  'oneOf' => [ ObjectField::JsonSchema, [ 'type' => 'null'] ] ],
			# endregion SchemaProperties;
        ]
    ];

    # region Consts:

	# endregion Consts;

    protected static array $casts = [
    # region Casts:
		
	# endregion Casts;
    ];

    public static function Create(?int $id = null): Invitation
    {
        if(!$id) {
            return Invitations::LoadEmpty();
        }
        return Invitations::LoadById($id);
    }

    public function Send(mixed $proxies = null): bool
	{

		$langModule = App::$moduleManager->{'lang'};
		
        $invitationData = [];
		$invitationData['email'] = $this->email;
		$invitationData['phone'] = $this->phone;
		$invitationData['code'] = $this->code;
		$invitationData = array_merge($invitationData, (array)$this->params->ToArray(true));

		$noticeName = 'invitation_' . ($langModule ? '_' . $langModule->current : '');
		$notice = Notices::LoadByName($noticeName);
		$notice->Apply($invitationData);
		
		if (!is_null($proxies) && isset($proxies->email)) {

			$url = $proxies->email;
			$request = new Request($url, Type::Post, Encryption::JsonEncoded);
			$request->timeout = 10;
			$request->sslVerify = false;
			$response = $request->Execute(json_encode([
				'recipient' => $this->email,
				'subject' => $notice->subject,
				'body' => $notice->body,
				'attachments' => []
			]));

			if ($response->status !== 200) {
				return false;
			}

			return true;


		} else {
			return Notices::Send($this->email, $notice);
		}


	}

}