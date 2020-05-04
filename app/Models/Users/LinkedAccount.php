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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;
use App\Models\Users\LinkedAccountProvider;

class LinkedAccount extends CreateStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'linked_account';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'linked_account_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'user_uid',
		'user_external_id', 
		'linked_account_provider_code', 
		'enabled_flag',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'linked_account_id',
		'user_uid',
		'user_external_id',
		'enabled_flag',
		'title',
		'description',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'title',
		'description'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
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
