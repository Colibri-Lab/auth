<?php

namespace App\Modules\Auth\Models\Fields\Confirmations;

/**
 * Enum property values
 * @enum
 */
enum PropertyEnum:string 
{
    /**
     * Json schema for the model properties
     * @public
     * @const
     * @var array
     */
    public const JsonSchema = [
        'type' => 'string',
        'enum' => [
            # region Values:
			"email",
			"phone",
			"reset",
			"login"
            # endregion Values;
        ]
    ];

    # region Properties:
	/** 
	 * @ru: Эл. адрес
	 * @en: Email address
	 * @it: 
	 * @hy: 
	 * @const string
	 */
	case Email = 'email';
	/** 
	 * @ru: Телефон
	 * @en: Telephone
	 * @it: 
	 * @hy: 
	 * @const string
	 */
	case Phone = 'phone';
	/** 
	 * @ru: Восстановление пароля
	 * @en: Password Recovery
	 * @it: 
	 * @hy: 
	 * @const string
	 */
	case Reset = 'reset';
	/** 
	 * @ru: Вход/Двух-факторная авторизация
	 * @en: Login/Two-factor authorization
	 * @it: 
	 * @hy: 
	 * @const string
	 */
	case Login = 'login';
    # endregion Properties;
    
    /**
     * Returns string representation of value
     * @returns string
     * @public
     */
	public function ToString(): string {
        return (string)$this->value;
    }
}