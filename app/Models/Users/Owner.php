<?php
/******************************************************************************\
|                                                                              |
|                                  Owner.php                                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an owner (which is similar to a user          |
|        model but reports less information).                                  |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;


use App\Models\BaseModel;
use App\Models\Users\User;


class Owner extends BaseModel {

	// database attributes
	//
	protected $table = 'user';
	protected $primaryKey = 'user_id';

	// mass assignment policy
	//
	protected $fillable = [
		'user_uid',
		'first_name', 
		'last_name', 
		'preferred_name', 
		'email'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'user_uid',
		'first_name', 
		'last_name', 
		'preferred_name', 
		'email'
	];

	//
	// static methods
	//

	public static function getIndex($userUid) {
		$user = User::getIndex($userUid);

		// assign subset of user attributes
		//
		if ($user) {
			$owner = new Owner;
			$owner->user_uid = $user->user_uid;
			$owner->first_name = $user->first_name;
			$owner->last_name = $user->last_name;
			$owner->preferred_name = $user->preferred_name;
			$owner->email = $user->email;
			return $owner;
		}
	}
}
