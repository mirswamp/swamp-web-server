<?php

/******************************************************************************\
|                                                                              |
|                                  admin.php                                   |
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
// public admin invitation routes
//

Route::get('admin_invitations/{invitation_key}', 'Admin\AdminInvitationsController@getIndex');
Route::put('admin_invitations/{invitation_key}/accept', 'Admin\AdminInvitationsController@acceptIndex');
Route::put('admin_invitations/{invitation_key}/decline', 'Admin\AdminInvitationsController@declineIndex');

//
// authenticated admin routes
//

Route::group(['middleware' => 'auth'], function () {

	// admin routes
	//
	Route::group(['middleware' => 'verify.admin'], function () {

		// admin invitation routes
		//
		Route::post('admin/email/user', 'Users\UsersController@getUserByEmail');
		Route::post('admin/username/user', 'Users\UsersController@getUserByUsername');

		// admin review permission request routes
		//
		Route::get('admin/permissions', 'Users\PermissionsController@getPending');
		Route::get('admin/permissions/num', 'Users\PermissionsController@getNumPending');

		// admin priviledge routes
		//
		Route::get('admins/{user_uid}', 'Admin\AdminsController@getIndex');
		Route::put('admins/{user_uid}', 'Admin\AdminsController@updateIndex');
		Route::delete('admins/{user_uid}', 'Admin\AdminsController@deleteIndex');

		// admin overview routes
		//	
		Route::get('admin/admins/all', 'Admin\AdminsController@getAll');
		Route::get('admin/projects/all', 'Projects\ProjectsController@getAll');
		Route::get('admin/users/all', 'Users\UsersController@getAll');
		Route::get('admin/users/{user_id}/info', 'Users\UsersController@getInfo');
		Route::get('admin/users/enabled', 'Users\UsersController@getEnabled');
		Route::get('admin/users/signed-in', 'Users\UsersController@getSignedIn');

		// admin email
		//
		Route::get('admin/users/email', 'Admin\AdminsController@getEmail');
		Route::post('admin/users/email', 'Admin\AdminsController@sendEmail');
	});

	// admin invitation routes
	//
	Route::group(['middleware' => 'verify.admin_invitation'], function () {
		Route::post('admin_invitations', 'Admin\AdminInvitationsController@postCreate');
		Route::get('admin_invitations', 'Admin\AdminInvitationsController@getAll');
		Route::delete('admin_invitations/{invitation_key}', 'Admin\AdminInvitationsController@deleteIndex');

		// user admin invitation routes
		//
		Route::group(['middleware' => 'verify.user'], function () {
			Route::get('admin_invitations/users/{user_uid}', 'Admin\AdminInvitationsController@getPendingByUser');
			Route::get('admin_invitations/users/{user_uid}/num', 'Admin\AdminInvitationsController@getNumPendingByUser');
		});
	});

	// restricted domain routes
	//
	Route::group(['middleware' => 'verify.admin'], function () {
		Route::post('restricted-domains', 'Admin\RestrictedDomainsController@postCreate');
		Route::get('restricted-domains/{restricted_domain_id}', 'Admin\RestrictedDomainsController@getIndex');
		Route::get('restricted-domains', 'Admin\RestrictedDomainsController@getAll');
		Route::put('restricted-domains/{restricted_domain_id}', 'Admin\RestrictedDomainsController@updateIndex');
		Route::delete('restricted-domains/{restricted_domain_id}', 'Admin\RestrictedDomainsController@deleteIndex');
		
		// diagnostic route
		//
		Route::get('status', 'Utilities\StatusController@getCurrent');
	});

	// route introspection route
	//
	Route::group(['middleware' => 'verify.admin'], function () {
		Route::get('routes', 'Utilities\RoutesController@getActual');
	});

	// app password routes for admins
	//
	Route::group(['middleware' => 'verify.admin'], function () {
		Route::get('v1/admin/users/{user_uid}/app_passwords', 'Users\AppPasswordsController@getByUser');
		Route::delete('v1/admin/users/{user_uid}/app_passwords', 'Users\AppPasswordsController@deleteByUser');
	});

	// project membership routes for admins
	//
	Route::post('memberships', 'Projects\ProjectMembershipsController@postCreate');
});