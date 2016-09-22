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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Admin;

use App\Models\TimeStamps\TimeStamped;

class RestrictedDomain extends TimeStamped {

	/**
	 * database attributes
	 */
	protected $table = 'restricted_domains';
	protected $primaryKey = 'restricted_domain_id';

	// use standard timestamp field names
	//
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'domain_name', 
		'description'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'restricted_domain_id',
		'domain_name', 
		'description'
	);

	/**
	 * methods
	 */
	static public function getRestrictedDomainNames() {
		$restrictedDomains = RestrictedDomain::All();
		$restrictedDomainNames = array();
		for ($i = 0; $i < sizeof($restrictedDomains); $i++) {
			$restrictedDomainNames[] = $restrictedDomains[$i]->domain_name;
		}
		return $restrictedDomainNames;
	}
	
	/**
	 * Get the name of the "created at" column.
	 *
	 * @return string
	 */
	public function getCreatedAtColumn() {
		return 'created_at';
	}

	/**
	 * Get the name of the "updated at" column.
	 *
	 * @return string
	 */
	public function getUpdatedAtColumn() {
		return 'updated_at';
	}

	/**
	 * Get the name of the "updated at" column.
	 *
	 * @return string
	 */
	public function getDeletedAtColumn() {
		return 'deleted_at';
	}
}
