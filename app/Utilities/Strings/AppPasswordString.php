<?php
/******************************************************************************\
|                                                                              |
|                          AppPasswordString.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for creating App Password strings.             |
|                                                                              |
|        Author(s): Terry Fleury                                               |
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

namespace App\Utilities\Strings;

class AppPasswordString
{
	/** 
	 * Return a new app password, which is a string consisting of 20 
	 * upper/lower case letters and/or digits, except for ambiguous
	 * characters such as '0', '1', and their look-alikes.
	 *
	 * @return A random string of 20 letters and numbers.
	 */
	static function create() {
	 	// App passwords consist of numbers and upper and lower case letters
		$validchars = array_merge(range('0','9'), range('A','Z'), range('a','z'));
		// Remove ambiguous characters, i.e., look like '0' and '1'
		$validchars = array_values(array_diff($validchars,
			['0','1','i','l','o','I','L','O']));
		$validcount = count($validchars);
		$retarray = [];

		for ($ch = 0; $ch < 20; $ch++) {
			$retarray[$ch] = $validchars[random_int(0,$validcount-1)];
		}

		return implode($retarray);
	}
}
