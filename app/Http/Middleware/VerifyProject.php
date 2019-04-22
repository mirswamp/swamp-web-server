<?php 
/******************************************************************************\
|                                                                              |
|                              VerifyProject.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a project.                          |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;

class VerifyProject
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
		// get current user
		//
		if (Session::has('user_uid')) {
			$currentUser = User::getIndex(session('user_uid'));
		} else {
			return response([
				'status' => 'NO_SESSION',
				'config' => new Configuration()
			], 401);
		}
		
		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
				break;

			case 'get':
				$projectUid = $request->route('project_uid');
				if (!$projectUid) {
					$projectUid = $request->route('project_uuid');	
				}

				// skip checking if no project is specified
				//
				if (!$projectUid) {
					break;
				}
				
				if (!strpos($projectUid, '+')) {

					// check a single project
					//
					$project = Project::where('project_uid', '=', $projectUid)->first();
					if ($project && !$project->isReadableBy($currentUser)) {
						return response('Insufficient priveleges to access project.', 403);
					}
				} else {

					// check multiple projects
					//
					$projectUids = explode('+', $projectUid);
					foreach ($projectUids as $projectUid) {
						$project = Project::where('project_uid', '=', $projectUid)->first();
						if ($project && !$project->isReadableBy($currentUser)) {
							return response('Insufficient priveleges to access project.', 403);
						}	
					}
				}
				break;

			case 'put':
			case 'delete':
				$projectUid = $request->route('project_uid');
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project && !$project->isWriteableBy($currentUser)) {
					return response('Insufficient priveleges to modify project.', 403);
				}
				break;
		}

		return $next($request);
	}
}