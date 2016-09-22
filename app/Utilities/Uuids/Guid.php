<?php
/******************************************************************************\
|                                                                              |
|                                   Guid.php                                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for creating globally unique universal         |
|        identifiers.                                                          |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Uuids;

class Guid {
    static function create() {
        if (function_exists('com_create_guid')) {

            // Windows only - use built-in function
            //
            return com_create_guid();
		} elseif (function_exists('openssl_random_pseudo_bytes')) {

				// If OpenSSL support is compiled into PHP, use method suggested by 
				// http://stackoverflow.com/a/15875555
				//
				$data = openssl_random_pseudo_bytes(16);
				$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
				$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10
				return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		} else { 
				// If all else fails, default to mt_rand method as used in 
				// https://github.com/alixaxel/phunction/blob/master/phunction/Text.php
				//
				$result = array();
				for ($i = 0; $i < 8; ++$i) {
						switch ($i) {
								case 3:  $result[$i] = mt_rand(16384, 20479); break;
								case 4:  $result[$i] = mt_rand(32768, 49151); break;
								default: $result[$i] = mt_rand(0, 65535);     break;
						}
				}
				return vsprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', $result);
		}
    }
}
