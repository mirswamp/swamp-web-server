<?php
/******************************************************************************\
|                                                                              |
|                              PackageVersion.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of generic base class of package version.        |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use ZipArchive;
use PharData;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Config;
use App\Utilities\Files\Archive;
use App\Models\TimeStamps\UserStamped;
use App\Models\Packages\Package;

class PackageVersion extends UserStamped {

	// database attributes
	//
	protected $connection = 'package_store';
	protected $table = 'package_version';
	protected $primaryKey = 'package_version_uuid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [

		// attributes
		//
		'package_version_uuid',
		'package_uuid',
		'platform_uuid',

		// version attributes
		//
		'version_string',
		'checkout_argument',
		'language_version',
		'version_sharing_status',

		// date / detail attributes
		//
		'release_date',
		'retire_date',
		'notes',

		// path attributes
		//
		'package_path',
		'source_path',

		// config attributes
		//
		'config_dir',
		'config_cmd',
		'config_opt',

		// build attributes
		//
		'build_file',
		'build_system',
		'build_target',

		// advanced build attributes
		//
		'build_dir',
		'build_cmd',
		'build_opt',

		// java source code attributes
		//
		'use_gradle_wrapper',
		'maven_version',

		// java bytecode attributes
		//
		'bytecode_class_path',
		'bytecode_aux_class_path',
		'bytecode_source_path',

		// android attributes
		//
		'android_sdk_target',
		'android_lint_target',
		'android_redo_build',
		'android_maven_plugin'
	];

	// array / json conversion whitelist
	//
	protected $visible = [

		// attributes
		//
		'package_version_uuid',
		'package_uuid',
		'platform_uuid',

		// version attributes
		//
		'version_string',
		'checkout_argument',
		'language_version',
		'version_sharing_status',

		// date / detail attributes
		//
		'release_date',
		'retire_date',
		'notes',

		// path attributes
		//
		'source_path',
		'filename',

		// config attributes
		//
		'config_dir',
		'config_cmd',
		'config_opt',

		// build attributes
		//
		'build_file',
		'build_system',
		'build_target',

		// advanced build attributes
		//
		'build_dir',
		'build_cmd',
		'build_opt',

		// java source code attributes
		//
		'use_gradle_wrapper',
		'maven_version',

		// java bytecode attributes
		//
		'bytecode_class_path',
		'bytecode_aux_class_path',
		'bytecode_source_path',

		// android attributes
		//
		'android_sdk_target',
		'android_lint_target',
		'android_redo_build',
		'android_maven_plugin'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'filename'
	];

	//
	// accessor methods
	//

	public function getFilenameAttribute() {
		return basename($this->package_path);
	}

	//
	// querying methods
	//

	function getRoot() {
		$archive = new Archive($this->getPackagePath());
		return $archive->getRoot();
	}

	function getFileContents($filename, $dirname) {
		$zip = new ZipArchive();
		if ($zip->open($this->getPackagePath()) === TRUE) {

			// extract file contents from zip archive
			//
			if ($this->source_path && $this->source_path != '.') {
				$filePath = $this->source_path.$filename;
			} else {
				$filePath = $dirname.$filename;
			}
			$contents = $zip->getFromName($filePath);
			$zip->close();

			return $contents;
		} else {

			// formulate path to file
			//
			if ($dirname == '.' || $dirname == './') {
				$dirname = '';
			}

			// extract file from tar archive
			//
			if ($this->source_path) {
				$gemFilePath = $this->source_path.$filename;
			} else {
				$gemFilePath = $dirname.$filename;
			}

			$packagePath = $this->getPackagePath();
			//$targetDir = dirname($packagePath);
			$targetDir = '/tmp';

			// create archive
			//
			$phar = new PharData($packagePath);
			if ($phar->offsetExists($gemFilePath)) {
				if ($phar->extractTo($targetDir, $gemFilePath, true)) {

					// return extracted file contents
					//
					$contents = file_get_contents($targetDir.'/'.$gemFilePath);

					// clean up
					//
					exec('rm -rf '.dirname($targetDir.'/'.$gemFilePath));

					return $contents;
				} else {
					return '';
				}
			} else {
				return null;
			}
		}
	}

	function parseKeyValueInfo($lines) {
		$array = [];
		$numLines = sizeof($lines);
		$currentLine = 0;

		while ($currentLine < $numLines) {

			// go to next line
			//
			$line = $lines[$currentLine++];
			if ($line != '') {

				// parse line
				//
				if ($line[0] == "#") {
					$line = ltrim($line, '#');

					// parse comment
					//
					array_push($array, [
						'comment' => trim($line)
					]);
				} else {

					// parse line
					//
					$pair = explode(':', $line);
					$key = $pair[0];
					$value = $pair[1];

					array_push($array, [
						$key => $value
					]);
				} 
			}
		}

		return $array;
	}

	public function getPackage() {
		return Package::where('package_uuid', '=', $this->package_uuid)->first();
	}

	public function getPackagePath() {
		if ($this->isNew()) {
			return config('app.incoming').$this->package_path;
		} else {
			return $this->package_path;
		}
	}

	//
	// sharing methods
	//

	public function isPublic() {
		return $this->getSharingStatus() == 'public';
	}

	public function isProtected() {
		return $this->getSharingStatus() == 'protected';
	}

	public function isPrivate() {
		return $this->getSharingStatus() == 'private';
	}

	public function getSharingStatus() {
		return strtolower($this->version_sharing_status);
	}

	public function isSharedWith($project) {
		return PackageVersionSharing::where('package_version_uuid', '=', $this->package_version_uuid)
			->where('project_uuid', '=', $project->project_uid)->count() > 0;
	}

	public function isSharedBy($user) {
		$projects = $user->getProjects();
		foreach ($projects as $project) {
			if ($this->isSharedWith($project)) {
				return true;
			}
		}
		return false;
	}

	//
	// compatibility methods
	//

	public function getPlatformCompatibility($platform) {
		$compatibility = PackagePlatform::where('package_version_uuid', '=', $this->package_version_uuid)->
			where('platform_uuid', '=', $platform->platform_uuid)->
			whereNull('platform_version_uuid')->first();
		if ($compatibility) {
			return $compatibility->compatible_flag;
		}
	}

	public function getPlatformVersionCompatibility($platformVersion) {
		$compatibility = PackagePlatform::where('package_version_uuid', '=', $this->package_version_uuid)->
			where('platform_version_uuid', '=', $platformVersion->platform_version_uuid)->first();
		if ($compatibility) {
			return $compatibility->compatible_flag;
		}
	}

	//
	// access control methods
	//

	public function isOwnedBy($user) {
		return $this->getPackage()->isOwnedBy($user);
	}

	public function isReadableBy($user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isPublic()) {
			return true;
		} else if ($this->getPackage()->isOwnedBy($user)) {
			return true;
		} else if ($this->isSharedBy($user)) {
			return true;
		} else {
			return false;
		}
	}

	public function isWriteableBy($user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isOwnedBy($user)) {
			return true;
		} else {
			return false;
		}
	}

	//
	// archive inspection methods
	//

	public function contains($dirname, $filename, $recursive) {
		$archive = new Archive($this->getPackagePath());
		if ($recursive) {
			return $archive->found($dirname, $filename);
		} else {
			return $archive->contains($dirname, $filename);
		}
	}

	public function getFileTypes($dirname) {
		$archive = new Archive($this->getPackagePath());
		return $archive->getFileTypes($dirname);
	}

	public function getFileInfoList($dirname, $filter) {
		$archive = new Archive($this->getPackagePath());
		return $archive->getFileInfoList($dirname, $filter);
	}

	public function getFileInfoTree($dirname, $filter) {
		$archive = new Archive($this->getPackagePath());
		return $archive->getFileInfoTree($dirname, $filter);
	}

	public function getDirectoryInfoList($dirname, $filter) {		
		$archive = new Archive($this->getPackagePath());
		return $archive->getDirectoryInfoList($dirname, $filter);
	}

	public function getDirectoryInfoTree($dirname, $filter) {
		$archive = new Archive($this->getPackagePath());
		return $archive->getDirectoryInfoTree($dirname, $filter);	
	}
}
