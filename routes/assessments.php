<?php

/******************************************************************************\
|                                                                              |
|                               assessments.php                                |
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
// authenticated assessment routes
//

Route::group(['middleware' => 'auth'], function () {

	// assessment run routes
	//
	Route::group(['middleware' => 'verify.assessment_run'], function () {
		Route::post('assessment_runs/check_compatibility', 'Assessments\AssessmentRunsController@checkCompatibility');
		Route::post('assessment_runs', 'Assessments\AssessmentRunsController@postCreate');
		Route::get('assessment_runs/{assessment_run_uuid}', 'Assessments\AssessmentRunsController@getIndex');
		Route::put('assessment_runs/{assessment_run_uuid}', 'Assessments\AssessmentRunsController@updateIndex');
		Route::delete('assessment_runs/{assessment_run_uuid}', 'Assessments\AssessmentRunsController@deleteIndex');
		Route::get('assessment_runs/{assessment_run_uuid}/run_requests', 'Assessments\AssessmentRunsController@getRunRequests');
	});

	// run request routes
	//
	Route::group(['middleware' => 'verify.run_request'], function () {
		Route::post('run_requests', 'RunRequests\RunRequestsController@postCreate');
		Route::post('run_requests/one-time', 'RunRequests\RunRequestsController@postOneTimeAssessmentRunRequests');
		Route::post('run_requests/{run_request_uuid}', 'RunRequests\RunRequestsController@postAssessmentRunRequests');
		Route::get('run_requests/{run_request_uuid}', 'RunRequests\RunRequestsController@getIndex');
		Route::put('run_requests/{run_request_uuid}', 'RunRequests\RunRequestsController@updateIndex');
		Route::delete('run_requests/{run_request_uuid}/assessment_runs/{assessment_run_uuid}', 'RunRequests\RunRequestsController@deleteAssessmentRunRequest');
		Route::delete('run_requests/{run_request_uuid}', 'RunRequests\RunRequestsController@deleteIndex');
	});

	// run request schedule routes
	//
	Route::group(['middleware' => 'verify.run_request_schedule'], function () {
		Route::post('run_request_schedules', 'RunRequests\RunRequestSchedulesController@postCreate');
		Route::get('run_request_schedules/{run_request_schedule_uuid}', 'RunRequests\RunRequestSchedulesController@getIndex');
		Route::get('run_request_schedules/run_requests/{run_request_uuid}', 'RunRequests\RunRequestSchedulesController@getByRunRequest');
		Route::put('run_request_schedules/{run_request_schedule_uuid}', 'RunRequests\RunRequestSchedulesController@updateIndex');
		Route::put('run_request_schedules', 'RunRequests\RunRequestSchedulesController@updateMultiple');
		Route::delete('run_request_schedules/{run_request_schedule_uuid}', 'RunRequests\RunRequestSchedulesController@deleteIndex');
	});
});