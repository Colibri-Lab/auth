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
 * @property string|null $first_name #{auth-storages-members-fields-first_name-desc;Имя}
 * @property string|null $last_name #{auth-storages-members-fields-last_name-desc;Фамилия}
 * @property string|null $patronymic #{auth-storages-members-fields-patronymic-desc;Отчество}
 * @property DateField|null $birthdate #{auth-storages-members-fields-birthdate-desc;Дата рождения}
 * @property ValueField|null $gender #{auth-storages-members-fields-gender-desc;Пол}
 * @property string|null $role #{auth-storages-members-fields-role-desc;Роль}
 * @property bool|null $email_confirmed #{auth-storages-members-fields-email_confirmed-desc;Почта подтверждена}
 * @property bool|null $phone_confirmed #{auth-storages-members-fields-phone_confirmed-desc;Телефон подтвержден}
 * endregion Properties;
 */
class Member extends BaseModelDataRow {

    const PasswordKey = 'AAAAB3NzaC1yc2EAAAADAQABAAABgQDIhRKlVdp8GPQzi9Yeje8B81qk5fFW3iC4xCu0HvuxvrnDHT5368odWBo3DPqQzRPhaGmZiDKYNRZnODGXiyNJwYEieZtAIt/pnLB1e5xUJJculhcgpicOPSBGGpAUUmroYaT0+K19aO5FIfOtmb5hY+Bkq9po0XSODhcnHZXntPBFOWyLdqkB2LB4jJPNUavhQDXOUqCwL/QFWPblPSbNILUSdImgWr41gSO5ISdvPMrfoAy3zUPuLKkge5l/KEu1Ga4IXMVI1YcKt7+ho1JHaDBTAmlfhJ8T1L+RgRElKPRCQ0zfV6SZoVK2X/uovNu0P+oB5WVfEkVQOQs/yPVPLJD9Ink54WwNLpZKz152DhdeHW+TA3UPwunQkciubJai85sV0Ask5q4vfUtqHAgsTuoNIYuZ6PotH4fU4JzyexhKA9UZOdG8qDWwueYrjmFINLrmMIQcHnf/yQdEprVpeThfZlU2PKp29/MdVx/5T4fhNd82xy1nLCqLbvg4T9U=';
    
    
	# region Consts:
	/** #{auth-storages-members-fields-gender-values-male;Мужской} */
	public const GenderMale = 'male';
	/** #{auth-storages-members-fields-gender-values-female;Женский} */
	public const GenderFemale = 'female';
	# endregion Consts;

    public function ExportForUserInterface(): array
    {
        $arr = $this->ToArray(true);
        unset($arr['id']);
        unset($arr['datecreated']);
        unset($arr['datemodified']);
        unset($arr['password']);
        $arr['gender'] = $arr['gender']['value'] ?? $arr['gender'];
        $arr['birthdate'] = $arr['birthdate']->format('yyyy-MM-dd hh:mm:ss');
        return $arr;
    }

    public function setPropertyEmail(string $value): void
    {
        $this->_data['members_email'] = $value;
        $this->_data['members_token'] = md5($value);
    }

    public function setPropertyPassword(string $value): void
    {
        if(($strength = $this->_checkPasswordStrength($value)) < 20) {
            throw new InvalidArgumentException('Password strength must be at least 20%, you got ' . $strength . '%');
        }
        $this->_data['members_password'] = md5(Crypt::Encrypt(self::PasswordKey, $value));
    }

    public function Authorize(string $password): bool 
    {
        return $this->password === md5(Crypt::Encrypt(self::PasswordKey, $password));
    }

    private function _checkPasswordStrength(string $password): float
    {
    
        if (strlen($password) < 8) {
            return 0;
        }

        $lc_pass = strtolower($password);
        $denum_pass = strtr($lc_pass,'5301!','seoll');
        $lc_email = strtolower($this->email);
        $lc_email = explode('@', $lc_email)[0];
    
        if (($lc_pass == $lc_email) || ($lc_pass == strrev($lc_email)) ||
            ($denum_pass == $lc_email) || ($denum_pass == strrev($lc_email))) {
            return 0;
        }
    
        // count how many lowercase, uppercase, and digits are in the password 
        $uc = 0; $lc = 0; $num = 0; $other = 0;
        for ($i = 0, $j = strlen($password); $i < $j; $i++) {
            $c = substr($password, $i, 1);
            if (preg_match('/^[[:upper:]]$/',$c)) {
                $uc++;
            } elseif (preg_match('/^[[:lower:]]$/',$c)) {
                $lc++;
            } elseif (preg_match('/^[[:digit:]]$/',$c)) {
                $num++;
            } else {
                $other++;
            }
        }
    
        $max = $j - 2;

        $uc = $uc * 100 / $max;
        $lc = $lc * 100 / $max;
        $num = $num * 100 / $max;
        $other = $other * 100 / $max;

        $percents = [$uc, $lc, $num, $other];
        return array_sum($percents) / count($percents);
    }

    public function SendConfirmationMessage(string $property): bool 
    {
        $confirmation = Confirmations::LoadByMember($property, $this->token);
        if(!$confirmation) {
            $confirmation = Confirmations::LoadEmpty();
            $confirmation->property = $property;
            $confirmation->member = $this->token;
            $confirmation->code = RandomizationHelper::Numeric(6);
            $confirmation->Save();
        }

        return $confirmation->Send();
    }

    public function ConfirmProperty(string $property, string $code): bool
    {
        $confirmation = Confirmations::LoadByMember($property, $this->token);
        if($confirmation->code === $code) {
            $this->{$property.'_confirmed'} = true;
            $this->Save();
            $confirmation->Delete();
            return true;
        }
        return false;
    }

}