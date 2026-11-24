<?php

namespace App\Modules\Auth\Models;

# region Uses:
use App\Modules\Auth\Models\Fields\Members\GenderEnum;
use App\Modules\Auth\Models\Fields\Members\RoleArrayField;
use Colibri\Data\Storages\Fields\ArrayField;
use Colibri\Data\Storages\Fields\DateField;
use Colibri\Data\Storages\Fields\DateTimeField;
use Colibri\Data\Storages\Fields\ObjectField;
use Colibri\Data\Storages\Fields\ValueField;
# endregion Uses;
use Colibri\Data\Storages\Models\DataRow as BaseModelDataRow;
use Colibri\Encryption\Crypt;
use Psr\Log\InvalidArgumentException;
use Colibri\Common\RandomizationHelper;
use Throwable;
use App\Modules\Auth\Module;
use Colibri\App;
use Colibri\Data\SqlClient\QueryInfo;
use Colibri\IO\FileSystem\File;

/**
 * Представление строки в таблице в хранилище Пользователи
 * @class
 * @memberof App\Modules\Auth\Models
 * @extends BaseModelDataRow
 * 
 * region Properties:
 * @property int $id ID строки
 * @property DateTimeField $datecreated Дата создания строки
 * @property DateTimeField $datemodified Дата последнего обновления строки
 * @property DateTimeField $datedeleted Дата удаления строки (если включно мягкое удаление)
 * @property string $token Токен пользователя
 * @property string|null $login Логин пользователя
 * @property string $email Эл. адрес пользователя
 * @property string $phone Телефон
 * @property string $password Пароль
 * @property string|null $first_name Имя
 * @property string|null $last_name Фамилия
 * @property string|null $patronymic Отчество
 * @property DateField|null $birthdate Дата рождения
 * @property GenderEnum|ValueField|ValueField|null $gender Пол
 * @property ObjectField|null $avatar Аватар
 * @property RoleArrayField|null $role Assigned application roles.
 * @property bool $email_confirmed Почта подтверждена
 * @property bool $phone_confirmed Телефон подтвержден
 * @property bool $blocked Заблокирован (удален)
 * @property bool $two_factor Двухфакторная аутентификация
 * @property string|null $two_factor_application Приложение аутентификации (код для приложения)
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
			'login' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 256, ] ] ],
			'email' => ['type' => 'string', 'maxLength' => 255, ],
			'phone' => ['type' => 'string', 'maxLength' => 50, ],
			'password' => ['type' => 'string', 'maxLength' => 128, ],
			'first_name' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			'last_name' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			'patronymic' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 255, ] ] ],
			'birthdate' => [ 'anyOf' => [ ['type' => ['string', 'null'], 'format' => 'date'], ['type' => ['string', 'null'], 'maxLength' => 0] ] ],
			'gender' => [ 'oneOf' => [ [ 'type' => 'null' ], GenderEnum::JsonSchema ] ], 
			'avatar' => [  'oneOf' => [ ObjectField::JsonSchema, [ 'type' => 'null'] ] ],
			'role' => [  'oneOf' => [ RoleArrayField::JsonSchema, [ 'type' => 'null'] ] ],
			'email_confirmed' => ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],],
			'phone_confirmed' => ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],],
			'blocked' => ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],],
			'two_factor' => ['type' => ['boolean','number'], 'enum' => [true, false, 0, 1],],
			'two_factor_application' => [ 'oneOf' => [ [ 'type' => 'null'], ['type' => 'string', 'maxLength' => 256, ] ] ],
			# endregion SchemaProperties;

        ]
    ];

    # region Consts:
	/** Мужской */
	public const GenderMale = 'male';
	/** Женский */
	public const GenderFemale = 'female';
	# endregion Consts;


    public function setPropertyLogin(string $value): void
    {
        $this->_data['members_login'] = $value;
        $this->_data['members_token'] = md5($value);
    }

    /**
     * Normalizes legacy role names and Array form values.
     * @public
     * @static
     * @param mixed $value A role name or an array of role objects.
     * @return array<array{role: string}> Unique assigned roles.
     */
    public static function NormalizeRoles(mixed $value): array
    {
        if ($value instanceof ArrayField) {
            $value = $value->ToArray(true);
        } elseif (is_string($value)) {
            if (str_starts_with(ltrim($value), '[')) {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } elseif ($value === '' || $value === 'json_array()') {
                $value = [];
            } else {
                $value = [['role' => $value]];
            }
        } elseif ($value === null) {
            $value = [];
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('Expected an array of role objects', 400);
        }
        $roles = [];
        foreach ($value as $item) {
            if (is_object($item)) {
                $item = (array)$item;
            }
            $name = is_array($item) ? ($item['role'] ?? null) : null;
            if (!is_string($name) || $name === '' || mb_strlen($name) > 255) {
                throw new \InvalidArgumentException('Invalid member role name', 400);
            }
            $roles[$name] = ['role' => $name];
        }
        return array_values($roles);
    }

    /**
     * Assigns roles while retaining compatibility with single-role callers.
     * @public
     * @param mixed $value A legacy role name or Array form value.
     * @return void
     */
    public function setPropertyRole(mixed $value): void
    {
        $roles = self::NormalizeRoles($value);
        $knownRoles = Applications::LoadRoles();
        foreach ($roles as $role) {
            if (!isset($knownRoles[$role['role']])) {
                throw new \InvalidArgumentException('Unknown member role: ' . $role['role'], 400);
            }
        }
        $roles = json_encode($roles, JSON_THROW_ON_ERROR);
        $this->_typeExchange('set', 'members_role', $roles);
        $this->_changed = true;
    }

    /**
     * Returns the assigned role names.
     * @public
     * @return string[] Unique role names.
     */
    public function GetRoleNames(): array
    {
        return array_column(self::NormalizeRoles($this->role), 'role');
    }

    /**
     * Checks whether a particular role is assigned.
     * @public
     * @param string $role Application role name.
     * @return bool Whether the member has the role.
     */
    public function HasRole(string $role): bool
    {
        return in_array($role, $this->GetRoleNames(), true);
    }

    /**
     * Finds the most privileged configured role for priority comparisons.
     * @public
     * @param Application $application Application defining the roles.
     * @return ObjectField|null Role with the lowest priority number.
     */
    public function GetApplicationRole(Application $application): ?ObjectField
    {
        $names = $this->GetRoleNames();
        $result = null;
        foreach ($application->params->roles as $role) {
            if (in_array($role->name, $names, true) && ($result === null || $role->priority < $result->priority)) {
                $result = $role;
            }
        }
        return $result;
    }

    /**
     * Hashes a newly assigned password after checking its strength.
     * @public
     * @param string $value Plaintext password.
     * @return void
     */
    public function setPropertyPassword(string $value): void
    {
        if (($strength = self::CheckPasswordStrength($this->email, $value)) < 40) {
            throw new InvalidArgumentException(sprintf('#{auth-errors-member-password-strength-not-match}', $strength));
        }
        $this->_data['members_password'] = password_hash($value, PASSWORD_ARGON2ID);
    }

    /**
     * Verifies a current or legacy password hash without changing the member.
     * @public
     * @param string $password Plaintext password.
     * @return bool Whether the password matches.
     */
    public function Authorize(string $password): bool
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $this->password)) {
            return hash_equals($this->password, md5(Crypt::Encrypt(self::PasswordKey, $password)));
        }
        return password_verify($password, $this->password);
    }

    /**
     * Upgrades an already verified password after successful authentication.
     * @public
     * @param string $password Plaintext password accepted by Authorize().
     * @return void
     */
    public function UpgradePasswordHash(string $password): void
    {
        if (!password_needs_rehash($this->password, PASSWORD_ARGON2ID)) {
            return;
        }

        // Existing passwords must not be rejected by the current strength policy.
        $this->_data['members_password'] = password_hash($password, PASSWORD_ARGON2ID);
        if (($res = $this->Save(true)) !== true) {
            throw new \InvalidArgumentException($res->error, 400);
        }
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
        $app = Module::Instance()->application;
        if (!$app) {
            throw new InvalidArgumentException('Application not found', 404);
        }

        $confirmation = Confirmations::LoadByMember($property, $this->token);
        if (!$confirmation) {
            $confirmation = Confirmations::LoadEmpty();
            $confirmation->property = $property;
            $confirmation->member = $this->token;
            $confirmation->valuebefore = $this->{$property};
        }

        $confirmation->code = RandomizationHelper::Numeric(6);
        if (($res = $confirmation->Save(true)) !== true) {
            throw new InvalidArgumentException($res->error, 500);
        }

        return $confirmation->Send($value, $app->params->proxies);

    }

    public function SendResetMessage(): bool
    {

        /** @var Application|null $app */
        $app = Module::Instance()->application;
        if (!$app) {
            throw new InvalidArgumentException('Application not found', 404);
        }

        $confirmation = Confirmations::LoadByMember(Confirmation::PropertyReset, $this->token);
        if (!$confirmation) {
            $confirmation = Confirmations::LoadEmpty();
            $confirmation->property = Confirmation::PropertyReset;
            $confirmation->member = $this->token;
            $confirmation->valuebefore = $this->email;
        }

        $confirmation->code = RandomizationHelper::Numeric(6);
        if (($res = $confirmation->Save(true)) !== true) {
            throw new InvalidArgumentException($res->error, 500);
        }

        return $confirmation->Send(null, $app->params->proxies);
    }

    public function SendTwoFactorAuthorizationMessage(): bool
    {
        /** @var Application|null $app */
        $app = Module::Instance()->application;
        if (!$app) {
            throw new InvalidArgumentException('Application not found', 404);
        }

        $confirmation = Confirmations::LoadByMember(Confirmation::PropertyLogin, $this->token);
        if (!$confirmation) {
            $confirmation = Confirmations::LoadEmpty();
            $confirmation->property = Confirmation::PropertyLogin;
            $confirmation->member = $this->token;
            $confirmation->valuebefore = $this->email;
        }

        $confirmation->code = RandomizationHelper::Numeric(6);
        if (($res = $confirmation->Save(true)) !== true) {
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

        if (($res = $this->Save(true)) !== true) {
            throw new InvalidArgumentException($res->error, 500);
        }

        return true;
    }

    public function ConfirmProperty(string $property, string $code): bool
    {
        $confirmation = Confirmations::LoadByMember($property, $this->token);
        if ($confirmation && $confirmation->code === $code && $confirmation->valuebefore === $this->{$property}) {
            $this->{$property . '_confirmed'} = true;

            if (($res = $this->Save(true)) !== true) {
                throw new \InvalidArgumentException($res->error, 400);
            }

            $confirmation->Delete();
            return true;

        }

        throw new \InvalidArgumentException('Invalid code', 400);
    }

    public function UpdateIdentify(string $property, string $code, string $value): bool
    {
        $confirmation = Confirmations::LoadByMember($property, $this->token);
        if ($confirmation && $confirmation->code === $code && $confirmation->valuebefore === $this->{$property}) {
            $this->{$property . '_confirmed'} = true;
            $this->$property = $value;

            if (($res = $this->Save(true)) !== true) {
                throw new \InvalidArgumentException($res->error, 400);
            }

            $confirmation->Delete();
            return true;
        }
        return false;
    }

    public function UpdateProfile(string $firstName, string $lastName, ?string $patronymic = null, ?string $gender = null, DateTimeField|string|null $birthdate = null, array|object|null $avatar = null): bool
    {

        if ($gender && !in_array($gender, [self::GenderMale, self::GenderFemale, null])) {
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
        $this->avatar = $avatar;

        if (($res = $this->Save(true)) !== true) {
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

        if (($res = $this->Save(true)) !== true) {
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

        if (($res = $this->Save(true)) !== true) {
            throw new \InvalidArgumentException($res->error, 400);
        }
        return true;
    }

    /**
     * Replaces assigned roles after validating every application role.
     * @public
     * @param string|array $newRole Legacy role name or Array form value.
     * @return bool Whether the roles were saved.
     */
    public function UpdateRole(string|array $newRole): bool
    {
        $roles = self::NormalizeRoles($newRole);
        $application = Module::Instance()->application;
        if (!$application) {
            throw new \InvalidArgumentException('Application not found', 404);
        }
        foreach ($roles as $role) {
            if (!$application->CheckRole($role['role'])) {
                throw new \InvalidArgumentException('#{auth-errors-member-role-not-exists}', 400);
            }
        }
        $this->role = $roles;
        if (($res = $this->Save(true)) !== true) {
            throw new \InvalidArgumentException($res->error, 400);
        }
        return true;
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
            if ($res->error) {
                throw new \InvalidArgumentException($res->error, 400);
            }


            $this->password = $newPassword;

            if (($res = $this->Save(true)) !== true) {
                throw new \InvalidArgumentException($res->error, 400);
            }

            // clear all sessions for this member to force logout after password reset
            $sessions = Sessions::LoadByMember($this);
            foreach ($sessions as $s) {
                /** @var \App\Modules\Auth\Models\Session $s */
                $s->member = null;
                $s->Save(true);
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
        $this->_generateOpenSSLKey();
        $arr = $this->ToArray(true);
        unset($arr['id']);
        unset($arr['datecreated']);
        unset($arr['datemodified']);
        unset($arr['datedeleted']);
        unset($arr['password']);
        $arr['two_factor_application'] = !!$arr['two_factor_application'];
        $arr['public'] = $this->_getPublicKey();
        $arr['public_local'] = $this->_getLocalPublicKey();
        if ($exportFullData) {
            $arr['gender'] = $arr['gender']['value'] ?? $arr['gender'];
            $arr['birthdate'] = $arr['birthdate'] ? $arr['birthdate']->format('yyyy-MM-dd hh:mm:ss') : null;
            $arr['fio'] = trim($arr['last_name'] . ' ' . $arr['first_name'] . ' ' . $arr['patronymic']);
            $arr['gender'] = $arr['gender']['value'] ?? 'male';
        }
        return $arr;
    }

    public function Encrypt(string $message): string
    {
        
        $pubKey = $this->_getPublicKey();
        $aesKey = openssl_random_pseudo_bytes(32); // 256-bit AES key
        $iv = openssl_random_pseudo_bytes(16);     // 16-byte IV
        $encryptedData = openssl_encrypt($message, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
        openssl_public_encrypt($aesKey, $encryptedKey, $pubKey, OPENSSL_PKCS1_OAEP_PADDING);
        return base64_encode($encryptedKey) . '::' . base64_encode($iv) . '::' . base64_encode($encryptedData);

    }

    public function Decrypt(string $message): string
    {
        $privKey = $this->_getPrivateKey();
        [$b64EncryptedKey, $b64Iv, $b64EncryptedData] = explode('::', $message);
        $encryptedKey = base64_decode($b64EncryptedKey);
        $iv = base64_decode($b64Iv);
        $encryptedData = base64_decode($b64EncryptedData);
        openssl_private_decrypt($encryptedKey, $aesKey, $privKey, OPENSSL_PKCS1_OAEP_PADDING);
        return openssl_decrypt($encryptedData, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
    }

    private function _getPublicKey(): string
    {
        $runtime = App::$appRoot . App::$config->Query('runtime')->GetValue() . 'ssl/';
        $pathPub = $runtime . substr($this->token, 0, 4) . '/' . substr($this->token, -4) . '/' . $this->token . '.pub';
        return File::Read($pathPub);
    }

    private function _getLocalPublicKey(): ?string
    {
        $runtime = App::$appRoot . App::$config->Query('runtime')->GetValue() . 'ssl/';
        $pathPub = $runtime . substr($this->token, 0, 4) . '/' . substr($this->token, -4) . '/' . $this->token . '.local.pub';
        return File::Read($pathPub);
    }

    private function _getPrivateKey(): string
    {
        $runtime = App::$appRoot . App::$config->Query('runtime')->GetValue() . 'ssl/';
        $pathPriv = $runtime . substr($this->token, 0, 4) . '/' . substr($this->token, -4) . '/' . $this->token;
        return File::Read($pathPriv);
    }

    private function _generateOpenSSLKey(bool $force = false): void
    {
        $runtime = App::$appRoot . App::$config->Query('runtime')->GetValue() . 'ssl/';
        $pathPriv = $runtime . substr($this->token, 0, 4) . '/' . substr($this->token, -4) . '/' . $this->token;
        $pathPub = $runtime . substr($this->token, 0, 4) . '/' . substr($this->token, -4) . '/' . $this->token . '.pub';
        if(!$force && File::Exists($pathPub) && File::Exists($pathPriv)) {
            return;
        }

        $config = [
            "digest_alg" => "sha512",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privKey);
        $pubKey = openssl_pkey_get_details($res);
        $pubKey = $pubKey["key"];

        
        File::Write($pathPriv, $privKey, true);
        File::Write($pathPub, $pubKey, true);

    }

    public function SetLocalPublicKey(string $publicKe): void
    {
        $runtime = App::$appRoot . App::$config->Query('runtime')->GetValue() . 'ssl/';
        $pathPub = $runtime . substr($this->token, 0, 4) . '/' . substr($this->token, -4) . '/' . $this->token . '.local.pub';
        File::Write($pathPub, $publicKe, true);
    }

}