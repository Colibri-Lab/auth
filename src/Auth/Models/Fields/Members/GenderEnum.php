<?php

namespace App\Modules\Auth\Models\Fields\Members;

/**
 * Enum Пол
 * @author <author name and email>
 * @package App\Modules\Auth\Models\Fields\Members
 */
enum GenderEnum:string 
{

    public const JsonSchema = [
        'type' => 'string',
        'enum' => [
            # region Values:
			"male",
			"female"
            # endregion Values;
        ]
    ];

    # region Properties:
	/** Мужской */
	case Male = 'male';
	/** Женский */
	case Female = 'female';
    # endregion Properties;

}