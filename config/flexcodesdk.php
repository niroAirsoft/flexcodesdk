<?php

return [
	 /*
     * Set configuration for flexcode
     */

    'devices' => array(
        // add device here
        array(
            'name' 	=> env('FLEXCODE_DEVICE'),
            'sn' 	=> env('FLEXCODE_SN'),
            'vc'	=> env('FLEXCODE_VC'),
            'ac' 	=> env('FLEXCODE_AC'),
            'vkey' 	=> env('FLEXCODE_VKEY'),
        ),
    ),

    'fingerprint_storage' => array(
        'table' => env('FLEXCODE_FINGERPRINT_TABLE', 'demo_finger'),
        'user_column' => env('FLEXCODE_FINGERPRINT_USER_COLUMN', 'user_id'),
        'data_column' => env('FLEXCODE_FINGERPRINT_DATA_COLUMN', 'finger_data'),
        'verify_column' => env('FLEXCODE_FINGERPRINT_VERIFY_COLUMN', 'verify'),
        'deleted_at_column' => env('FLEXCODE_FINGERPRINT_DELETED_AT_COLUMN', 'deleted_at'),
    ),

	'redirect_after_register' => env('FLEXCODE_REDIRECT_AFTER_REGISTER, '/'),
	'redirect_after_verify' => env('FLEXCODE_REDIRECT_AFTER_VERIFY', '/'),
];
