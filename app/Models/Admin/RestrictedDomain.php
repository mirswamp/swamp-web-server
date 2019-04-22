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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Admin;

use App\Models\TimeStamps\TimeStamped;

class RestrictedDomain extends TimeStamped
{
	// database attributes
	//
	protected $table = 'restricted_domains';
	protected $primaryKey = 'restricted_domain_id';

	// mass assignment policy
	//
	protected $fillable = [
		'domain_name', 
		'description'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'restricted_domain_id',
		'domain_name', 
		'description'
	];

	//
	// querying methods
	//

	public static function getRestrictedDomainNames() {
		$restrictedDomains = RestrictedDomain::All();
		$restrictedDomainNames = [];
		for ($i = 0; $i < sizeof($restrictedDomains); $i++) {
			$restrictedDomainNames[] = $restrictedDomains[$i]->domain_name;
		}
		return $restrictedDomainNames;
	}
}
