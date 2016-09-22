<?php
/******************************************************************************\
|                                                                              |
|                                  Filename.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for handling (sanitizing) filenames.           |
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

namespace App\Utilities\Files;

class Filename {

	/**
	 * Sanitizes a filename replacing whitespace with dashes
	 *
	 * Removes special characters that are illegal in filenames on certain
	 * operating systems and special characters requiring special escaping
	 * to manipulate at the command line. Replaces spaces and consecutive
	 * dashes with a single dash. Trim period, dash and underscore from beginning
	 * and end of filename.
	 *
	 * @since 2.1.0
	 *
	 * @param string $filename The filename to be sanitized
	 * @return string The sanitized filename
	 */
	public static function sanitize($filename) {
		$replace_chars = array(
			'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
			'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
			'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
			'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
			'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
			'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
			'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f'
		);
		$f = strtr($filename, $replace_chars);

		// convert & to "and", @ to "at", and # to "number"
		//
		$f = preg_replace(array('/[\&]/', '/[\@]/', '/[\#]/'), array('-and-', '-at-', '-number-'), $f);
		$f = preg_replace('/[^(\x20-\x7F)]*/','', $f); // removes any special chars we missed
		$f = str_replace(' ', '-', $f); // convert space to hyphen
		$f = str_replace('\'', '', $f); // removes apostrophes
		$f = preg_replace('/[^\w\-\.]+/', '', $f); // remove non-word chars (leaving hyphens and periods)
		$f = preg_replace('/[\-]+/', '-', $f); // converts groups of hyphens into one
		return strtolower($f);
	}
}