<?php

namespace App\Modules\Auth\Models\Fields\Members;

/**
 * Enum Пол
 * @enum
 */
enum GenderEnum:string 
{

    /**
     * JsonSchema
     * @const array
     * @public
     */
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
	 * Male
	 * @const string
	 */
	case Male = 'male';
	/** 
	 * Female
	 * @const string
	 */
	case Female = 'female';	
	# endregion Properties;

}