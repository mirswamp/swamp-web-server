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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use App\Models\Users\User;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;

class VerifyProjectMembership
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{

		// check to see that user is logged in
		//
		if (Session::has('user_uid')) {
			$user = User::current();
			if (!$user) {
				return response('Unable to change project membership.  No current user.', 401);
			}
		} else {
			return response('Unable to change project membership.  No session.', 401);
		}	

		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
			case 'get':
				break;

			case 'put':		

				// change by index
				//
				if ($request->route('project_membership_id')) {
					$projectMembership = ProjectMembership::where('membership_uid', '=', $request->route('project_membership_id'))->first();

					// check for project membership
					//
					if (!$projectMembership) {
						return response('Unable to change project membership.  Project membership does not exist.', 401);
					} else {

						// check for admin / project admin priviledges
						//
						$hasProjectMembership = $user->hasProjectMembership($request->route('project_membership_id'));		
						$isProjectAdmin = $user->isProjectAdmin($projectMembership->project_uid);
						if (!($user->isAdmin()) && !$isProjectAdmin && !$hasProjectMembership) {
							return response('Unable to change project membership.  Insufficient privileges.', 401);
						}
					}

				// change all
				//
				} else if ($request->route('project_uid')) {

					// check admin / project admin priviledges
					//
					if ((!$user->isAdmin()) && (!$user->isProjectAdmin($request->route('project_uid')))) {
						return response('Unable to change project membership.  Insufficient privileges.', 401);
					}
				}
				break;

			case 'delete':

				// delete by index
				//
				if ($request->route('project_membership_id')) {
					$projectMembership = ProjectMembership::where('membership_uid', '=', $request->route('project_membership_id'))->first();

					// check for project membership
					//
					if (!$projectMembership) {
						return response('Unable to delete project membership.  Project membership does not exist.', 401);
					} else {

						// check for admin / project admin priviledges
						//
						$hasProjectMembership = $user->hasProjectMembership($request->route('project_membership_id'));		
						$isProjectAdmin = $user->isProjectAdmin($projectMembership->project_uid);
						if (!($user->isAdmin()) && !$isProjectAdmin && !$hasProjectMembership) {
							return response('Unable to delete project membership.  Insufficient privileges.', 401);
						}
					}

				// delete by project / user
				//			
				} else if ($request->route('project_uid')) {
					$projectUid = $request->route('project_uid');
					$userUid = $request->route('user_uid');
					$project = Project::where('project_uid', '=', $projectUid)->first();

					// check for owner
					//
					if ($project && $project->project_owner_uid == $userUid) {
						return response('Unable to delete project owner.', 401);
					}

					// check admin / project admin priviledges
					//
					if ((!$user->isAdmin()) && (!$user->isProjectAdmin($request->route('project_uid')))) {
						return response('Unable to delete project membership.  Insufficient privileges.', 401);
					}
				}
				break;
		}

		return $next($request);
	}
}