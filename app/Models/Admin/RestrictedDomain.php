<?php
/******************************************************************************\
|                                                                              |
|                              RestrictedDomain.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a restricted email domain.                    |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Admin;

use App\Models\TimeStamps\TimeStamped;

class RestrictedDomain extends TimeStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'restricted_domains';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'restricted_domain_id';

	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'string';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'domain_name', 
		'description'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'restricted_domain_id',
		'domain_name', 
		'description'
	];

	//
	// querying methods
	//

	public static function getRestrictedDomainNames(): array {
		$restrictedDomains = RestrictedDomain::All();
		$restrictedDomainNames = [];
		for ($i = 0; $i < sizeof($restrictedDomains); $i++) {
			$restrictedDomainNames[] = $restrictedDomains[$i]->domain_name;
		}
		return $restrictedDomainNames;
	}
}
