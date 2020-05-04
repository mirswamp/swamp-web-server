<?php
/******************************************************************************\
|                                                                              |
|                               StringUtils.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for some basic string operations.              |
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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Strings;

class StringUtils
{
	//
	// string utility methods
	//

	public static function startsWith(?string $haystack, ?string $needle) {
		if (!$haystack || !$needle) {
			return false;
		}
		
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}

	public static function endsWith(?string $haystack, ?string $needle) {
		if (!$haystack || !$needle) {
			return false;
		}

		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
	}

	public static function contains(?string $haystack, ?string $needle) {
		return strpos($haystack, $needle) !== false;
	}

	//
	// url encode / decode methods
	//

	public static function urldecodeAll(array $strings) {
		$decoded = [];
		for ($i = 0; $i < count($strings); $i++) {
			array_push($decoded, urldecode($strings[$i]));
		}
		return $decoded;
	}

	public static function urlencodeAll(array $strings) {
		$encoded = [];
		for ($i = 0; $i < count($strings); $i++) {
			array_push($encoded, urlencode($strings[$i]));
		}
		return $encoded;
	}
}
