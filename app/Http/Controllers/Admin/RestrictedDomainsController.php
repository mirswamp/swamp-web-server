<?php
/******************************************************************************\
|                                                                              |
|                        RestrictedDomainsController.php                       |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for restricted email domains.               |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Models\Admin\RestrictedDomain;
use App\Http\Controllers\BaseController;

class RestrictedDomainsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request): RestrictedDomain {

		// parse parameters
		//
		$domainName = $request->input('domain_name');
		$description = $request->input('description');

		// create new restricted domain
		//
		$restrictedDomain = new RestrictedDomain([
			'domain_name' => $domainName,
			'description' => $description
		]);
		$restrictedDomain->save();

		return $restrictedDomain;
	}

	// get by index
	//
	public function getIndex(string $restrictedDomainId): ?RestrictedDomain {
		return RestrictedDomain::find($restrictedDomainId);
	}

	// get all
	//
	public function getAll(): Collection {
		return RestrictedDomain::all();
	}
	
	// update by index
	//
	public function updateIndex(Request $request, string $restrictedDomainId) {

		// parse parameters
		//
		$domainName = $request->input('domain_name');
		$description = $request->input('description');

		// find model
		//
		$restrictedDomain = RestrictedDomain::find($restrictedDomainId);
		if (!$restrictedDomain) {
			return response("Restricted domain not found.", 404);
		}

		// update attributes
		//
		$restrictedDomain->domain_name = $domainName;
		$restrictedDomain->description = $description;

		// save and return changes
		//
		$changes = $restrictedDomain->getDirty();
		$restrictedDomain->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex(string $restrictedDomainId) {

		// find model
		//
		$restrictedDomain = RestrictedDomain::find($restrictedDomainId);
		if (!$restrictedDomain) {
			return response("Restricted domain not found.", 404);
		}

		$restrictedDomain->delete();
		return $restrictedDomain;
	}
}