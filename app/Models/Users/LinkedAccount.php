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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Config;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;
use App\Models\Users\LinkedAccountProvider;

class LinkedAccount extends CreateStamped {

	/**
	 * database attributes
	 */
	protected $table = 'linked_account';
	protected $primaryKey = 'linked_account_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'user_uid',
		'user_external_id', 
		'linked_account_provider_code', 
		'enabled_flag',
		'create_date',
		'create_user',
		'update_date',
		'update_user'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'linked_account_id',
		'user_uid',
		'enabled_flag',
		'create_date',
		'title',
		'description'
	);

	protected $appends = array(
		'title',
		'description'
	);

	public function getTitleAttribute(){
		return LinkedAccountProvider::where('linked_account_provider_code','=',$this->linked_account_provider_code)->first()->title;
	}

	public function getDescriptionAttribute(){
		return LinkedAccountProvider::where('linked_account_provider_code','=',$this->linked_account_provider_code)->first()->description;
	}
	
}
