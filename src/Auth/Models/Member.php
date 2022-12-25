<?php

namespace App\Modules\Auth\Models;

# region Uses:
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\DateField;
use Colibri\Data\Storages\Fields\ValueField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;
use Colibri\Encryption\Crypt;
use Psr\Log\InvalidArgumentException;
use Colibri\Common\RandomizationHelper;
use Throwable;

/**
 * Представление строки в таблице в хранилище Пользователи
 * @author <author name and email>
 * @package App\Modules\Auth\Models
 * 
 * region Properties:
 * @property-read int $id ID строки
 * @property-read DateTimeField $datecreated Дата создания строки
 * @property-read DateTimeField $datemodified Дата последнего обновления строки
 * @property string $token Токен пользователя
 * @property string $email Эл. адрес пользователя
 * @property string $phone Телефон
 * @property string $password Пароль
 * @property string $first_name Имя
 * @property string $last_name Фамилия
 * @property string|null $patronymic Отчество
 * @property DateField|null $birthdate Дата рождения
 * @property ValueField|null $gender Пол
 * @property string|null $role Роль
 * @property bool $email_confirmed Почта подтверждена
 * @property bool $phone_confirmed Телефон подтвержден
 * @property bool $blocked Заблокирован (удален)
 * @property bool $two_factor Двухфакторная аутентификация
 * endregion Properties;
 */
class Member extends BaseModelDataRow
{

    const PasswordKey = 'AAAAB3NzaC1yc2EAAAADAQABAAABgQDIhRKlVdp8GPQzi9Yeje8B81qk5fFW3iC4xCu0HvuxvrnDHT5368odWBo3DPqQzRPhaGmZiDKYNRZnODGXiyNJwYEieZtAIt/pnLB1e5xUJJculhcgpicOPSBGGpAUUmroYaT0+K19aO5FIfOtmb5hY+Bkq9po0XSODhcnHZXntPBFOWyLdqkB2LB4jJPNUavhQDXOUqCwL/QFWPblPSbNILUSdImgWr41gSO5ISdvPMrfoAy3zUPuLKkge5l/KEu1Ga4IXMVI1YcKt7+ho1JHaDBTAmlfhJ8T1L+RgRElKPRCQ0zfV6SZoVK2X/uovNu0P+oB5WVfEkVQOQs/yPVPLJD9Ink54WwNLpZKz152DhdeHW+TA3UPwunQkciubJai85sV0Ask5q4vfUtqHAgsTuoNIYuZ6PotH4fU4JzyexhKA9UZOdG8qDWwueYrjmFINLrmMIQcHnf/yQdEprVpeThfZlU2PKp29/MdVx/5T4fhNd82xy1nLCqLbvg4T9U=';

    public const JsonSchema = [
        'type' => 'object',
        'required' => [
            'id',
            'datecreated',
            'datemodified',
            # region SchemaRequired:
			'token',
			'email',
			'phone',
			'password',
			'first_name',
			'last_name',
			'email_confirmed',
			'phone_confirmed',
			'blocked',
			'two_factor',
			# endregion SchemaRequired;

        ],
        'properties' => [
            'id' => ['type' => 'integer'],
            'datecreated' => ['type' => 'string', 'format' => 'db-date-time'],
            'datemodified' => ['type' => 'string', 'format' => 'db-date-time'],
            # region SchemaProperties:
			'token' => ['type' => 'string', 'maxLength' => 32, ],
			'email' => ['type' => 'string', 'maxLength' => 255, ],
			'phone' => ['type' => 'string', 'maxLength' => 50, ],
			'password' => ['type' => 'string', 'maxLength' => 128, ],
			'first_name' => ['type' => 'string', 'maxLength' => 255, ],
			'last_name' => ['type' => 'string', 'maxLength' => 255, ],
			'patronymic' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			'birthdate' => [ 'anyOf' => [ ['type' => ['string', 'null'], 'format' => 'date'], ['type' => ['string', 'null'], 'maxLength' => 0] ] ],
			'gender' => [  'oneOf' => [ [ 'type' => 'null' ], ['type' => 'string', 'enum' => ['male', 'female']] ] ],
			'role' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 20, ] ] ],
			'email_confirmed' => ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],],
			'phone_confirmed' => ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],],
			'blocked' => ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],],
			'two_factor' => ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],],
			# endregion SchemaProperties;

        ]
    ];

    # region Consts:
	/** Мужской */
	public const GenderMale = 'male';
	/** Женский */
	public const GenderFemale = 'female';
	# endregion Consts;


    public function setPropertyEmail(string $value): void
    {
        $this->_data['members_email'] = $value;
        $this->_data['members_token'] = md5($value);
    }

    public function setPropertyPassword(string $value): void
    {
        if (($strength = self::CheckPasswordStrength($this->email, $value)) < 40) {
            throw new InvalidArgumentException(sprintf('#{auth-errors-member-password-strength-not-match}', $strength));
        }
        $this->_data['members_password'] = md5(Crypt::Encrypt(self::PasswordKey, $value));
    }

    public function Authorize(string $password): bool
    {
        return $this->password === md5(Crypt::Encrypt(self::PasswordKey, $password));
    }

    public static function CheckPasswordStrength(string $email, string $password): float
    {

        if (strlen($password) < 8) {
            return 0;
        }

        if (!$password) {
            return 0;
        }

        $score = 0;
        // award every unique letter until 5 repetitions
        $letters = [];
        for ($i = 0; $i < strlen($password); $i++) {
            $letters[substr($password, $i, 1)] = ($letters[substr($password, $i, 1)] ?? 0) + 1;
            $score += 5.0 / $letters[substr($password, $i, 1)];
        }

        // bonus points for mixing it up
        $variations = [
            'digits' => preg_match('/\d/', $password),
            'lower' => preg_match('/[a-z]/', $password),
            'upper' => preg_match('/[A-Z]/', $password),
            'nonWords' => preg_match('/\W/', $password),
        ];

        $variationCount = 0;
        foreach ($variations as $check => $v) {
            $variationCount += (($variations[$check] ?? false) === 1) ? 1 : 0;
        }
        $score += ($variationCount - 1) * 10;

        if ($score > 100) {
            $score = 100;
        }

        $score = (int) ($score);

        return $score;

    }

    public function SendConfirmationMessage(string $property, ?string $value = null): bool
    {
        if (!in_array($property, [Confirmation::PropertyEmail, Confirmation::PropertyPhone])) {
            return false;
        }

        /** @var Application|null $app */
        $app = Module::$instance->application;
        if (!$app) {
            throw new InvalidArgumentException('Application not found', 404);
        }

        $confirmation = Confirmations::LoadByMember($property, $this->token);
        if (!$confirmation) {
            $confirmation = Confirmations::LoadEmpty();
            $confirmation->property = $property;
            $confirmation->member = $this->token;
        }

        $confirmation->code = RandomizationHelper::Numeric(6);
        if( ($res = $confirmation->Save(true)) !== true ) {
            throw new InvalidArgumentException($res->error, 500);
        }

        return $confirmation->Send($value, $app->params->proxies);
        
    }

    public function SendResetMessage(): bool
    {

        /** @var Application|null $app */
        $app = Module::$instance->application;
        if (!$app) {
            throw new InvalidArgumentException('Application not found', 404);
        }

        $confirmation = Confirmations::LoadByMember(Confirmation::PropertyReset, $this->token);
        if (!$confirmation) {
            $confirmation = Confirmations::LoadEmpty();
            $confirmation->property = Confirmation::PropertyReset;
            $confirmation->member = $this->token;
        }

        $confirmation->code = RandomizationHelper::Numeric(6);
        if( ($res = $confirmation->Save(true)) !== true) {
            throw new InvalidArgumentException($res->error, 500);
        }

        return $confirmation->Send(null, $app->params->proxies);
    }

    public function SendTwoFactorAuthorizationMessage(): bool
    {
        /** @var Application|null $app */
        $app = Module::$instance->application;
        if (!$app) {
            throw new InvalidArgumentException('Application not found', 404);
        }

        $confirmation = Confirmations::LoadByMember(Confirmation::PropertyLogin, $this->token);
        if (!$confirmation) {
            $confirmation = Confirmations::LoadEmpty();
            $confirmation->property = Confirmation::PropertyLogin;
            $confirmation->member = $this->token;
        }

        $confirmation->code = RandomizationHelper::Numeric(6);
        if( ($res = $confirmation->Save(true)) !== true) {
            throw new InvalidArgumentException($res->error, 500);
        }

        return $confirmation->Send(null, $app->params->proxies);
    }

    public function Update(object|array $mutationData): QueryInfo|bool
    {
        foreach ($mutationData as $key => $value) {
            if (!in_array($key, ['token', 'email', 'phone', 'email_confirmed', 'phone_confirmed', 'role'])) {
                $this->$key = $value;
            }
        }
        
        if( ($res = $this->Save(true)) !== true) {
            throw new InvalidArgumentException($res->error, 500);
        }

        return true;
    }

    public function ConfirmProperty(string $property, string $code): bool
    {
        $confirmation = Confirmations::LoadByMember($property, $this->token);
        if ($confirmation && $confirmation->code === $code) {
            $this->{$property . '_confirmed'} = true;
            
            if( ($res = $this->Save(true)) !== true) {
                throw new \InvalidArgumentException($res->error, 400);
            }

            $res = $confirmation->Delete();
            if( $res->error ) {
                throw new \InvalidArgumentException($res->error, 400);
            }

            return true;

        }

        throw new \InvalidArgumentException('Invalid code', 400);
    }

    public function UpdateIdentify(string $property, string $code, string $value): bool
    {
        $confirmation = Confirmations::LoadByMember($property, $this->token);
        if ($confirmation && $confirmation->code === $code) {
            $this->{$property . '_confirmed'} = true;
            $this->$property = $value;
            
            if( ($res = $this->Save(true)) !== true) {
                throw new \InvalidArgumentException($res->error, 400);
            }

            $res = $confirmation->Delete();
            if( $res->error ) {
                throw new \InvalidArgumentException($res->error, 400);
            }

            return true;
        }
        return false;
    }

    public function UpdateProfile(string $firstName, string $lastName, string $patronymic, string $gender = null, DateTimeField|string|null $birthdate = null): bool
    {

        if ($gender && !in_array($gender, [self::GenderMale, self::GenderFemale])) {
            return false;
        }

        if (is_string($birthdate)) {
            $birthdate = new DateTimeField($birthdate);
        }

        $this->first_name = $firstName;
        $this->last_name = $lastName;
        $this->patronymic = $patronymic;
        $this->gender = $gender;
        $this->birthdate = $birthdate;
        
        if( ($res = $this->Save(true)) !== true) {
            throw new \InvalidArgumentException($res->error, 400);
        }
        return true;

    }

    public function UpdateIdentity(string $email, string $phone): bool
    {

        $this->email = $email;
        $this->phone = $phone;

        if ($this->IsPropertyChanged('email')) {
            $this->email_confirmed = false;
            $this->SendConfirmationMessage('email');
        }
        if ($this->IsPropertyChanged('phone')) {
            $this->phone_confirmed = false;
            $this->SendConfirmationMessage('phone');
        }

        if( ($res = $this->Save(true)) !== true) {
            throw new \InvalidArgumentException($res->error, 400);
        }
        return true;

    }

    public function UpdatePassword(string $currentPassword, string $newPassword): bool
    {
        if (!$this->Authorize($currentPassword)) {
            return false;
        }
        $this->password = $newPassword;
        
        if( ($res = $this->Save(true)) !== true) {
            throw new \InvalidArgumentException($res->error, 400);
        }
        return true;
    }

    public function UpdateRole(string $newRole): bool
    {
        $currentRole = $this->role;
        $roles = Module::$instance->application->params->roles;
        $currentIndex = 0;
        foreach ($roles as $index => $role) {
            if ($role->name === $currentRole) {
                $currentIndex = $index;
            }
        }

        $newIndex = 0;
        foreach ($roles as $index => $role) {
            if ($role->name === $newRole) {
                $newIndex = $index;
            }
        }

        if ($newIndex > $currentIndex) {
            $this->role = $newRole;
            
            if( ($res = $this->Save(true)) !== true) {
                throw new \InvalidArgumentException($res->error, 400);
            }
            
            return true;

        }

        return false;
    }


    public function ResetPassword(string $code, string $newPassword): bool
    {
        $confirmation = Confirmations::LoadByMember(Confirmation::PropertyReset, $this->token);
        if (!$confirmation) {
            return false;
        }

        if ($confirmation->code !== $code) {
            throw new InvalidArgumentException('Invalid code', 400);
        }

        try {

            $res = $confirmation->Delete();
            if( $res->error ) {
                throw new \InvalidArgumentException($res->error, 400);
            }
            

            $this->password = $newPassword;

            if( ($res = $this->Save(true)) !== true) {
                throw new \InvalidArgumentException($res->error, 400);
            }

        } catch (Throwable $e) {
            throw new InvalidArgumentException($e->getMessage(), 405);
        }

        return true;
    }

    public function ConfirmLogin(string $code): bool
    {
        $confirmation = Confirmations::LoadByMember(Confirmation::PropertyLogin, $this->token);
        if (!$confirmation) {
            return false;
        }

        return $confirmation->code === $code;

    }


    public function ExportForUserInterface($exportFullData = false): array
    {
        $arr = $this->ToArray(true);
        unset($arr['id']);
        unset($arr['datecreated']);
        unset($arr['datemodified']);
        unset($arr['password']);
        $arr['gender'] = $arr['gender']['value'] ?? $arr['gender'];
        $arr['birthdate'] = $arr['birthdate'] ? $arr['birthdate']->format('yyyy-MM-dd hh:mm:ss') : null;
        if ($exportFullData) {
            unset($arr['email_confirmed']);
            unset($arr['phone_confirmed']);
            $arr['fio'] = trim($arr['last_name'] . ' ' . $arr['first_name'] . ' ' . $arr['patronymic']);
            $arr['gender'] = $arr['gender']['value'] ?? 'male';
        }
        return $arr;
    }

}