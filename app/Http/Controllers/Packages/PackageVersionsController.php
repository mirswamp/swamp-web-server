<?php

/******************************************************************************\
|                                                                              |
|                        PackageVersionsController.php                         |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for package versions.                       |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Packages;

use PDO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Utilities\Files\Filename;
use App\Utilities\Strings\StringUtils;
use App\Utilities\Uuids\Guid;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;
use App\Models\Packages\PackageVersionSharing;
use App\Models\Packages\CPackageVersion;
use App\Models\Packages\JavaSourcePackageVersion;
use App\Models\Packages\JavaBytecodePackageVersion;
use App\Models\Packages\PythonPackageVersion;
use App\Models\Packages\RubyPackageVersion;
use App\Models\Packages\AndroidSourcePackageVersion;
use App\Models\Packages\AndroidBytecodePackageVersion;
use App\Models\Packages\WebScriptingPackageVersion;
use App\Http\Controllers\BaseController;

class PackageVersionsController extends BaseController {

	// post upload
	//
	public function postUpload() {

		// parse parameters
		//
		$file = Input::hasFile('file')? Input::file('file') : null;
		$externalUrl = Input::get('external_url');
		$useExternalUrl = filter_var(Input::get('use_external_url'), FILTER_VALIDATE_BOOLEAN);
		$checkoutArgument = Input::get('checkout_argument');
		$packageUuid = Input::get('package_uuid');

		// upload file
		//
		if ($file) {
			$uploaded = self::upload($file);
			if ($uploaded) {
				return $uploaded;
			} else {
				return response('Error uploading file.', 400);
			}

		// upload file from external url
		//
		} else if ($externalUrl) {
			if ($this->acceptableExternalUrl($externalUrl)) {
				if ($checkoutArgument) {
					$uploaded = self::uploadFromUrl($externalUrl, $checkoutArgument);
				} else {
					$uploaded = self::uploadFromUrl($externalUrl);
				}
				if ($uploaded) {
					return $uploaded;
				} else {
					return response('Error uploading file.', 400);
				}
			} else {
				return response('External URL unacceptable.', 404);
			}

		// upload new package version
		//
		} else if ($useExternalUrl && $packageUuid) {
			$package = Package::where('package_uuid', '=', $packageUuid)->first();
			if ($package && $this->acceptableExternalUrl($package->external_url)) {
				$uploaded = self::uploadFromUrl($package->external_url, $checkoutArgument);
				if ($uploaded) {
					return $uploaded;
				} else {
					return response('Error uploading file.', 400);
				}
			} else {
				return response('External URL unacceptable.', 404);
			}
		} else {
			return response('No uploaded file.', 404);
		}
	}

	public function acceptableExternalUrl($url) {
		return filter_var($url, FILTER_VALIDATE_URL);
	}

	// post add
	//
	public function postAdd($packageVersionUuid) {

		// get parameters
		//
		$packagePath = Input::get('package_path');

		// add path
		//
		return self::add($packageVersionUuid, $packagePath);
	}

	// create
	//
	public function postCreate() {
		$attributes = self::getAttributes();

		// set creation attributes
		//
		$attributes['package_version_uuid'] = Guid::create();

		// create new package version
		//
		$packageVersion = new PackageVersion($attributes);
		$packageVersion->save();

		return $packageVersion;
	}

	// post store (add and create)
	//
	public function postStore() {
		return self::store();
	}

	// post new
	//
	public function postAddNew() {

		// parse paramerers
		//
		$file = Input::file('file');

		// add new package version
		//
		$uploaded = self::upload($file);
		$packagePath = $uploaded["destination_path"]."/".$uploaded["filename"];
		$package = $this->postCreate();
		return self::add($package->package_version_uuid, $packagePath);
	}

	// get by index
	//
	public function getIndex($packageVersionUuid) {
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
		return $packageVersion;
	}

	//
	// newly uploaded package version file archive inspection methods
	//

	// get name of root directory
	//
	public function getNewRoot() {
		
		// parse parameters
		//
		$packagePath = Input::get('package_path');

		// create package appropriate to package type
		//
		$packageVersion = new PackageVersion([
			'package_path' => $packagePath
		]);

		return $packageVersion->getRoot();
	}

	// check contents
	//
	public function getNewContains() {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filename = Input::get('filename');
		$recursive = Input::get('recursive');
		$packagePath = Input::get('package_path');

		// create package appropriate to package type
		//
		$packageVersion = new PackageVersion([
			'package_path' => $packagePath
		]);

		if ($packageVersion) {
			return response()->json($packageVersion->contains($dirname, $filename, $recursive));
		} else {
			return response("Unable to check contents for package type ".$packageTypeId.".", 400);
		}
	}

	// get inventory of files types in the archive
	//
	public function getNewFileTypes() {

		// parse parameters
		//
		$dirname = Input::get('dirname');

		// create new package version
		//
		$packageVersion = new PackageVersion([
			'package_path' => Input::get('package_path')
		]);

		return $packageVersion->getFileTypes($dirname);
	}

	// get file list
	//
	public function getNewFileInfoList() {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filter = Input::get('filter');

		// create new package version
		//
		$packageVersion = new PackageVersion([
			'package_path' => Input::get('package_path')
		]);

		return $packageVersion->getFileInfoList($dirname, $filter);
	}

	// get file tree
	//
	public function getNewFileInfoTree() {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filter = Input::get('filter');
		$packagePath = Input::get('package_path');

		// create new package version
		//
		$packageVersion = new PackageVersion([
			'package_path' => $packagePath
		]);

		return $packageVersion->getFileInfoTree($dirname, $filter);
	}

	// get directory list
	//
	public function getNewDirectoryInfoList() {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filter = Input::get('filter');

		// create new package version
		//
		$packageVersion = new PackageVersion([
			'package_path' => Input::get('package_path')
		]);

		return $packageVersion->getDirectoryInfoList($dirname, $filter);
	}

	// get directory tree
	//
	public function getNewDirectoryInfoTree() {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filter = Input::get('filter');

		// create new package version
		//
		$packageVersion = new PackageVersion([
			'package_path' => Input::get('package_path')
		]);

		return $packageVersion->getDirectoryInfoTree($dirname, $filter);
	}

	//
	// newly uploaded package version inspection methods
	//

	// infer build system from contents
	//
	public function getNewBuildSystem() {

		// create package appropriate to package type
		//
		$attributes = self::getAttributes();
		$packageTypeId = self::getPackageTypeId($attributes);
		$packageVersion = self::getNewPackageVersion($packageTypeId, $attributes);

		if ($packageVersion) {
			$buildSystem = $packageVersion->getBuildSystem();

			if ($buildSystem) {
				return response($buildSystem, 200);
			} else {
				return response("Unable to find build system for package type ".$packageTypeId.".", 404);
			}
		} else {
			return response("Unable to find package version for package type ".$packageTypeId.".", 404);
		}
	}

	// infer build info from contents
	//
	public function getNewBuildInfo() {

		// create package appropriate to package type
		//
		$attributes = self::getAttributes();
		$packageTypeId = self::getPackageTypeId($attributes);
		$packageVersion = self::getNewPackageVersion($packageTypeId, $attributes);

		if ($packageVersion) {
			$buildInfo = $packageVersion->getBuildInfo();

			if ($buildInfo) {
				return response($buildInfo, 200);
			} else {
				return response("Unable to find build info for package type ".$packageTypeId.".", 404);
			}
		} else {
			return response("Unable to find package version for package type ".$packageTypeId.".", 404);
		}
	}

	//
	// package version file archive inspection methods
	//

	// get name of root directory
	//
	public function getRoot($packageVersionUuid) {

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		return $packageVersion->getRoot();
	}

	// check contents
	//
	public function getContains($packageVersionUuid) {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filename = Input::get('filename');
		$recursive = Input::get('recursive');

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		if ($packageVersion) {
			return response()->json($packageVersion->contains($dirname, $filename, $recursive));
		} else {
			return response("Unable to check contents for package type ".$packageTypeId.".", 400);
		}
	}

	// get inventory of files types in the archive
	//
	public function getFileTypes($packageVersionUuid) {

		// parse parameters
		//
		$dirname = Input::get('dirname');

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		return $packageVersion->getFileTypes($dirname);
	}

	// get file list
	//
	public function getFileInfoList($packageVersionUuid) {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filter = Input::get('filter');

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		return $packageVersion->getFileInfoList($dirname, $filter);
	}

	// get file tree
	//
	public function getFileInfoTree($packageVersionUuid) {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filter = Input::get('filter');

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		return $packageVersion->getFileInfoTree($dirname, $filter);
	}

	// get directory list
	//
	public function getDirectoryInfoList($packageVersionUuid) {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filter = Input::get('filter');

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		return $packageVersion->getDirectoryInfoList($dirname, $filter);
	}

	// get directory tree
	//
	public function getDirectoryInfoTree($packageVersionUuid) {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filter = Input::get('filter');

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		return $packageVersion->getDirectoryInfoTree($dirname, $filter);
	}

	//
	// package version inspection methods
	//

	// infer build system from contents
	//
	public function getBuildSystem($packageVersionUuid) {

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		// create package appropriate to package type
		//
		$packageTypeId = $packageVersion->package_type_id;
		$packageVersion = $this->getNewPackageVersion($packageTypeId, null);
		
		if ($packageVersion) {
			$archive = new Archive($packageVersion->getPackagePath());
			$buildSystem = $packageVersion->getBuildSystem($archive);
			if ($buildSystem) {
				return response($buildSystem, 200);
			} else {
				return response("Unable to find build system for package type ".$packageTypeId.".", 404);
			}
		} else {
			return response("Unable to find package version for package type ".$packageTypeId.".", 404);
		}
	}

	// infer build info from contents
	//
	public function getBuildInfo($packageVersionUuid) {

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		// create package appropriate to package type
		//
		$packageTypeId = $packageVersion->package_type_id;
		$packageVersion = $this->getNewPackageVersion($packageTypeId, null);
		
		if ($packageVersion) {
			$archive = new Archive($packageVersion->getPackagePath());
			$buildInfo = $packageVersion->getBuildInfo($archive);
			if ($buildInfo) {
				return response($buildInfo, 200);
			} else {
				return response("Unable to find build info for package type ".$packageTypeId.".", 404);
			}
		} else {
			return response("Unable to find package version for package type ".$packageTypeId.".", 404);
		}
	}

	//
	// package version sharing methods
	//

	// get sharing
	//
	public function getSharing($packageVersionUuid) {
		$packageVersionSharing = PackageVersionSharing::where('package_version_uuid', '=', $packageVersionUuid)->get();
		$projectUuids = [];
		for ($i = 0; $i < sizeof($packageVersionSharing); $i++) {
			array_push($projectUuids, $packageVersionSharing[$i]->project_uuid);
		}
		return $projectUuids;
	}

	// update sharing by index
	//
	public function updateSharing($packageVersionUuid) {

		// parse parameters
		//
		$projects = Input::get('projects');
		$projectUuids = Input::get('project_uuids');

		// remove previous sharing
		//
		$packageVersionSharings = PackageVersionSharing::where('package_version_uuid', '=', $packageVersionUuid)->get();
		foreach ($packageVersionSharings as $packageVersionSharing) {
			$packageVersionSharing->delete();
		}

		// create new sharing 
		//
		// Note: this is support for the old way of specifying sharing
		// which is needed for backwards compatibility with API plugins).
		//
		if ($projects) {
			$packageVersionSharings = [];
			if ($projects) {
				foreach($projects as $project) {
					$projectUid = $project['project_uid'];
					$packageVersionSharing = new PackageVersionSharing([
						'package_version_uuid' => $packageVersionUuid,
						'project_uuid' => $projectUid
					]);
					$packageVersionSharing->save();
					$packageVersionSharings[] = $packageVersionSharing;
				}
			}
		} 

		// create new sharing
		//
		if ($projectUuids) {
			$packageVersionSharings = [];
			foreach ($projectUuids as $projectUuid) {
				$packageVersionSharing = new PackageVersionSharing([
					'package_version_uuid' => $packageVersionUuid,
					'project_uuid' => $projectUuid
				]);
				$packageVersionSharing->save();
				$packageVersionSharings[] = $packageVersionSharing;
			}
		}	

		return $packageVersionSharings;
	}

	// update by index
	//
	public function updateIndex($packageVersionUuid) {

		// parse parameters
		//
		$packageVersionUuid =  Input::get('package_version_uuid');
		$packageUuid = Input::get('package_uuid');
		$versionString = Input::get('version_string');
		$languageVersion = Input::get('language_version');
		$versionSharingStatus = Input::get('version_sharing_status');
		$releaseDate = Input::get('release_date');
		$retireDate = Input::get('retire_date');
		$notes = Input::get('notes');
		$sourcePath = Input::get('source_path');
		$excludePaths = Input::get('exclude_paths');
		$configDir = Input::get('config_dir');
		$configCmd = Input::get('config_cmd');
		$configOpt = Input::get('config_opt');
		$buildFile = Input::get('build_file');
		$buildSystem = Input::get('build_system');
		$buildTarget = Input::get('build_target');
		$buildDir = Input::get('build_dir');
		$buildCmd = Input::get('build_cmd');
		$buildOpt = Input::get('build_opt');
		$useGradleWrapper = filter_var(Input::get('use_gradle_wrapper'), FILTER_VALIDATE_BOOLEAN);
		$mavenVersion = Input::get('maven_version');
		$bytecodeClassPath = Input::get('bytecode_class_path');
		$bytecodeAuxClassPath = Input::get('bytecode_aux_class_path');
		$bytecodeSourcePath = Input::get('bytecode_source_path');
		$androidSdkTarget = Input::get('android_sdk_target');
		$androidLintTarget = Input::get('android_lint_target');
		$androidRedoBuild = filter_var(Input::get('android_redo_build'), FILTER_VALIDATE_BOOLEAN);
		$androidMavenPlugin = Input::get('android_maven_plugin');

		// get model
		//
		$packageVersion = $this->getIndex($packageVersionUuid);

		// update attributes
		//
		$packageVersion->package_version_uuid = $packageVersionUuid;
		$packageVersion->package_uuid = $packageUuid;
		$packageVersion->version_string = $versionString;
		$packageVersion->language_version = $languageVersion;
		$packageVersion->version_sharing_status = $versionSharingStatus;
		$packageVersion->release_date = $releaseDate;
		$packageVersion->retire_date = $retireDate;
		$packageVersion->notes = $notes;
		$packageVersion->source_path = $sourcePath;
		$packageVersion->exclude_paths = $excludePaths;
		$packageVersion->config_dir = $configDir;
		$packageVersion->config_cmd = $configCmd;
		$packageVersion->config_opt = $configOpt;
		$packageVersion->build_file = $buildFile;
		$packageVersion->build_system = $buildSystem;
		$packageVersion->build_target = $buildTarget;
		$packageVersion->build_dir = $buildDir;
		$packageVersion->build_cmd = $buildCmd;
		$packageVersion->build_opt = $buildOpt;
		$packageVersion->use_gradle_wrapper = $useGradleWrapper;
		$packageVersion->maven_version = $mavenVersion;
		$packageVersion->bytecode_class_path = $bytecodeClassPath;
		$packageVersion->bytecode_aux_class_path = $bytecodeAuxClassPath;
		$packageVersion->bytecode_source_path = $bytecodeSourcePath;
		$packageVersion->android_sdk_target = $androidSdkTarget;
		$packageVersion->android_lint_target = $androidLintTarget;
		$packageVersion->android_redo_build = $androidRedoBuild;
		$packageVersion->android_maven_plugin = $androidMavenPlugin;

		// save and return changes
		//
		$changes = $packageVersion->getDirty();
		$packageVersion->save();
		return $changes;
	}

	// download package
	//
	public function getDownload($packageVersionUuid) {
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
		$packagePath = $packageVersion->package_path;

		// set download parameters
		//
		$filename = basename($packagePath);
		$headers = [
			  'content-type: application/octet-stream'
		];

		// download and return file
		//
		return Response::download($packagePath, $filename, $headers);
	}

	// delete by index
	//
	public function deleteIndex($packageVersionUuid) {
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
		$packageVersion->delete();
		return $packageVersion;
	}

	// post build system check
	//
	public function postBuildSystemCheck() {
		$attributes = self::getAttributes();

		// create package appropriate to package type
		//
		$packageTypeId = self::getPackageTypeId($attributes);
		$packageVersion = self::getNewPackageVersion($packageTypeId, $attributes);

		// look up package path for existing packages
		//
		if ($attributes['package_version_uuid']) {
			$existingPackageVersion = PackageVersion::where("package_version_uuid", "=", $attributes['package_version_uuid'])->first();
			$packageVersion->package_path = $existingPackageVersion->package_path;
		}

		// check build system
		//
		if ($packageVersion) {
			return $packageVersion->checkBuildSystem();
		} else {
			return response("Unable to check build system for package type ".$packageTypeId.".", 400);
		}
	}

	//
	// private utility methods
	//

	private static function getNewPackageVersion($packageTypeId, $attributes) {
		switch ($packageTypeId) {
			case 1:		// C/C++
				return new CPackageVersion($attributes);
				break;
			case 2:		// Java7 Source
				return new JavaSourcePackageVersion($attributes);
				break;
			case 3:		// Java7 Bytecode
				return new JavaBytecodePackageVersion($attributes);
				break;
			case 4:		// Python2
			case 5:		// Python3
				return new PythonPackageVersion($attributes);
				break;
			case 6:		// Android
				return new AndroidSourcePackageVersion($attributes);
				break;
			case 7:		// Ruby
			case 8:		// Sinatra
			case 9:		// Rails
			case 10:	// Padrino
				return new RubyPackageVersion($attributes);
				break;
			case 11:	// Padrino
				return new AndroidBytecodePackageVersion($attributes);
				break;
			case 12:	// Java8 Source
				return new JavaSourcePackageVersion($attributes);
				break;
			case 13:	// Java8 Bytecode
				return new JavaBytecodePackageVersion($attributes);
				break;
			case 14:	// Web Scripting
				return new WebScriptingPackageVersion($attributes);
				break;
			default:
				return null;
				break;
		}
	}

	private static function getPackageTypeId($attributes) {

		// parse parameters
		//
		$packageUuid = Input::get('package_uuid');
		$packageTypeId = Input::get('package_type_id');

		// get package type id
		//
		if ($packageUuid) {

			// existing packages
			//
			$package = Package::where('package_uuid', '=', $attributes['package_uuid'])->first();
			return $package->package_type_id;
		} else {

			// new packages
			//
			return $packageTypeId;
		}
	}

	private static function getAttributes() {
		return [
			'package_version_uuid' => Input::get('package_version_uuid'),
			'package_uuid' => Input::get('package_uuid'),

			// version attributes
			//
			'version_string' => Input::get('version_string'),
			'checkout_argument' => Input::get('checkout_argument'),
			'language_version' => Input::get('language_version'),
			'version_sharing_status' => Input::get('version_sharing_status'),

			// date / detail attributes
			//
			'release_date' => Input::get('release_date'),
			'retire_date' => Input::get('retire_date'),
			'notes' => Input::get('notes'),

			// package path attributes
			//
			'package_path' => Input::get('package_path'),
			'source_path' => Input::get('source_path'),
			'exclude_paths' => Input::get('exclude_paths'),

			// config attributes
			//
			'config_dir' => Input::get('config_dir'),
			'config_cmd' => Input::get('config_cmd'),
			'config_opt' => Input::get('config_opt'),

			// build attributes
			//
			'build_file' => Input::get('build_file'),
			'build_system' => Input::get('build_system'),
			'build_target' => Input::get('build_target'),

			'build_dir' => Input::get('build_dir'),
			'build_cmd' => Input::get('build_cmd'),
			'build_opt' => Input::get('build_opt'),

			// java source code attributes
			//
			'use_gradle_wrapper' => filter_var(Input::get('use_gradle_wrapper'), FILTER_VALIDATE_BOOLEAN),
			'maven_version' => Input::get('maven_version'),

			// java bytecode attributes
			//
			'bytecode_class_path' => Input::get('bytecode_class_path'),
			'bytecode_aux_class_path' => Input::get('bytecode_aux_class_path'),
			'bytecode_source_path' => Input::get('bytecode_source_path'),

			// android attributes
			//
			'android_sdk_target' => Input::get('android_sdk_target'),
			'android_lint_target' => Input::get('android_lint_target'),
			'android_redo_build' => filter_var(Input::get('android_redo_build'), FILTER_VALIDATE_BOOLEAN),
			'android_maven_plugin' => Input::get('android_maven_plugin')
		];
	}

	private static function upload($file) {
		$workdir = '/tmp/' . uniqid();
		$filename = null;
		$path = null;
		$extension = null;
		$mime = null;
		$size = 0;
		$workdir = false;

		// query uploaded file
		//
		$filename = $file->getClientOriginalName();
		$path = $file->getRealPath();
		$extension = $file->getClientOriginalExtension();
		$mime = $file->getMimeType();
		$size = $file->getSize();

		// replace spaces in filename with dashes
		//
		$filename = Filename::sanitize($filename);

		// replace extension with original extension
		//
		if ($extension && $extension != '') {
			$filename = pathinfo($filename, PATHINFO_FILENAME).'.'.$extension;
		}

		// move file to destination
		//
		$destinationFolder = Guid::create();
		$destinationPath = config('app.incoming').$destinationFolder;
		$uploadSuccess = $file->move($destinationPath, $filename);

		if ($workdir) {
			`rm -rf $workdir`;
		}

		if ($uploadSuccess) {
			return [
				'filename' => $filename,
				'path' => $path,
				'extension' => $extension,
				'mime' => $mime,
				'size' => $size,
				'destination_path' => $destinationFolder
			];
		} else {
			return response("Could not upload file.", 500);
		}
	}

	private static function uploadFromUrl($external_url = false, $checkout_argument = false) {
		$workdir = '/tmp/' . uniqid();
		$external_url = escapeshellcmd( $external_url );

		if (StringUtils::endsWith($external_url, '.git')) {

			// clone from GitHub
			//
            $temp = strrchr($external_url, "/");
            $dirname = substr($temp, 1, -4);

			if ($checkout_argument) {
				$result = `mkdir $workdir;
				cd $workdir;
				git clone --recursive $external_url;
				cd $dirname;
				git checkout $checkout_argument`;
			} else {
				$result = `mkdir $workdir;
			 	cd $workdir;
			 	mkdir $dirname;
			 	cd $dirname;
			 	git clone --recursive $external_url;
			 	cd ..; cd ..`;
			}

			$files = scandir($workdir);
			if (sizeof($files) !== 3) {
				`rm -rf $workdir;`;
				return response('Not a single directory.', 404);
			}

			`tar -zcf $workdir/$dirname.tar.gz -C $workdir .`;

			if (!file_exists("$workdir/$dirname.tar.gz")) {
				return response('Unable to tar project directory', 404);
			}

			$filename = Filename::sanitize($dirname).'.tar.gz';
			$path = "$workdir/$dirname.tar.gz";
			$extension = 'tar.gz';
			$mime = 'applization/x-gzip';
			$size = filesize("$workdir/$dirname.tar.gz");

			// move file to destination
			//
			$destinationFolder = Guid::create();
			$destinationPath = config('app.incoming').$destinationFolder;
			`mkdir -p $destinationPath`;
			`mv $workdir/$dirname.tar.gz $destinationPath/$filename`;
			$uploadSuccess = file_exists("$destinationPath/$filename");
		} else {
			
			// create destination folder
			//
			$destinationFolder = Guid::create();
			$destinationPath = config('app.incoming').$destinationFolder;
			`mkdir $destinationPath`;

			// replace spaces in filename with dashes
			//
			$filename = pathinfo($external_url, PATHINFO_FILENAME);
			$filename = Filename::sanitize($filename);

			// download file
			//
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $external_url);
			curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$data = curl_exec($curl);
			$uploadSuccess = (curl_errno($curl) == 0);
			curl_close($curl);
			file_put_contents("$destinationPath/$filename", $data);

			//$contents = file_get_contents($external_url);
			//$uploadSuccess = file_put_contents($destinationPath.'/'.$filename, $contents);
			$path = $destinationPath;

			// query uploaded file
			//
			$extension = pathinfo($external_url, PATHINFO_EXTENSION);
			$mime = mime_content_type($destinationPath.'/'.$filename);
			$size = filesize($destinationPath);
		}

		if ($workdir) {
			`rm -rf $workdir`;
		}

		if ($uploadSuccess) {
			return [
				'filename' => $filename,
				'path' => $path,
				'extension' => $extension,
				'mime' => $mime,
				'size' => $size,
				'destination_path' => $destinationFolder
			];
		} else {
			return response("Could not upload file.", 500);
		}
	}

	private static function add($packageVersionUuid, $packagePath) {
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		// create stored procedure call
		//
		$connection = DB::connection('package_store');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL add_package_version(
			:packageVersionUuid, :packagePath, @returnStatus, @returnMsg);");

		// bind params
		//
		$stmt->bindParam(":packageVersionUuid", $packageVersionUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":packagePath", $packagePath, PDO::PARAM_STR, 200);

		// set param values
		//
		$returnStatus = null;
		$returnMsg = null;

		// call stored procedure
		//
		$results = $stmt->execute();

		// fetch return parameters
		//
		$select = $pdo->query('SELECT @returnStatus, @returnMsg');
		$results = $select->fetchAll();
		$returnStatus = $results[0]["@returnStatus"];
		$returnMsg = $results[0]["@returnMsg"];

		// delete package version if error
		//
		if ($returnStatus == 'ERROR') {
			$packageVersion->delete();
		}

		// remove file and directory
		//
		unlink(config('app.incoming').$packagePath);
		rmdir(dirname(config('app.incoming').$packagePath));

		// return values
		//
		return response( $returnMsg, $returnStatus == 'ERROR' ? 500 : 200 );
	}

	private static function store() {
		$attributes = self::getAttributes();
		$packagePath = $attributes['package_path'];
		$packageUuid = $attributes['package_uuid'];

		// create stored procedure call
		//
		$connection = DB::connection('package_store');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL store_package_version(
			:packageUuidIn, :packagePathIn, @packagePathOut, @checksum, @returnStatus, @returnMsg);");

		// bind params
		//
		$stmt->bindParam(":packageUuidIn", $packageUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":packagePathIn", $packagePath, PDO::PARAM_STR, 200);

		// set param values
		//
		$checksum = null;
		$returnStatus = null;
		$returnMsg = null;

		// call stored procedure
		//
		$results = $stmt->execute();

		// fetch return parameters
		//
		$select = $pdo->query('SELECT @packagePathOut, @checksum, @returnStatus, @returnMsg');
		$results = $select->fetchAll();
		$packagePathOut = $results[0]["@packagePathOut"];
		$checksum = $results[0]["@checksum"];
		$returnStatus = $results[0]["@returnStatus"];
		$returnMsg = $results[0]["@returnMsg"];

		// remove file and directory
		//
		// unlink(config('app.incoming').$packagePath);
		// rmdir(dirname(config('app.incoming').$packagePath));

		// create new package version if successful
		//
		if ($returnStatus == "SUCCESS") {

			// create new package version
			//
			$packageVersion = new PackageVersion($attributes);

			// set creation attributes
			//
			$packageVersion->package_version_uuid = Guid::create();
			$packageVersion->package_path = $packagePathOut;
			$packageVersion->checksum = $checksum;

			$packageVersion->save();

			return $packageVersion;
		} else {

			// return values
			//
			return response( $returnMsg, $returnStatus == 'ERROR' ? 500 : 200 );
		}
	}
}
