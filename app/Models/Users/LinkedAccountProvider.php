<?php
/******************************************************************************\
|                                                                              |
|                           LinkedAccountProvider.php                          |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a particular identity provider.               |
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

use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class LinkedAccountProvider extends CreateStamped
{
	// database attributes
	//
	protected $table = 'linked_account_provider';
	protected $primaryKey = 'linked_account_provider_code';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'linked_account_provider_code', 
		'title',
		'description',
		'enabled_flag',

		// timestamp attributes
		//
		'create_date',
		'update_date',

		// userstamp attributes
		//
		'create_user',
		'update_user'
	];

	// attribute types
	//
	protected $casts = [
		'enabled_flag' => 'boolean',
		'create_date' => 'datetime',
		'update_date' => 'datetime'
	];
}
