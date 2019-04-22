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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Packages;

use PDO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use App\Models\Users\User;
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
use App\Models\Packages\DotNetPackageVersion;
use App\Models\Packages\PackageDownloadLogEntry;
use App\Models\Assessments\AssessmentRun;
use App\Models\Assessments\AssessmentResult;
use App\Models\RunRequests\RunRequest;
use App\Models\Assessments\AssessmentRunRequest;
use App\Models\Projects\Project;
use App\Http\Controllers\BaseController;
use App\Utilities\Files\Archive;
use App\Utilities\Files\Filename;
use App\Utilities\Strings\StringUtils;
use App\Utilities\Uuids\Guid;

class PackageVersionsController extends BaseController
{
	// post upload
	//
	public function postUpload() {

		// parse parameters
		//
		$packageUuid = Input::get('package_uuid');
		$file = Input::hasFile('file')? Input::file('file') : null;
		$checkoutArgument = Input::get('checkout_argument');

		// find package that version belongs to
		//
		$package = Package::find($packageUuid);
		if ($package) {
			$externalUrl = $package->external_url;
			$useExternalUrl = $package->external_url != null;
		} else {
			$externalUrl = Input::get('external_url');
			$useExternalUrl = filter_var(Input::get('use_external_url'), FILTER_VALIDATE_BOOLEAN);
		}
		
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
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
		return $packageVersion->add($packagePath);
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

		// share package version with current user's trial project
		//
		$currentUser = User::getIndex(session('user_uid'));
		if ($currentUser) {
			$packageVersion->shareWith($currentUser->getTrialProject());
		}

		return $packageVersion;
	}

	// post store (add and create)
	//
	public function postStore() {
		$attributes = self::getAttributes();	
		return self::store($attributes);
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
		$packageVersion = $this->postCreate();
		$packageVersion->add($packagePath);	
		#return self::add($package->package_version_uuid, $packagePath);
	}

	// get by index
	//
	public function getIndex($packageVersionUuid) {
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
		return $packageVersion;
	}

	// get by projects associated with package version
	//
	public function getProjects($packageVersionUuid) {
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
		return $packageVersion->getProjects();
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

	// get bug count for file or directory
	//
	public function getBugCount($filename, $bugInstances, $include, $exclude) {
		$count = 0;	

		if ($include) {

			// count bug instances in include filter
			//
			foreach ($bugInstances as $bugInstance) {
				if (in_array($bugInstance->BugCode, $include)) {
					foreach ($bugInstance->BugLocations as $bugLocation) {
						if ($bugLocation->primary) {
							if (StringUtils::startsWith($bugLocation->SourceFile, 'pkg1/' . $filename)) {
								$count++;
							}
						}
					}
				}
			}
		} else if ($exclude) {

			// count bug instances not in exclude filter
			//
			foreach ($bugInstances as $bugInstance) {
				if (!in_array($bugInstance->BugCode, $exclude)) {
					foreach ($bugInstance->BugLocations as $bugLocation) {
						if ($bugLocation->primary) {
							if (StringUtils::startsWith($bugLocation->SourceFile, 'pkg1/' . $filename)) {
								$count++;
							}
						}
					}
				}
			}
		} else {

			// count bug instances
			//
			foreach ($bugInstances as $bugInstance) {
				foreach ($bugInstance->BugLocations as $bugLocation) {
					if ($bugLocation->primary) {
						if (StringUtils::startsWith($bugLocation->SourceFile, 'pkg1/' . $filename)) {
							$count++;
						}
					}
				}
			}
		}

		return $count;
	}

	// get file list
	//
	public function getFileInfoList($packageVersionUuid) {

		// parse parameters
		//
		$dirname = Input::get('dirname');
		$filter = Input::get('filter');
		$include = Input::get('include');
		$exclude = Input::get('exclude');
		$assessmentResultUuid = Input::get('assessment_result_uuid');

		// cast filters to arrays, if necessary
		//
		if ($include && !is_array($include)) {
			$include = [$include];
		}
		if ($exclude && !is_array($exclude)) {
			$exclude = [$excude];
		}

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
		$fileInfoList = $packageVersion->getFileInfoList($dirname, $filter);

		// annotate list with counts
		//
		if ($assessmentResultUuid) {
			$result = AssessmentResult::where('assessment_result_uuid', '=', $assessmentResultUuid)->first();
			$data = json_decode($result->getNativeResultsData()); 
			for ($i = 0; $i < count($fileInfoList); $i++) {
				$filename = $fileInfoList[$i]['name'];
				$bugInstances = $data->AnalyzerReport->BugInstances;
				$fileInfoList[$i]['bug_count'] = $this->getBugCount($filename, $bugInstances, $include, $exclude);
			}
		}

		return $fileInfoList;
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
		$package = $packageVersion->getPackage();

		// create package appropriate to package type
		//
		$attributes = $packageVersion->getAttributes();
		$packageVersion = $this->getNewPackageVersion($package->package_type_id, $attributes);
		
		if ($packageVersion) {
			$archive = Archive::create($packageVersion->getPackagePath());
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
		$buildDir = Input::get('build_dir');

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
		$package = $packageVersion->getPackage();

		// create package appropriate to package type
		//
		$attributes = $packageVersion->getAttributes();
		$packageVersion = $this->getNewPackageVersion($package->package_type_id, $attributes);
		
		if ($packageVersion) {
			$buildInfo = $packageVersion->getBuildInfo($buildDir);
			if ($buildInfo) {
				return response($buildInfo, 200);
			} else {
				return response("Unable to find build info for package type ".$package->package_type_id.".", 404);
			}
		} else {
			return response("Unable to find package version for package type ".$package->package_type_id.".", 404);
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

		// find package version
		//
		$packageVersion = PackageVersion::find($packageVersionUuid);

		// remove previous sharing
		//
		$packageVersion->unshare();

		// share package version with current user's trial project
		//
		$currentUser = User::getIndex(session('user_uid'));
		if ($currentUser) {
			$packageVersion->shareWith($currentUser->getTrialProject());
		}

		// create new sharing 
		//
		// Note: this is support for the old way of specifying sharing
		// which is needed for backwards compatibility with API plugins).
		//
		if ($projects) {
			$packageVersionSharings = [];
			if ($projects) {
				foreach ($projects as $project) {
					$projectUid = $project['project_uid'];

					// find project
					//
					$project = Project::where('project_uid', '=', $projectUid)->first();

					// creating sharing
					//
					if ($project) {
						$packageVersionSharings[] = $packageVersion->shareWith($project);
					}
				}
			}
		} 

		// create new sharing
		//
		if ($projectUuids) {
			$packageVersionSharings = [];
			foreach ($projectUuids as $projectUuid) {

				// find project
				//
				$project = Project::where('project_uid', '=', $projectUuid)->first();

				// create sharing
				//
				if ($project) {
					$packageVersionSharings[] = $packageVersion->shareWith($project);
				}
			}
		}	

		return $packageVersionSharings;
	}

	// update by index
	//
	public function updateIndex($packageVersionUuid) {

		// parse parameters
		//
		$packageVersionUuid = Input::get('package_version_uuid');
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
		$packageInfo = Input::get('package_info');
		$packageBuildSettings = Input::get('package_build_settings');

		// convert json to strings for storage
		//
		if ($packageInfo && !is_string($packageInfo)) {
			$packageInfo = json_encode($packageInfo);
		}
		if ($packageBuildSettings && !is_string($packageBuildSettings)) {
			$packageBuildSettings = json_encode($packageBuildSettings);
		}

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
		$packageVersion->package_info = $packageInfo;
		$packageVersion->package_build_settings = $packageBuildSettings;

		// save and return changes
		//
		$changes = $packageVersion->getDirty();
		$packageVersion->save();
		return $changes;
	}

	// download package
	//
	public function getDownload($packageVersionUuid) {

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		// set download parameters
		//
		$packagePath = $packageVersion->package_path;
		$filename = basename($packagePath);
		$headers = [
			  'content-type: application/octet-stream'
		];

		// log package version download
		//
		$logEntry = new PackageDownloadLogEntry([
			'package_uuid' => $packageVersion->package_uuid,
			'package_version_uuid' => $packageVersion->package_version_uuid,
			'user_uuid' => Session::get('user_uid'),
			'name' => $packageVersion->getPackage()->name,
			'version_string' => $packageVersion->version_string
		]);
		$logEntry->save();

		// download and return file
		//
		return Response::download($packagePath, $filename, $headers);
	}

	// download file
	//
	public function getDownloadFile($packageVersionUuid) {

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		// find file paths
		//
		$filePath = Input::get('path');
		$packagePath = $packageVersion->package_path;
		$packageDir = dirname($packagePath);

		// extract file from achive
		//
		$archive = Archive::create($packageVersion->getPackagePath());
		$archive->extractTo($packageDir, [$filePath]);

		// set download parameters
		//
		$filename = basename($filePath);
		$headers = [
			  'content-type: application/octet-stream'
		];

		// download and return file
		//
		return Response::download($packageDir . '/' . $filePath, $filename, $headers);
	}

	// download file
	//
	public function getFileContents($packageVersionUuid) {

		// find package version
		//
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		// find paths
		//
		$filePath = Input::get('path');
		$packagePath = $packageVersion->package_path;
		$packageDir = dirname($packagePath);

		// extract specified contents from archive file
		//
		$archive = Archive::create($packageVersion->package_path);
		return $archive->extractContents($filePath);
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
			case 15:	// .NET
				return new DotNetPackageVersion($attributes);
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
		$attributes = [
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
			'android_maven_plugin' => Input::get('android_maven_plugin'),

			// dot net attributes
			//
			'package_info' => Input::get('package_info'),
			'package_build_settings' => Input::get('package_build_settings')
		];

		// convert json to strings for storage
		//
		if ($attributes['package_info'] && !is_string($attributes['package_info'])) {
			$attributes['package_info'] = json_encode($attributes['package_info']);
		}
		if ($attributes['package_build_settings'] && !is_string($attributes['package_build_settings'])) {
			$attributes['package_build_settings'] = json_encode($attributes['package_build_settings']);
		}

		return $attributes;
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

	// increment the version string
	//
	private function incrementVersion($version){
		if ($version) {
			$versions = explode('.', $version);
			$last = $versions[count($versions)-1];
			$versions[count($versions)-1] = (string)((int)$last+1);
			$ret = '';
			for ($i = 0; $i < count($versions)-1; $i++){
				$ret .= $versions[$i].'.';
			}
			$ret .= $versions[count($versions)-1];
			return $ret;
		} else {
			return '1.0';
		}

	}

	// Function to authorize the Request by using user's secret token.
	//
	private function authorizeToken($secretToken, $payloadContent, $githubSignature) {
		$hashResult = 'sha1='.hash_hmac('sha1', $payloadContent, $secretToken);#'gitkey'
		if ($hashResult == $githubSignature) {
			return true;
		}
		return false;
	}

	// This function handles HTTP request sent from Github webhook when a push event is triggered.
	// This function will verify the credential for the comming request based on the secret token set by 
	// the swamp user. Then a new package version will be created with the latest version of code pull from Github.
	//
	public function getGitHubResponse(Request $request) {
		$attributes = Input::get('payload');

		// first we check if the request is a ping event or a push event
		//
		$githubEvent = $_SERVER['HTTP_X_GITHUB_EVENT'];
		if ($githubEvent != 'ping' && $githubEvent != 'push') {
			return "Unknown Github Event.";
		}

		$data = json_decode($attributes);
		if (is_null($data)) {
			return "Json decode failed.";
		}

		// extract information from HTTP request
		//
		$clone_url = $data->repository->clone_url;
		if ($githubEvent == 'push') {
			$commitHash = $data->commits[0]->id;
			$timeStamp = $data->commits[0]->timestamp;
			$refName = $data->ref;
			$refList = explode('/', $refName);
			$branchName = end($refList); 
			$authorName = $data->commits[0]->author->name;
		}

		// first we find all packages associated with the clone url
		//
		$packages = Package::where('external_url', '=', $clone_url)
			->orWhere('external_url', '=', preg_replace('/\.git$/', '', $clone_url))
			->get();
	
		// loop and update all git packages with the url
		//
		for ($i = 0; $i < count($packages); $i++) {
			$attributes = $packages[$i]->getAttributes();
			
			// authentication using github secret key
			//
			if (self::authorizeToken($attributes['secret_token'], file_get_contents('php://input'),$_SERVER['HTTP_X_HUB_SIGNATURE'])) {
				if ($githubEvent == 'push') {

					// update the git package
					//
					self::updateGitPackage($attributes['package_uuid'], $commitHash, $timeStamp, $branchName, $authorName);
				}	
			}
		}

		// Nothing is matched in the database
		//
		$returnString = ' event failed. No associated package is found in SWAMP. Please create a package in SWAMP.';
		return $returnString;	
	}

	// This function clones the latest code from github and creates and new package version based on that.
	// Then the associated assessment run is found if the user has set an on update run schedule on the assessment.
	// New assessment run request will be created and executed.
	//
	private function updateGitPackage($packageUuid, $commitHash, $timeStamp, $branchName, $authorName){
		$package = $packageUuid? Package::where('package_uuid', '=', $packageUuid)->first() : null;
	
		// find the lasted version regardless of the project
		//
		if ($package) {

			// get the latest package version regardless of project
			//
			$packageVersion = $package->getLatestVersion(null);
		} else {
			return response('Cannot find the package.', 400);
		}

		// fetch github link for database
		//
		$packageUrl = $package->external_url;

		// git upload the package from url
		//
		$uploaded = self::uploadFromGitUrl($packageUrl, $branchName);

		if ($uploaded) {

			// create a new package version
			//
			if ($packageVersion) {
				$attributes = $packageVersion->getAttributes();
				$attributes['package_version_uuid'] = Guid::create();
				$attributes['version_string'] = self::incrementVersion($attributes['version_string']).' (Update from Github)';	
				$attributes['notes'] = 'Cloned commit {'.$commitHash.'} from branch {'.$branchName.'} at '.$timeStamp.' by '.$authorName;
				$attributes['version_no']++;	
				$newPackageVersion = new PackageVersion($attributes);
				
				// store the new package version
				//
				$newPackageVersion->save();
					
				// move to permanant location
				//
				$fullPath = $uploaded['destination_path'].'/'.$uploaded['filename'];
				$newPackageVersion->add($fullPath);		

				// duplicate package version sharing
				//
				$packageVersionSharings = PackageVersionSharing::where('package_version_uuid', '=', $packageVersion->package_version_uuid)
						->get();
				for ($i = 0; $i < sizeof($packageVersionSharings); $i++) {
					$packageVersionSharing = $packageVersionSharings[$i];
					$newPackageVersionSharing = new PackageVersionSharing([
						'project_uuid' => $packageVersionSharing->project_uuid,
						'package_version_uuid' => $newPackageVersion->package_version_uuid
					]);
					$newPackageVersionSharing->save();
				}
			} else {
				return response('Cannot find the package version', 400);
			}
		} else {
			return response('Error updating file.', 400);
		}
	
		// find 'on push' and 'one-time' run requests
		//
		$onUpdateRequest = RunRequest::where('name', '=', 'On push')
			->where('project_uuid', '=', null)
			->first();
		$oneTimeRequest = RunRequest::where('name', '=', 'One-time')
			->where('project_uuid', '=', null)
			->first();
		
		if ($onUpdateRequest) {

			// fetch all 'on push' assessment run requests
			//
			$runRequests = AssessmentRunRequest::where('run_request_id', '=', $onUpdateRequest->run_request_id)
				->get();
			foreach ($runRequests as $req) {

				// find the assessment runs equal to the current assessment_run_id
				//
				$assessmentRuns = AssessmentRun::where('assessment_run_id', '=', $req->assessment_run_id)
					->get();

				// we find the one matches the package uuid
				//
				foreach ($assessmentRuns as $run){
					if ($run->package_uuid == $packageUuid) {

						// create new assessment run request based on the current
						//
						$newAssessmentRunRequest = new AssessmentRunRequest([
							'assessment_run_id' => $run->assessment_run_id,
							'run_request_id' => $oneTimeRequest->run_request_id,
							'user_uuid' => $req->user_uuid,
							'notify_when_complete_flag' => true
						]);

						// start execute the assessment request
						//
						$newAssessmentRunRequest->save();
					}
				}
			}		
		} else {
			return response('Cannot find on Update run request', 400);
		}	

	   return $packageVersion;
	}

	private static function uploadFromGitUrl($external_url, $checkout_argument = null) {
		$workdir = '/tmp/' . uniqid();
		$external_url = escapeshellcmd($external_url);	

		// find name of directory to create
		//
		$temp = strrchr($external_url, "/");
		if (StringUtils::endsWith($temp, '.git')) {
			$dirname = substr($temp, 1, -4);
		} else {
			$dirname = substr($temp, 1);
		}

		// clone / checkout the package version
		//
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

		// zip the directory into a tar.gz
		//
		if ($checkout_argument) {
			`tar -zcf $workdir/$dirname.tar.gz -C $workdir .`;
		} else {
			`tar -zcf $workdir/$dirname.tar.gz -C $workdir/$dirname .`;
		}

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
		$filePath = "$destinationPath/$filename";

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
				'destination_path' => $destinationFolder,
				'file_path' => $filePath
			];
		} else {
			return response("Could not upload file.", 500);
		}
	}

	private static function uploadFromArchiveUrl($external_url = false) {
		$workdir = '/tmp/' . uniqid();
		$external_url = escapeshellcmd($external_url);

		// create destination folder
		//
		$destinationFolder = Guid::create();
		$destinationPath = config('app.incoming').$destinationFolder;
		`mkdir $destinationPath`;

		// replace spaces in filename with dashes
		//
		$filename = pathinfo($external_url, PATHINFO_FILENAME);
		$extension = pathinfo($external_url, PATHINFO_EXTENSION);
		$filename = Filename::sanitize($filename).'.'.$extension;

		// download file
		//
		/*
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $external_url);
		curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($curl);
		$uploadSuccess = (curl_errno($curl) == 0);
		curl_close($curl);
		file_put_contents("$destinationPath/$filename", $data);
		*/

		$size = file_put_contents($destinationPath.'/'.$filename, fopen($external_url, 'r'));
		$path = $destinationPath;
		$uploadSuccess = ($size != 0);

		// query uploaded file
		//
		$extension = pathinfo($external_url, PATHINFO_EXTENSION);
		$mime = mime_content_type($destinationPath.'/'.$filename);
		$filePath = "$destinationPath/$filename";

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
				'destination_path' => $destinationFolder,
				'file_path' => $filePath
			];
		} else {
			return response("Could not upload file.", 500);
		}

	}

	private static function uploadFromUrl($external_url = false, $checkout_argument = false) {
		$external_url = escapeshellcmd($external_url);
		$extension = pathinfo($external_url, PATHINFO_EXTENSION);

		if ($extension == 'git' || !$extension || $extension == '') {
			return self::uploadFromGitUrl($external_url, $checkout_argument);
		} else {
			return self::uploadFromArchiveUrl($external_url);
		}
	}

	private static function store($attributes) {
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

			// share package version with current user's trial project
			//
			$currentUser = User::getIndex(session('user_uid'));
			if ($currentUser) {
				$packageVersion->shareWith($currentUser->getTrialProject());
			}

			return $packageVersion;
		} else {

			// return values
			//
			return response( $returnMsg, $returnStatus == 'ERROR' ? 500 : 200 );
		}
	}
}
