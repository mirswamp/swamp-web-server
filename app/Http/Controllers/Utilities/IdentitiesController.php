<?php
/******************************************************************************\
|                                                                              |
|                           IdentitiesController.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for authentication identity providers.      |
|                                                                              |
|        Author(s): Terry Fleury                                               |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\BaseController;
use App\Models\Utilities\Configuration;
use Illuminate\Support\Facades\Config;

define('CILOGON_IDPLIST_URL','https://cilogon.org/idplist/?skin=' .  
							 config('oauth2.cilogon_skin'));

class IdentitiesController extends BaseController {

	/**
	 * This function returns a JSON array of the Identity Providers which are
	 * configured for OAuth 2.0 authentication. The contents of the JSON
	 * array are of the form {"entityid":"entityId1","name":"displayname1",
	 * "class":"class1"}. If no OAuth 2.0 providers are specified, the 
	 * returned JSON array will be empty, i.e., "[]".
	 *
	 * @return JSON array of available IdPs as:
	 *         [ {"entityid":"entityId1",
	 *            "name":"name1",
	 *            "class":"class1"} , ... ]
	 */

	public static function getProvidersList() {

		// Store IdPs in an array with entries for entityid, name, and class
		//
		$idparray = [];

		// Check if GitHub OAuth2 has been configured
		//
		if (config('app.github_authentication_enabled')) {
			$idparray[] = [
				'entityid' => 'GitHub',
				'name'     => 'GitHub',
				'class'    => 'GitHub',
			];
		}

		// Check if Google OAuth2 has been configured
		//
		if (config('app.google_authentication_enabled')) {
			$idparray[] = [
				'entityid' => 'Google',
				'name'     => 'Google',
				'class'    => 'Google',
			];
		}

		// Check if CILogon OAuth2 has been configured
		//
		if (config('app.ci_logon_authentication_enabled')) {

			// Set a short timeout (10 seconds) for file_get_contents()
			// Taken from http://stackoverflow.com/a/10236480
			//
			$ctx = stream_context_create([
				'http' => [
					'timeout' => 10, // in seconds
				]
			]);

			// Fetch the list of CILogon IdPs available for SWAMP login
			//
			$cilogonjson = @file_get_contents(CILOGON_IDPLIST_URL,false,$ctx);
			if (strlen($cilogonjson) > 0) {

				// Convert JSON to array so we can extract EntityIDs and IdP OrgNames
				//
				$cilogonidps = json_decode($cilogonjson,true);
				if (!is_null($cilogonidps)) {
					foreach ($cilogonidps as $idp) {
						$idparray[] = [
							'entityid' => $idp['EntityID'], 
							'name'     => $idp['OrganizationName'],
							'class'    => 'CILogon',
						];
					}
				}
			}
		}

		// Sort the array of IdPs by their display names
		//
		if (!empty($idparray)) {
			usort($idparray, function ($a,$b) {
				return strcasecmp($a['name'],$b['name']);
			});
		}

		// Don't escape '/' or unicode characters
		//
		return json_encode($idparray,JSON_UNESCAPED_SLASHES |
			JSON_UNESCAPED_UNICODE);
	}

}
