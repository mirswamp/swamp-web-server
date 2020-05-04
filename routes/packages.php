<?php

/******************************************************************************\
|                                                                              |
|                                 packages.php                                 |
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
// public package routes
//

Route::get('packages/public', 'Packages\PackagesController@getPublic');
Route::get('packages/types', 'Packages\PackagesController@getTypes');

// github callback route
//
Route::post('packages/github', 'Packages\PackageVersionsController@getGitHubResponse');

//
// authenticated package routes
//

Route::group(['middleware' => 'auth'], function () {

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
		Route::get('packages/{package_uuid}/projects', 'Projects\ProjectsController@getByPackage');

		// package compatibility routes
		//
		Route::get('packages/{package_uuid}/platforms', 'Packages\PackagesController@getPackagePlatforms');

		// package version routes
		//
		Route::get('packages/{package_uuid}/versions', 'Packages\PackagesController@getVersions');
		Route::get('packages/{package_uuid}/{project_uuid}/versions', 'Packages\PackagesController@getSharedVersions');
		Route::put('packages/{package_uuid}', 'Packages\PackagesController@updateIndex');
		Route::delete('packages/{package_uuid}', 'Packages\PackagesController@deleteIndex');
		Route::delete('packages/{package_uuid}/versions', 'Packages\PackagesController@deleteVersions');
	});

	// package version dependency routes
	//
	Route::post('packages/versions/dependencies', 'Packages\PackageVersionDependenciesController@postCreate');
	Route::get('packages/{package_uuid}/versions/dependencies/recent/', 'Packages\PackageVersionDependenciesController@getMostRecent');
	Route::get('packages/versions/{package_version_uuid}/dependencies', 'Packages\PackageVersionDependenciesController@getByPackageVersion');
	Route::put('packages/versions/dependencies/{package_version_dependency_id}', 'Packages\PackageVersionDependenciesController@update');
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
		Route::get('packages/versions/new/build-info', 'Packages\PackageVersionsController@getNewBuildInfo');
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
		Route::get('packages/versions/{package_version_uuid}/build-info', 'Packages\PackageVersionsController@getBuildInfo');
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
		Route::get('packages/versions/{package_vesion_uuid}/projects', 'Packages\PackageVersionsController@getProjects');
		Route::put('packages/versions/{package_version_uuid}', 'Packages\PackageVersionsController@updateIndex');
		Route::get('packages/versions/{package_version_uuid}/download', 'Packages\PackageVersionsController@getDownload');
		Route::get('packages/versions/{package_version_uuid}/download/file', 'Packages\PackageVersionsController@getDownloadFile');
		Route::get('packages/versions/{package_version_uuid}/file', 'Packages\PackageVersionsController@getFileContents');
		Route::delete('packages/versions/{package_version_uuid}', 'Packages\PackageVersionsController@deleteIndex');
	});
});