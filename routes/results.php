<?php

/******************************************************************************\
|                                                                              |
|                                 results.php                                  |
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
// public execution record notification route
//

Route::post('execution_records/{execution_record_uuid}/notify', 'Results\ExecutionRecordsController@notifyIndex');

//
// authenticated result routes
//

Route::group(['middleware' => 'auth'], function () {

	// proxy routes
	//
	Route::group(['middleware' => 'verify.project'], function () {
		Route::any('{all}', 'Viewers\ProxyController@proxyCodeDxRequest')->where('all', '^proxy-.*');
	});
		
	// execution record routes
	//
	Route::group(['middleware' => 'verify.execution_record'], function () {
		Route::get('execution_records/all', 'Results\ExecutionRecordsController@getAll');
		Route::get('execution_records/{execution_record_uuid}', 'Results\ExecutionRecordsController@getIndex');
		Route::get('execution_records/{execution_record_uuid}/ssh_access', 'Results\ExecutionRecordsController@getSshAccess');
		Route::put('execution_records/{execution_record_uuid}/kill', 'Results\ExecutionRecordsController@killIndex');
		Route::delete('execution_records/{execution_record_uuid}', 'Results\ExecutionRecordsController@deleteIndex');
	});

	// assessment results routes
	//
	Route::group(['middleware' => 'verify.assessment_result'], function () {
		Route::get('assessment_results/{assessment_result_uuid}', 'Results\AssessmentResultsController@getIndex');
		Route::get('assessment_results/{assessment_result_uuid}/viewer/{viewer_uuid}/project/{project_uuid}', 'Results\AssessmentResultsController@getResults');
		Route::get('assessment_results/{assessment_result_uuid}/viewer/{viewer_uuid}/project/{project_uuid}/catalog', 'Results\AssessmentResultsController@getCatalog');
		Route::get('assessment_results/{assessment_result_uuid}/viewer/{viewer_uuid}/project/{project_uuid}/permission', 'Results\AssessmentResultsController@getResultsPermission');
		Route::get('assessment_results/viewer/{viewer_uuid}/project/{project_uuid}/permission', 'Results\AssessmentResultsController@getNoResultsPermission');
		Route::get('assessment_results/viewer_instance/{viewer_instance_uuid}', 'Results\AssessmentResultsController@getInstanceStatus');
		Route::get('v1/assessment_results/{assessment_result_uuid}/scarf', 'Results\AssessmentResultsController@getScarf');
	});

	// viewer routes
	//
	Route::group(['middleware' => 'verify.viewer'], function () {
		Route::get('viewers/all', 'Viewers\ViewersController@getAll');
	});
});