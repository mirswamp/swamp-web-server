<?php
/******************************************************************************\
|                                                                              |
|                                  routes.php                                  |
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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

Route::get('environment', function() {
	return App::environment();
});

// for all application routes, verify that server has been configured
//
Route::group(['middleware' => 'verify.config'], function () {

	// login routes
	//
	Route::post('login', 'Users\SessionController@postLogin');
	Route::post('logout', 'Users\SessionController@postLogout');
	Route::get('idps', 'Utilities\IdentitiesController@getProvidersList');

	// validation / verification routes
	//
	Route::post('users/validate', 'Users\UsersController@postValidate');
			
	// public user routes
	//
	Route::post('users', 'Users\UsersController@postCreate');
	Route::post('users/email/request-username', 'Users\UsersController@requestUsername');

	// public resource routes
	//
	Route::get('packages/public', 'Packages\PackagesController@getPublic');
	Route::get('packages/types', 'Packages\PackagesController@getTypes');
	Route::get('tools/public', 'Tools\ToolsController@getPublic');
	Route::get('tools/restricted', 'Tools\ToolsController@getRestricted'); 
	Route::get('platforms/public', 'Platforms\PlatformsController@getPublic');

	// public email verification routes
	//
	Route::group(['middleware' => 'verify.email_verification'], function () {
		Route::post('verifications', 'Users\EmailVerificationsController@postCreate');
		Route::post('verifications/resend', 'Users\EmailVerificationsController@postResend');
		Route::get('verifications/{verification_key}', 'Users\EmailVerificationsController@getIndex');
		Route::put('verifications/{verification_key}', 'Users\EmailVerificationsController@updateIndex');
		Route::put('verifications/verify/{verification_key}', 'Users\EmailVerificationsController@putVerify');
		Route::delete('verifications/{verification_key}', 'Users\EmailVerificationsController@deleteIndex');
	});

	// public admin invitation routes
	//
	Route::get('admin_invitations/{invitation_key}', 'Admin\AdminInvitationsController@getIndex');
	Route::put('admin_invitations/{invitation_key}/accept', 'Admin\AdminInvitationsController@acceptIndex');
	Route::put('admin_invitations/{invitation_key}/decline', 'Admin\AdminInvitationsController@declineIndex');

	// public project invitation routes
	//
	Route::get('invitations/{invitation_key}', 'Projects\ProjectInvitationsController@getIndex');
	Route::get('projects/{project_uid}/confirm', 'Projects\ProjectsController@getIndex');
	Route::put('invitations/{invitation_key}/accept', 'Projects\ProjectInvitationsController@acceptIndex');
	Route::put('invitations/{invitation_key}/decline', 'Projects\ProjectInvitationsController@declineIndex');
	Route::get('invitations/{invitation_key}/inviter', 'Projects\ProjectInvitationsController@getInviter');
	Route::get('invitations/{invitation_key}/invitee', 'Projects\ProjectInvitationsController@getInvitee');

	// public password reset routes
	//
	Route::group(['middleware' => 'verify.password_reset'], function () {
		Route::post('password_resets', 'Users\PasswordResetsController@postCreate');
		Route::get('password_resets/{password_reset_key}/{password_reset_id}', 'Users\PasswordResetsController@getIndex');
		Route::put('password_resets/{password_reset_id}/reset', 'Users\PasswordResetsController@updateIndex');
		Route::delete('password_resets/{password_reset_key}/{password_reset_id}', 'Users\PasswordResetsController@deleteIndex');
	});

	// oauth2 login routes
	//
	Route::get('oauth2','Users\SessionController@oauth2');
	Route::get('oauth2/user','Users\SessionController@oauth2User');
	Route::get('oauth2/register','Users\SessionController@registeroauth2User');
	Route::get('oauth2/redirect','Users\SessionController@oauth2Redirect');
	Route::post('oauth2/link','Users\SessionController@oauth2Link');

	// contact routes
	//
	Route::post('contacts', 'Utilities\ContactsController@postCreate');

	// country routes
	//
	Route::get('countries', 'Utilities\CountriesController@getAll');

	// authenticated routes
	//
	Route::group(['middleware' => 'auth'], function () {

		// proxy routes
		// 
		Route::any('{all}', 'Proxies\ProxyController@proxyCodeDxRequest')->where('all', '^proxy-.*');

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
			Route::put('users', 'Users\UsersController@updateAll');
			Route::delete('users/{user_uid}', 'Users\UsersController@deleteIndex');
			Route::get('users/{user_uid}/projects', 'Users\UsersController@getProjects');
			Route::get('users/{user_uid}/projects/trial', 'Projects\ProjectsController@getUserTrialProject');
			Route::get('users/{user_uid}/projects/num', 'Users\UsersController@getNumProjects');
			Route::get('users/{user_uid}/memberships', 'Users\UsersController@getProjectMemberships');
		});

		// user permission routes
		//
		Route::group(['middleware' => 'verify.user_permission'], function () {
			Route::group(['middleware' => 'verify.user'], function () {
				Route::get('users/{user_uid}/permissions', 'Users\PermissionsController@getPermissions');
				Route::post('users/{user_uid}/permissions', 'Users\PermissionsController@requestPermissions');
				Route::put('users/{user_uid}/permissions', 'Users\PermissionsController@setPermissions');
				Route::get('user_permissions/{user_uid}/{permission_code}', 'Users\PermissionsController@lookupPermission');
				Route::post('user_permissions/{user_uid}/{permission_code}', 'Users\PermissionsController@requestPermission');
			});

			Route::delete('user_permissions/{user_permission_uid}', 'Users\PermissionsController@deletePermission');
			Route::post('user_permissions/{user_permission_uid}/project/{project_uid}', 'Users\PermissionsController@designateProject');
			Route::post('user_permissions/{user_permission_uid}/package/{package_uuid}', 'Users\PermissionsController@designatePackage');
		});

		// policy routes
		//
		Route::group(['middleware' => 'verify.policy'], function () {
			Route::get('policies/{policy_code}', 'Users\PoliciesController@getByCode');
			Route::post('user_policies/{policy_code}/user/{user_uid}', 'Users\UserPoliciesController@markAcceptance');
		});

		// restricted domain routes
		//
		Route::group(['middleware' => 'verify.admin'], function () {
			Route::post('restricted-domains', 'Admin\RestrictedDomainsController@postCreate');
			Route::get('restricted-domains/{restricted_domain_id}', 'Admin\RestrictedDomainsController@getIndex');
			Route::put('restricted-domains/{restricted_domain_id}', 'Admin\RestrictedDomainsController@updateIndex');
			Route::delete('restricted-domains/{restricted_domain_id}', 'Admin\RestrictedDomainsController@deleteIndex');
			Route::get('restricted-domains', 'Admin\RestrictedDomainsController@getAll');
			Route::put('restricted-domains', 'Admin\RestrictedDomainsController@updateMultiple');

			// diagnostic route
			//
			Route::get('status', 'Utilities\StatusController@getCurrent');
		});

		// project routes
		//
		Route::group(['middleware' => 'verify.project'], function () {
			Route::post('projects', 'Projects\ProjectsController@postCreate');
			Route::get('projects/{project_uid}', 'Projects\ProjectsController@getIndex');
			Route::get('projects/{project_uid}/users', 'Projects\ProjectsController@getUsers');
			Route::get('projects/{project_uid}/memberships', 'Projects\ProjectsController@getMemberships');
			Route::get('projects/{project_uid}/events', 'Projects\ProjectsController@getEvents');
			Route::put('projects/{project_uid}', 'Projects\ProjectsController@updateIndex');
			Route::put('projects', 'Projects\ProjectsController@updateAll');
			Route::delete('projects/{project_uid}', 'Projects\ProjectsController@deleteIndex');

			Route::get('projects/{project_uuid}/assessment_runs', 'Assessments\AssessmentRunsController@getByProject');
			Route::get('projects/{project_uuid}/assessment_runs/num', 'Assessments\AssessmentRunsController@getNumByProject');
			Route::get('projects/{project_uuid}/run_requests', 'Assessments\AssessmentRunsController@getRunRequestsByProject');
			Route::get('projects/{project_uuid}/run_requests/schedules', 'RunRequests\RunRequestsController@getByProject');
			Route::get('projects/{project_uuid}/assessment_runs/scheduled', 'Assessments\AssessmentRunsController@getScheduledByProject');
			Route::get('projects/{project_uuid}/assessment_runs/scheduled/num', 'Assessments\AssessmentRunsController@getNumScheduledByProject');
			Route::get('projects/{project_uuid}/run_requests/schedules/num', 'RunRequests\RunRequestsController@getNumByProject');
			Route::get('projects/{project_uuid}/execution_records', 'Executions\ExecutionRecordsController@getByProject');
			Route::get('projects/{project_uuid}/execution_records/num', 'Executions\ExecutionRecordsController@getNumByProject');
			Route::get('projects/{project_uuid}/assessment_results', 'Assessments\AssessmentResultsController@getByProject');
		});

		// project invitation routes
		//
		Route::group(['middleware' => 'verify.project_invitation'], function () {
			Route::post('invitations', 'Projects\ProjectInvitationsController@postCreate');
			Route::get('invitations/projects/{project_uid}', 'Projects\ProjectInvitationsController@getByProject');
			Route::put('invitations/{invitation_key}', 'Projects\ProjectInvitationsController@updateIndex');
			Route::put('invitations', 'Projects\ProjectInvitationsController@updateAll');
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
			Route::post('memberships', 'Projects\ProjectMembershipsController@postCreate');
			Route::get('memberships/{project_membership_id}', 'Projects\ProjectMembershipsController@getIndex');
			Route::get('memberships/projects/{project_uid}/users/{user_uid}', 'Projects\ProjectMembershipsController@getMembership');
			Route::put('memberships/{project_membership_id}', 'Projects\ProjectMembershipsController@updateIndex');
			Route::put('memberships', 'Projects\ProjectMembershipsController@updateAll');
			Route::delete('memberships/{project_membership_id}', 'Projects\ProjectMembershipsController@deleteIndex');
			Route::delete('memberships/projects/{project_uid}/users/{user_uid}', 'Projects\ProjectMembershipsController@deleteMembership');
		});

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

			// admin privelidge routes
			//
			Route::get('admins/{user_uid}', 'Admin\AdminsController@getIndex');
			Route::put('admins/{user_uid}', 'Admin\AdminsController@updateIndex');
			Route::delete('admins/{user_uid}', 'Admin\AdminsController@deleteIndex');

			// admin overview routes
			//	
			Route::get('admins/{user_uid}/admins', 'Admin\AdminsController@getAll');
			Route::get('admins/{user_uid}/projects', 'Projects\ProjectsController@getAll');
			Route::get('admins/{user_uid}/users', 'Users\UsersController@getAll');
			Route::get('admins/{user_uid}/contacts', 'Utilities\ContactsController@getAll');

			// admin email
			//
			Route::post('admins_email', 'Admin\AdminsController@sendEmail');
		});

		// admin invitation routes
		//
		Route::group(['middleware' => 'verify.admin_invitation'], function () {
			Route::post('admin_invitations', 'Admin\AdminInvitationsController@postCreate');
			Route::get('admin_invitations', 'Admin\AdminInvitationsController@getAll');
			Route::get('admin_invitations/invitees', 'Admin\AdminInvitationsController@getInvitees');
			Route::get('admin_invitations/inviters', 'Admin\AdminInvitationsController@getInviters');
			Route::put('admin_invitations/{invitation_key}', 'Admin\AdminInvitationsController@updateIndex');
			Route::delete('admin_invitations/{invitation_key}', 'Admin\AdminInvitationsController@deleteIndex');

			// user admin invitation routes
			//
			Route::group(['middleware' => 'verify.user'], function () {
				Route::get('admin_invitations/users/{user_uid}', 'Admin\AdminInvitationsController@getPendingByUser');
				Route::get('admin_invitations/users/{user_uid}/num', 'Admin\AdminInvitationsController@getNumPendingByUser');
			});
		});

		// event routes
		//
		Route::group(['middleware' => 'verify.user'], function () {
			Route::get('events/users/{user_uid}/num', 'Events\PersonalEventsController@getNumByUser');
			Route::get('events/personal/users/{user_uid}', 'Events\PersonalEventsController@getPersonalByUser');
			Route::get('events/personal/users/{user_uid}/num', 'Events\PersonalEventsController@getNumPersonalByUser');
			Route::get('events/projects/users/{user_uid}', 'Events\ProjectEventsController@getByUser');
			Route::get('events/projects/users/{user_uid}/num', 'Events\ProjectEventsController@getNumByUser');
			Route::get('events/projects/users/{user_uid}/events', 'Events\ProjectEventsController@getUserProjectEvents');
			Route::get('events/projects/users/{user_uid}/events/num', 'Events\ProjectEventsController@getNumUserProjectEvents');
		});


		// package routes
		//
		Route::group(['middleware' => 'verify.package'], function () {

			// package creation and querying routes
			//
			Route::get('packages/all', 'Packages\PackagesController@getAll');
			Route::get('packages/{package_uuid}', 'Packages\PackagesController@getIndex');
			Route::post('packages', 'Packages\PackagesController@postCreate');
			Route::get('packages/protected/{project_uuid}', 'Packages\PackagesController@getProtected');
			Route::get('packages/protected/{project_uuid}/num', 'Packages\PackagesController@getNumProtected');
			Route::get('packages/users/{user_uuid}', 'Packages\PackagesController@getByUser');
			Route::get('packages/users/{user_uuid}/num', 'Packages\PackagesController@getNumByUser');
			Route::get('packages/owners/{user_uuid}', 'Packages\PackagesController@getByOwner');
			Route::get('packages/projects/{project_uuid}', 'Packages\PackagesController@getByProject');

			// package compatibility routes
			//
			Route::get('packages/{package_uuid}/platforms', 'Packages\PackagesController@getPackagePlatforms');

			// package version and sharing routes
			//
			Route::get('packages/{package_uuid}/versions', 'Packages\PackagesController@getVersions');
			Route::get('packages/{package_uuid}/{project_uuid}/versions', 'Packages\PackagesController@getSharedVersions');
			Route::get('packages/{package_uuid}/sharing', 'Packages\PackagesController@getSharing');
			Route::put('packages/{package_uuid}', 'Packages\PackagesController@updateIndex');
			Route::put('packages/{package_uuid}/sharing', 'Packages\PackagesController@updateSharing');
			Route::post('packages/{package_uuid}/sharing/apply-all', 'Packages\PackagesController@applyToAll');
			Route::delete('packages/{package_uuid}', 'Packages\PackagesController@deleteIndex');
			Route::delete('packages/{package_uuid}/versions', 'Packages\PackagesController@deleteVersions');
		});

		// package version dependency routes
		//
		Route::post('packages/versions/dependencies', 'Packages\PackageVersionDependenciesController@postCreate');
		Route::get('packages/{package_uuid}/versions/dependencies/recent/', 'Packages\PackageVersionDependenciesController@getMostRecent');
		Route::get('packages/versions/{package_version_uuid}/dependencies', 'Packages\PackageVersionDependenciesController@getByPackageVersion');
		Route::put('packages/versions/dependencies/{package_version_dependency_id}', 'Packages\PackageVersionDependenciesController@update');
		Route::put('packages/versions/dependencies', 'Packages\PackageVersionDependenciesController@updateAll');
		Route::delete('packages/versions/{package_version_uuid}/dependencies/{platform_version_uuid}', 'Packages\PackageVersionDependenciesController@delete');

		// package version routes
		//
		Route::group(['middleware' => 'verify.package_version'], function () {

			// package version creation routes
			//
			Route::post('packages/versions/upload', 'Packages\PackageVersionsController@postUpload');
			Route::post('packages/versions', 'Packages\PackageVersionsController@postCreate');
			Route::post('packages/versions/store', 'Packages\PackageVersionsController@postStore');
			Route::post('packages/versions/{package_version_uuid}/add', 'Packages\PackageVersionsController@postAdd');

			// newly uploaded package version file archive inspection routes
			//
			Route::get('packages/versions/new/root', 'Packages\PackageVersionsController@getNewRoot');
			Route::get('packages/versions/new/contains', 'Packages\PackageVersionsController@getNewContains');
			Route::get('packages/versions/new/file-types', 'Packages\PackageVersionsController@getNewFileTypes');
			Route::get('packages/versions/new/file-list', 'Packages\PackageVersionsController@getNewFileInfoList');
			Route::get('packages/versions/new/file-tree', 'Packages\PackageVersionsController@getNewFileInfoTree');
			Route::get('packages/versions/new/directory-list', 'Packages\PackageVersionsController@getNewDirectoryInfoList');
			Route::get('packages/versions/new/directory-tree', 'Packages\PackageVersionsController@getNewDirectoryInfoTree');

			// newly uploaded package version inspection routes
			//
			Route::get('packages/versions/new/build-system', 'Packages\PackageVersionsController@getNewBuildSystem');
			Route::get('packages/versions/new/ruby-gem-info', 'Packages\RubyPackageVersionsController@getNewRubyGemInfo');
			Route::get('packages/versions/new/python-wheel-info', 'Packages\PythonPackageVersionsController@getNewPythonWheelInfo');

			// package version file archive inspection routes
			//
			Route::get('packages/versions/{package_version_uuid}/root', 'Packages\PackageVersionsController@getRoot');	
			Route::get('packages/versions/{package_version_uuid}/contains', 'Packages\PackageVersionsController@getContains');	
			Route::get('packages/versions/{package_version_uuid}/file-types', 'Packages\PackageVersionsController@getFileTypes');
			Route::get('packages/versions/{package_version_uuid}/file-list', 'Packages\PackageVersionsController@getFileInfoList');
			Route::get('packages/versions/{package_version_uuid}/file-tree', 'Packages\PackageVersionsController@getFileInfoTree');
			Route::get('packages/versions/{package_version_uuid}/directory-list', 'Packages\PackageVersionsController@getDirectoryInfoList');
			Route::get('packages/versions/{package_version_uuid}/directory-tree', 'Packages\PackageVersionsController@getDirectoryInfoTree');

			// package version inspection routes
			//
			Route::get('packages/versions/{package_version_uuid}/build-system', 'Packages\PackageVersionsController@getBuildSystem');
			Route::get('packages/versions/{package_version_uuid}/ruby-gem-info', 'Packages\RubyPackageVersionsController@getRubyGemInfo');
			Route::get('packages/versions/{package_version_uuid}/python-wheel-info', 'Packages\PythonPackageVersionsController@getPythonWheelInfo');

			// package version sharing routes
			//
			Route::get('packages/versions/{package_version_uuid}/sharing', 'Packages\PackageVersionsController@getSharing');
			Route::put('packages/versions/{package_version_uuid}/sharing', 'Packages\PackageVersionsController@updateSharing');

			// other package version routes
			//
			Route::post('packages/versions/build-system/check', 'Packages\PackageVersionsController@postBuildSystemCheck');
			Route::get('packages/versions/{package_version_uuid}', 'Packages\PackageVersionsController@getIndex');
			Route::put('packages/versions/{package_version_uuid}', 'Packages\PackageVersionsController@updateIndex');
			Route::get('packages/versions/{package_version_uuid}/download', 'Packages\PackageVersionsController@getDownload');
			Route::delete('packages/versions/{package_version_uuid}', 'Packages\PackageVersionsController@deleteIndex');
		});

		// tool routes
		//
		Route::group(['middleware' => 'verify.tool'], function () {
			Route::post('tools', 'Tools\ToolsController@postCreate');
			Route::get('tools/protected/{project_uuid}', 'Tools\ToolsController@getProtected');
			Route::get('tools/all', 'Tools\ToolsController@getAll');
			Route::get('tools/users/{user_uuid}', 'Tools\ToolsController@getByUser');
			Route::get('tools/users/{user_uuid}/num', 'Tools\ToolsController@getNumByUser');
			Route::get('tools/projects/{project_uuid}', 'Tools\ToolsController@getByProject');
			Route::get('tools/{tool_uuid}', 'Tools\ToolsController@getIndex');
			Route::get('tools/{tool_uuid}/versions', 'Tools\ToolsController@getVersions');
			Route::get('tools/{tool_uuid}/sharing', 'Tools\ToolsController@getSharing');
			Route::get('tools/{tool_uuid}/policy', 'Tools\ToolsController@getPolicy');
			Route::post('tools/{tool_uuid}/permission', 'Tools\ToolsController@getToolPermissionStatus');
			Route::put('tools/{tool_uuid}', 'Tools\ToolsController@updateIndex');
			Route::put('tools/{tool_uuid}/sharing', 'Tools\ToolsController@updateSharing');
			Route::delete('tools/{tool_uuid}', 'Tools\ToolsController@deleteIndex');
			Route::delete('tools/{tool_uuid}/versions', 'Tools\ToolsController@deleteVersions');
		});

		// tool version routes
		//
		Route::group(['middleware' => 'verify.tool_version'], function () {
			Route::post('tools/versions/upload', 'Tools\ToolVersionsController@postUpload');
			Route::post('tools/versions', 'Tools\ToolVersionsController@postCreate');
			Route::post('tools/versions/{tool_version_uuid}/add', 'Tools\ToolVersionsController@postAdd');
			Route::get('tools/versions/{tool_version_uuid}', 'Tools\ToolVersionsController@getIndex');
			Route::put('tools/versions/{tool_version_uuid}', 'Tools\ToolVersionsController@updateIndex');
			Route::delete('tools/versions/{tool_version_uuid}', 'Tools\ToolVersionsController@deleteIndex');
		});

		// platform routes
		//
		Route::group(['middleware' => 'verify.platform'], function () {
			Route::post('platforms', 'Platforms\PlatformsController@postCreate');
			Route::get('platforms/users/{user_uuid}', 'Platforms\PlatformsController@getByUser');
			Route::get('platforms/all', 'Platforms\PlatformsController@getAll');
			Route::get('platforms/protected/{project_uuid}', 'Platforms\PlatformsController@getProtected');
			Route::get('platforms/projects/{project_uuid}', 'Platforms\PlatformsController@getByProject');
			Route::get('platforms/{platform_uuid}', 'Platforms\PlatformsController@getIndex');
			Route::get('platforms/{platform_uuid}/versions', 'Platforms\PlatformsController@getVersions');
			Route::get('platforms/{platform_uuid}/sharing', 'Platforms\PlatformsController@getSharing');
			Route::put('platforms/{platform_uuid}', 'Platforms\PlatformsController@updateIndex');
			Route::put('platforms/{platform_uuid}/sharing', 'Platforms\PlatformsController@updateSharing');
			Route::delete('platforms/{platform_uuid}', 'Platforms\PlatformsController@deleteIndex');
			Route::delete('platforms/{platform_uuid}/versions', 'Platforms\PlatformsController@deleteVersions');
		});

		// platform version routes
		//
		Route::group(['middleware' => 'verify.platform_version'], function () {
			Route::post('platforms/versions/upload', 'Platforms\PlatformVersionsController@postUpload');
			Route::post('platforms/versions', 'Platforms\PlatformVersionsController@postCreate');
			Route::post('platforms/versions/{platform_version_uuid}/add', 'Tools\PlatformVersionsController@postAdd');
			Route::get('platforms/versions/all', 'Platforms\PlatformVersionsController@getAll');
			Route::get('platforms/versions/{platform_version_uuid}', 'Platforms\PlatformVersionsController@getIndex');
			Route::put('platforms/versions/{platform_version_uuid}', 'Platforms\PlatformVersionsController@updateIndex');
			Route::delete('platforms/versions/{platform_version_uuid}', 'Platforms\PlatformVersionsController@deleteIndex');
		});

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

		// execution record routes
		//
		Route::group(['middleware' => 'verify.execution_record'], function () {
			Route::get('execution_records/all', 'Executions\ExecutionRecordsController@getAll');
			Route::get('execution_records/{execution_record_uuid}', 'Executions\ExecutionRecordsController@getIndex');
			Route::get('execution_records/{execution_record_uuid}/ssh_access', 'Executions\ExecutionRecordsController@getSshAccess');
			Route::delete('execution_records/{execution_record_uuid}', 'Executions\ExecutionRecordsController@deleteIndex');
		});

		// assessment results routes
		//
		Route::group(['middleware' => 'verify.assessment_result'], function () {
			Route::get('assessment_results/{assessment_result_uuid}/viewer/{viewer_uuid}/project/{project_uuid}', 'Assessments\AssessmentResultsController@getResults');
			Route::get('assessment_results/{assessment_result_uuid}/viewer/{viewer_uuid}/project/{project_uuid}/permission', 'Assessments\AssessmentResultsController@getResultsPermission');
			Route::get('assessment_results/viewer/{viewer_uuid}/project/{project_uuid}/permission', 'Assessments\AssessmentResultsController@getNoResultsPermission');
			Route::get('assessment_results/viewer_instance/{viewer_instance_uuid}', 'Assessments\AssessmentResultsController@getInstanceStatus');
			Route::get('v1/assessment_results/{assessment_result_uuid}/scarf', 'Assessments\AssessmentResultsController@getScarf');
		});

		// viewer routes
		//
		Route::group(['middleware' => 'verify.viewer'], function () {
			Route::get('viewers/all', 'Viewers\ViewersController@getAll');
			Route::put('viewers/default/{project_uid}/viewer/{viewer_uuid}', 'Viewers\ViewersController@setDefaultViewer');
		});

		// route introspection route
		//
		Route::group(['middleware' => 'verify.admin'], function () {
			Route::get('routes', 'Utilities\RoutesController@getActual');
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

		// app password routes for admins
		//
		Route::group(['middleware' => 'verify.admin'], function () {
			Route::get('v1/admin/users/{user_uid}/app_passwords', 'Users\AppPasswordsController@getByUser');
			Route::delete('v1/admin/users/{user_uid}/app_passwords', 'Users\AppPasswordsController@deleteByUser');
		});

	});
});
