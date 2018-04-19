<?php
/******************************************************************************\
|                                                                              |
|                          UserPoliciesController.php                          |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for user policies.                          |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use PDO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use App\Utilities\Uuids\Guid;
use App\Models\Projects\Project;
use App\Models\Users\User;
use App\Models\Users\Policy;
use App\Models\Users\UserPolicy;
use App\Http\Controllers\BaseController;

class UserPoliciesController extends BaseController {

	//
	// set methods
	//

	public function markAcceptance($policyCode, $userUid) {

		// get inputs
		//
		$policy = Policy::where('policy_code','=', $policyCode)->first();
		$user = User::getIndex($userUid);
		$acceptFlag = filter_var(Input::get('accept_flag', null), FILTER_VALIDATE_BOOLEAN);

		// check inputs
		//
		if ((!$user) || (!$policy) || (!$acceptFlag)) {
			return response('Invalid input.', 404);
		}

		// check privileges
		//
		if (!$user->isAdmin() && ($user->user_uid != session('user_uid'))) {
			return response('Insufficient privileges to mark policy acceptance.', 401);
		}

		// get or create new user policy
		//
		$userPolicy = UserPolicy::where('user_uid','=',$userUid)->where('policy_code','=',$policyCode)->first();
		if (!$userPolicy) {
			$userPolicy = new UserPolicy([
				'user_policy_uid' => Guid::create(),
				'user_uid' => $userUid,
				'policy_code' => $policyCode
			]);
		}

		$userPolicy->accept_flag = $acceptFlag;
		$userPolicy->save();
		return $userPolicy;
	}
}
