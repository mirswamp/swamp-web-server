<?php
/******************************************************************************\
|                                                                              |
|                               LinkedAccount.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a linking between a user account and          |
|        an external authentication / identity provider.                       |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Config;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;
use App\Models\Users\LinkedAccountProvider;

class LinkedAccount extends CreateStamped {

	// database attributes
	//
	protected $table = 'linked_account';
	protected $primaryKey = 'linked_account_id';

	// mass assignment policy
	//
	protected $fillable = [
		'user_uid',
		'user_external_id', 
		'linked_account_provider_code', 
		'enabled_flag',

		// timestamp attributes
		//
		'create_date',
		'create_user',
		'update_date',
		'update_user'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'linked_account_id',
		'user_uid',
		'user_external_id',
		'enabled_flag',
		'title',
		'description',

		// timestamp attributes
		//
		'create_date'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'title',
		'description'
	];

	// attribute types
	//
	protected $casts = [
		'enabled_flag' => 'boolean',
		'create_date' => 'datetime'
	];

	//
	// accessor methods
	//

	public function getTitleAttribute() {
		return LinkedAccountProvider::where('linked_account_provider_code','=',$this->linked_account_provider_code)->first()->title;
	}

	public function getDescriptionAttribute(){
		return LinkedAccountProvider::where('linked_account_provider_code','=',$this->linked_account_provider_code)->first()->description;
	}
}
