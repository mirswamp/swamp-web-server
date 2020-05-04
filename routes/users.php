<?php

/******************************************************************************\
|                                                                              |
|                                  users.php                                   |
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
// public user routes
//

// registration routes
//
Route::post('users/validate', 'Users\UsersController@postValidate');
Route::post('users', 'Users\UsersController@postCreate');
Route::get('users/classes', 'Users\UserClassesController@getAll');

// email verification routes
//
Route::group(['middleware' => 'verify.email_verification'], function () {
	Route::post('verifications', 'Users\EmailVerificationsController@postCreate');
	Route::post('verifications/resend', 'Users\EmailVerificationsController@postResend');
	Route::get('verifications/{verification_key}', 'Users\EmailVerificationsController@getIndex');
	Route::put('verifications/verify/{verification_key}', 'Users\EmailVerificationsController@putVerify');
	Route::delete('verifications/{verification_key}', 'Users\EmailVerificationsController@deleteIndex');
});

// password reset routes
//
Route::group(['middleware' => 'verify.password_reset'], function () {
	Route::post('password_resets', 'Users\PasswordResetsController@postCreate');
	Route::get('password_resets/{password_reset_uuid}/{password_reset_nonce}', 'Users\PasswordResetsController@getByIndexAndNonce');
	Route::put('password_resets/reset', 'Users\PasswordResetsController@updateIndex');
});

// authentication routes
//
Route::post('login', 'Users\SessionController@postLogin');
Route::post('logout', 'Users\SessionController@postLogout');
Route::get('sessions/{session_id}/valid', 'Users\SessionController@isValid');
Route::post('users/email/request-username', 'Users\UsersController@requestUsername');

// third party sign-in routes
//
Route::get('idps', 'Utilities\IdentitiesController@getProvidersList');
Route::get('providers/{provider}/register', 'Auth\AuthController@registerWithProvider');
Route::get('providers/{provider}/login', 'Auth\AuthController@loginWithProvider');
Route::get('providers/{provider}/login/add', 'Auth\AuthController@addLoginWithProvider');
Route::get('providers/{provider}/callback', 'Auth\AuthController@handleProviderCallback');
Route::get('oauth2','Auth\AuthController@handleOAuthCallback');

//
// authenticated user routes
//

Route::group(['middleware' => 'auth'], function () {

	// linked account routes
	//
	Route::group(['middleware' => 'verify.linked_account'], function () {
		Route::get('linked-accounts/users/{user_uid}', 'Users\LinkedAccountsController@getLinkedAccountsByUser');
		Route::post('linked-accounts/{linked_account_id}/enabled', 'Users\LinkedAccountsController@setEnabledFlag');
		Route::delete('linked-accounts/{linked_account_id}', 'Users\LinkedAccountsController@deleteLinkedAccount');
	});
	
	// user routes
	//
	Route::group(['middleware' => 'verify.user'], function () {
		Route::get('users/{user_uid}', 'Users\UsersController@getIndex');
		Route::put('users/{user_uid}', 'Users\UsersController@updateIndex');
		Route::put('users/{user_uid}/change-password', 'Users\UsersController@changePassword');
		Route::delete('users/{user_uid}', 'Users\UsersController@deleteIndex');
		Route::get('users/{user_uid}/projects', 'Users\UsersController@getProjects');
		Route::get('users/{user_uid}/projects/trial', 'Projects\ProjectsController@getUserTrialProject');
		Route::get('users/{user_uid}/projects/num', 'Users\UsersController@getNumProjects');
		Route::get('users/{user_uid}/memberships', 'Users\UsersController@getProjectMemberships');

		// user class routes
		//
		Route::group(['middleware' => 'verify.user_class'], function () {
			Route::get('users/{user_uid}/classes', 'Users\UserClassesController@getByUser');
			Route::post('users/{user_uid}/classes/{class_code}', 'Users\UserClassesController@postByUser');
			Route::delete('users/{user_uid}/classes/{class_code}', 'Users\UserClassesController@deleteByUser');
		});
	});

	// user permission routes
	//
	Route::group(['middleware' => 'verify.user_permission'], function () {
		Route::group(['middleware' => 'verify.user'], function () {
			Route::get('users/{user_uid}/permissions', 'Users\PermissionsController@getPermissions');
			Route::post('users/{user_uid}/permissions', 'Users\PermissionsController@requestPermissions');
			Route::put('users/{user_uid}/permissions', 'Users\PermissionsController@setPermissions');
			Route::get('user_permissions/{user_uid}/{permission_code}', 'Users\PermissionsController@lookupPermission');
		});

		Route::delete('user_permissions/{user_permission_uid}', 'Users\PermissionsController@deletePermission');
		Route::post('user_permissions/{user_permission_uid}/project/{project_uid}', 'Users\PermissionsController@designateProject');
		Route::post('user_permissions/{user_permission_uid}/package/{package_uuid}', 'Users\PermissionsController@designatePackage');
	});
});