<?php
/******************************************************************************\
|                                                                              |
|                                   Uuid.php                                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for creating universally unique                |
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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Uuids;

/*
	This code is from the following source:
	http://rommelsantor.com/clog/2012/02/23/generate-uuid-in-php/
*/

class Uuid {  
	
	/** 
	 * Generates version 1: MAC address 
	 */  
	public static function v1() {  
		if (!function_exists('uuid_create'))  
			return false;  
	
		uuid_create(&$context);  
		uuid_make($context, UUID_MAKE_V1);  
		uuid_export($context, UUID_FMT_STR, &$uuid);  
		return trim($uuid);  
	}  
	
	/** 
	 * Generates version 3 UUID: MD5 hash of URL 
	 */  
	public static function v3($i_url) {  
		if (!function_exists('uuid_create'))  
			return false;  
	
		if (!strlen($i_url))  
			$i_url = self::v1();  
	
		uuid_create(&$context);  
		uuid_create(&$namespace);  
	
		uuid_make($context, UUID_MAKE_V3, $namespace, $i_url);  
		uuid_export($context, UUID_FMT_STR, &$uuid);  
		return trim($uuid);  
	}  
	
	/** 
	 * Generates version 4 UUID: random 
	 */  
	public static function v4() {  
		if (!function_exists('uuid_create'))  
			return false;  
	
		uuid_create(&$context);  
	
		uuid_make($context, UUID_MAKE_V4);  
		uuid_export($context, UUID_FMT_STR, &$uuid);  
		return trim($uuid);  
	}  
	
	/** 
	 * Generates version 5 UUID: SHA-1 hash of URL 
	 */  
	public static function v5($i_url) {  
		if (!function_exists('uuid_create'))  
			return false;  
	
		if (!strlen($i_url))  
			$i_url = self::v1();  
	
		uuid_create(&$context);  
		uuid_create(&$namespace);  
	
		uuid_make($context, UUID_MAKE_V5, $namespace, $i_url);  
		uuid_export($context, UUID_FMT_STR, &$uuid);  
		return trim($uuid);  
	}  
}