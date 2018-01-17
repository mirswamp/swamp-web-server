<?php
/******************************************************************************\
|                                                                              |
|                               LdapSanitize.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for sanitizing information that is             |
|        read from or written to an Ldap database.                             |
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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Sanitization;

/**
 * Sanitizes ldap search strings.
 * See rfc2254
 * @link http://www.faqs.org/rfcs/rfc2254.html
 * @since 1.5.1 and 1.4.5
 * @param string $string
 * @return string sanitized string
 * @author Squirrelmail Team
 */

class LdapSanitize  {
	static function escapeQueryValue($string) {
		$sanitized = [
			'\\'   => '\5c',
			'*'    => '\2a',
			'('    => '\28',
			')'    => '\29',
			"\x00" => '\00'
		];

		return str_replace(array_keys($sanitized), array_values($sanitized), $string);
	}
}

