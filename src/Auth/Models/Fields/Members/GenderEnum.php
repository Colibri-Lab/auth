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
	/** 
	 * @ru: Мужской
	 * @en: Male
	 * @it: Maschio
	 * @hy: Արական
	 */
	case Male = 'male';
	/** 
	 * @ru: Женский
	 * @en: Female
	 * @it: Femmina
	 * @hy: իգական
	 */
	case Female = 'female';	
	# endregion Properties;

}