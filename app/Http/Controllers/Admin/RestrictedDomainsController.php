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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use App\Models\Admin\RestrictedDomain;
use App\Http\Controllers\BaseController;

class RestrictedDomainsController extends BaseController {

	// create
	//
	public function postCreate() {

		// parse parameters
		//
		$domainName = Input::get('domain_name');
		$description = Input::get('description');

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
	public function getIndex($restrictedDomainId) {
		$restrictedDomain = RestrictedDomain::where('restricted_domain_id', '=', $restrictedDomainId)->first();
		return $restrictedDomain;
	}

	// update by index
	//
	public function updateIndex($restrictedDomainId) {

		// parse parameters
		//
		$domainName = Input::get('domain_name');
		$description = Input::get('description');

		// get model
		//
		$restrictedDomain = $this->getIndex($restrictedDomainId);

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
	public function deleteIndex($restrictedDomainId) {
		$restrictedDomain = RestrictedDomain::where('restricted_domain_id', '=', $restrictedDomainId)->first();
		$restrictedDomain->delete();
		return $restrictedDomain;
	}

	// get all
	//
	public function getAll() {
		$restrictedDomains = RestrictedDomain::all();
		return $restrictedDomains;
	}
	
	// update multiple
	//
	public function updateMultiple() {

		// parse parameters
		//
		$inputs = Input::all();

		// update
		//
		$collection = new Collection;
		for ($i = 0; $i < sizeOf($inputs); $i++) {

			// get input item
			//
			$input = $inputs[$i];
			if (array_key_exists('restricted_domain_id', $input)) {
				
				// find existing model
				//
				$restrictedDomainId = $input['restricted_domain_id'];
				$restrictedDomain = RestrictedDomain::where('restricted_domain_id', '=', $restrictedDomainId)->first();
				$collection->push($restrictedDomain);
			} else {
				
				// create new model
				//
				$restrictedDomain = new RestrictedDomain();
			}
			
			// update model
			//
			$restrictedDomain->domain_name = $input['domain_name'];
			$restrictedDomain->description = $input['description'];
			
			// save model
			//
			$restrictedDomain->save();
		}
		
		return $collection;
	}
}