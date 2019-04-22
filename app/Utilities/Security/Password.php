<?php
/******************************************************************************\
|                                                                              |
|                                Password.php                                  |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This is a utility for performing password encryption.                 |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Security;

class Password
{
	//
	// static querying methods
	//

	public static function getEncrypted($password, $encryption, $hash='') {
		switch (strtoupper($encryption)) {

			case 'NONE': 
				return $password;
				break;

			case 'MD5':
				return '{MD5}' . base64_encode(md5($password, true));
				break;

			case 'SHA':
			case 'SHA1':
				return '{' . $encryption . '}' . base64_encode(sha1($password, true));
				break;

			case 'SSHA':
				$salt = substr(base64_decode(substr($hash, 6)), 20);
				return '{SSHA}' . base64_encode(sha1($password . $salt, true) . $salt);
				break;

			case 'BCRYPT':
			default:
				return '{BCRYPT}' . password_hash($password, PASSWORD_BCRYPT);
				break;
		}
	}

	public static function getEncryption($hash) {

		// if no encryption specified in curly brackets, must be plaintext
		//
		if ($hash{0} != '{') {
			return 'none';
		}

		// find substring between curly brackets
		//
		$i = 1;
		while ($hash{$i} != '}' && $i < strlen($hash)) {
			$i++;
		}
		return substr($hash, 1, $i - 1);
	}

	public static function isValid($password, $hash) {

		// no password
		//
		if ($hash == '') {
			return false;
		}

		// find encryption method
		//
		$encryption = self::getEncryption($hash);

		switch (strtoupper($encryption)) {

			case 'NONE':
				return $password == $hash;

			case 'CRYPT':
				return (crypt($password, substr($hash, 7)) == substr($hash, 7));

			case 'MD5':
			case 'SHA':
			case 'SHA1':
			case 'SSHA':
				$encryptedPassword = self::getEncrypted($password, $encryption, $hash);
				break;

			case 'BCRYPT':
				return password_verify($password, substr($hash, 8));
				break;

			default:
				echo "Unsupported password hash format: " . $hash . ". ";
				return false;
		}

		return ($hash == $encryptedPassword);
	}
}
