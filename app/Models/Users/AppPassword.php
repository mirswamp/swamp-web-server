<?php
/******************************************************************************\
|                                                                              |
|                               AppPassword.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of app passwords. API documentation              |
|        can be found at https://goo.gl/Vqvn84 .                               |
|                                                                              |
|        Database table 'app_passords' schema:                                 |
+-------------------+--------------+------+-----+-------------------+-----------------------------+
| Field             | Type         | Null | Key | Default           | Extra                       |
+-------------------+--------------+------+-----+-------------------+-----------------------------+
| app_password_id   | int(11)      | NO   | PRI | null              | auto_increment              |
| app_password_uuid | varchar(45)  | NO   |     | null              |                             |
| user_uid          | varchar(127) | NO   |     | null              |                             |
| password          | varchar(127) | NO   |     | null              |                             |
| label             | varchar(63)  | YES  |     | null              |                             |
| create_date       | timestamp    | NO   |     | CURRENT_TIMESTAMP |                             |
| update_date       | timestamp    | NO   |     | CURRENT_TIMESTAMP | on update CURRENT_TIMESTAMP |
+-------------------+--------------+------+-----+-------------------+-----------------------------+
|                                                                              |
|        Author(s): Terry Fleury                                               |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\TimeStamps\TimeStamped;

class AppPassword extends TimeStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'app_passwords';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'app_password_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'app_password_uuid',
		'user_uid',
		'password',
		'label'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'app_password_uuid',
		'user_uid',
		'label'
	];

	/**
	 * Attempt to validate a password for a given user_uid. This is
	 * done by looping through all the app password hashes for the
	 * given user and seeing if any of them match. If so, return true,
	 * otherwise false.
	 *
	 * @param $user_uid The user UID of the given user to check.
	 * @param $password The user-entered password to check.
	 *
	 * @return True if $password validates against one of the user's
	 *         app password hashes. False otherwise.
	 */
	public static function validatePassword(string $user_uid, string $password): bool {
		$retval = false; // Assume app password is not valid

		// Get all of the user's app passwords
		$app_password_coll = AppPassword::where('user_uid', $user_uid)->get();
		// Iterate through the app passwords looking for a matching hash
		foreach ($app_password_coll as $app_pass) {
			if (password_verify($password,$app_pass->password)) {
				$retval = true;
				Log::info("App password authenticated.", [
					'user_uid' => $user_uid,
					'app_password_uuid' => $app_pass->app_password_uuid,
				]);
				break;
			}
		}

		return $retval;
	}
}
