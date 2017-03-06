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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Config;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class LinkedAccountProvider extends CreateStamped {

	/**
	 * database attributes
	 */
	protected $table = 'linked_account_provider';
	protected $primaryKey = 'linked_account_provider_code';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'linked_account_provider_code', 
		'title',
		'description',
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
	);
}
