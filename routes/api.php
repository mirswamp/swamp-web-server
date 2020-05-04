<?php

/******************************************************************************\
|                                                                              |
|                                   api.php                                    |
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

Route::get('environment', 'BaseController@getEnvironment');

// for all application routes, verify that server has been configured
//
Route::group(['middleware' => 'verify.config'], function () {

	// usage / metrics route
	//
	Route::get('usage', 'Utilities\UsageController@getUsage');
	Route::get('usage/latest', 'Utilities\UsageController@getLatestUsage');
	
	// contact routes 
	//	 
	Route::post('contacts', 'Utilities\ContactsController@postCreate');

	// authenticated routes
	//
	Route::group(['middleware' => 'auth'], function () {

		// policy routes
		//
		Route::group(['middleware' => 'verify.policy'], function () {
			Route::get('policies/{policy_code}', 'Users\PoliciesController@getByCode');
			Route::get('user_policies/{policy_code}', 'Users\UserPoliciesController@getByCurrentUser');
			Route::post('user_policies/{policy_code}/user/{user_uid}', 'Users\UserPoliciesController@markAcceptance');
		});

		// app password routes
		//
		Route::group(['middleware' => 'verify.app_passwords'], function () {
			Route::post('v1/app_passwords', 'Users\AppPasswordsController@postCreate');
			Route::get('v1/app_passwords/{app_password_uuid}', 'Users\AppPasswordsController@getIndex');
			Route::get('v1/app_passwords', 'Users\AppPasswordsController@getAll');
			Route::put('v1/app_passwords/{app_password_uuid}', 'Users\AppPasswordsController@putIndex');
			Route::delete('v1/app_passwords/{app_password_uuid}', 'Users\AppPasswordsController@deleteIndex');
			Route::delete('v1/app_passwords', 'Users\AppPasswordsController@deleteAll');
		});
	});
});
