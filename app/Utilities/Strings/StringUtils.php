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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Strings;

class StringUtils {

	//
	// string utility methods
	//

	public static function startsWith($haystack, $needle) {
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}

	public static function endsWith($haystack, $needle) {	
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
	}
}
