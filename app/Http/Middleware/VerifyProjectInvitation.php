<?php 
/******************************************************************************\
|                                                                              |
|                         VerifyProjectInvitation.php                          |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a project invitation.               |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;


class VerifyProjectInvitation {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
				if (Session::has('user_uid')) {
					$user = User::getIndex(Session::get('user_uid'));
				}
				else {
					return response(array(
						'status' => 'NO_SESSION',
						'config' => new Configuration()
					), 401);
				}	
				if (!$user || !$request->input('project_uid')) {
					return response('Unable to change project membership.  Insufficient privilages.', 401);
				}
				if ((!$user->isAdmin()) && (!$user->isProjectAdmin( $request->input('project_uid')))) {
					return response('Unable to change project membership.  Insufficient privilages.', 401);
				}
				$project = Project::where('project_uid', '=', $request->input('project_uid'))->first();
				if ($project->trial_project_flag) {
					return response('Unable to change project membership.  Insufficient privilages.', 401);
				}
				break;

			case 'get':
			case 'put':
			case 'delete':
				break;
		}

		return $next($request);
	}

}
