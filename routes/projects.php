<?php

/******************************************************************************\
|                                                                              |
|                                 projects.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This is where all of the application routes are registered.           |
|        Routes are registered by telling Laravel the URIs that it             |
|        should respond to and giving it the controller to call when           |
|        that URI is requested.                                                |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

//
// public project invitation routes
//

Route::get('invitations/{invitation_key}', 'Projects\ProjectInvitationsController@getIndex');
Route::get('projects/{project_uid}/confirm', 'Projects\ProjectsController@getIndex');
Route::put('invitations/{invitation_key}/accept', 'Projects\ProjectInvitationsController@acceptIndex');
Route::put('invitations/{invitation_key}/decline', 'Projects\ProjectInvitationsController@declineIndex');
Route::get('invitations/{invitation_key}/inviter', 'Projects\ProjectInvitationsController@getInviter');
Route::get('invitations/{invitation_key}/invitee', 'Projects\ProjectInvitationsController@getInvitee');

//
// authenticated project routes
//

Route::group(['middleware' => 'auth'], function () {
	
	// project routes
	//
	Route::group(['middleware' => 'verify.project'], function () {
		Route::post('projects', 'Projects\ProjectsController@postCreate');
		Route::get('projects/{project_uid}', 'Projects\ProjectsController@getIndex');
		Route::get('projects/{project_uid}/users', 'Projects\ProjectsController@getUsers');
		Route::get('projects/{project_uid}/memberships', 'Projects\ProjectsController@getMemberships');
		Route::get('projects/{project_uid}/events', 'Projects\ProjectsController@getEvents');
		Route::put('projects/{project_uid}', 'Projects\ProjectsController@updateIndex');
		Route::delete('projects/{project_uid}', 'Projects\ProjectsController@deleteIndex');

		Route::get('projects/{project_uuid}/assessment_runs', 'Assessments\AssessmentRunsController@getByProject');
		Route::get('projects/{project_uuid}/assessment_runs/num', 'Assessments\AssessmentRunsController@getNumByProject');
		Route::get('projects/{project_uuid}/run_requests/schedules', 'RunRequests\RunRequestsController@getByProject');
		Route::get('projects/{project_uuid}/assessment_runs/scheduled', 'Assessments\AssessmentRunsController@getScheduledByProject');
		Route::get('projects/{project_uuid}/assessment_runs/scheduled/num', 'Assessments\AssessmentRunsController@getNumScheduledByProject');
		Route::get('projects/{project_uuid}/run_requests/schedules/num', 'RunRequests\RunRequestsController@getNumByProject');
		Route::get('projects/{project_uuid}/execution_records', 'Results\ExecutionRecordsController@getByProject');
		Route::get('projects/{project_uuid}/execution_records/num', 'Results\ExecutionRecordsController@getNumByProject');
		Route::get('projects/{project_uuid}/assessment_results', 'Results\AssessmentResultsController@getByProject');
	});

	// project invitation routes
	//
	Route::group(['middleware' => 'verify.project_invitation'], function () {
		Route::post('invitations', 'Projects\ProjectInvitationsController@postCreate');
		Route::get('invitations/projects/{project_uid}', 'Projects\ProjectInvitationsController@getByProject');
		Route::delete('invitations/{invitation_key}', 'Projects\ProjectInvitationsController@deleteIndex');

		// user project invitation routes
		//
		Route::group(['middleware' => 'verify.user'], function () {
			Route::get('invitations/users/{user_uid}', 'Projects\ProjectInvitationsController@getByUser');
			Route::get('invitations/users/{user_uid}/num', 'Projects\ProjectInvitationsController@getNumByUser');
		});
	});

	// project membership routes
	//
	Route::group(['middleware' => 'verify.project_membership'], function () {
		Route::get('memberships/{project_membership_id}', 'Projects\ProjectMembershipsController@getIndex');
		Route::get('memberships/projects/{project_uid}/users/{user_uid}', 'Projects\ProjectMembershipsController@getMembership');
		Route::put('memberships/{project_membership_id}', 'Projects\ProjectMembershipsController@updateIndex');
		Route::delete('memberships/{project_membership_id}', 'Projects\ProjectMembershipsController@deleteIndex');
		Route::delete('memberships/projects/{project_uid}/users/{user_uid}', 'Projects\ProjectMembershipsController@deleteMembership');
	});
});