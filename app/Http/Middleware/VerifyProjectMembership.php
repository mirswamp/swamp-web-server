<?php 
/******************************************************************************\
|                                                                              |
|                         VerifyProjectMembership.php                          |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a project membership.               |
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
use App\Utilities\Filters\FiltersHelper;
use App\Models\Projects\ProjectMembership;

class VerifyProjectMembership {

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
			case 'get':
				break;

			case 'put':
			case 'delete':

				// check to see that user is logged in
				//
				if (Session::has('user_uid')) {
					$user = User::getIndex(Session::get('user_uid'));
					if (!$user) {
						return response('Unable to change project membership.  No current user.', 401);
					}
				} else {
					return response('Unable to change project membership.  No session.', 401);
				}				

				// check privileges
				//
				if ($request->route()->getParameter('project_membership_id')) {
					$projectMembership = ProjectMembership::where('membership_uid', '=', $request->route()->getParameter('project_membership_id'))->first();
					if (!$projectMembership) {
						return response('Unable to change project membership.  Project membership does not exist.', 401);
					} else {
						$hasProjectMembership = $user->hasProjectMembership($request->route()->getParameter('project_membership_id'));		
						$isProjectAdmin = $user->isProjectAdmin($projectMembership->project_uid);
						if (!($user->isAdmin()) && !$isProjectAdmin && !$hasProjectMembership) {
							return response('Unable to change project membership.  Insufficient privileges.', 401);
						}
					}
				} else if ($request->route()->getParameter('project_uid')) {
					if ((!$user->isAdmin()) && (!$user->isProjectAdmin($request->route()->getParameter('project_uid')))) {
						return response('Unable to change project membership.  Insufficient privileges.', 401);
					}
				}
				break;
		}

		return $next($request);
	}

}
