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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use PDO;
use ZipArchive;
use PharData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Utilities\Files\Archive;
use App\Models\TimeStamps\TimeStamped;
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersionSharing;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;

class PackageVersion extends TimeStamped
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'package_store';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'package_version';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'package_version_uuid';
	
	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'string';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
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
		'exclude_paths',

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
		'android_maven_plugin',

		// dot net package info
		//
		'package_info',
		'package_build_settings'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
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
		'exclude_paths',
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
		'android_maven_plugin',

		// dot net package info
		//
		'package_info',
		'package_build_settings'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'filename'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'use_gradle_wrapper' => 'boolean',
		'android_redo_build' => 'boolean'
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

	function getRoot(): string {
		$archive = Archive::create($this->getPackagePath());
		return $archive->getRoot();
	}

	function getFileContents(string $filename, string $dirname = ''): ?string {
		$zip = new ZipArchive();
		if ($zip->open($this->getPackagePath()) === true) {

			// extract file contents from zip archive
			//
			if ($this->source_path && $this->source_path != '.' && $this->source_path != './') {
				$filePath = $this->source_path . $filename;
			} else {
				$filePath = $dirname . $filename;
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
				$gemFilePath = $this->source_path . $filename;
			} else {
				$gemFilePath = $dirname . $filename;
			}

			$packagePath = $this->getPackagePath();
			$targetDir = '/tmp';

			// create archive
			//
			$phar = new PharData($packagePath);
			if ($phar->offsetExists($gemFilePath)) {
				if ($phar->extractTo($targetDir, $gemFilePath, true)) {

					// return extracted file contents
					//
					$contents = file_get_contents($targetDir . '/' . $gemFilePath);

					// clean up
					//
					exec('rm -rf ' . dirname($targetDir . '/' . $gemFilePath));

					return $contents;
				} else {
					return '';
				}
			} else {
				return null;
			}
		}
	}

	function parseKeyValueInfo(array $lines): array {
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

	public function getPackage(): Package {
		return Package::find($this->package_uuid);
	}

	public function getPackagePath(): string {
		if ($this->isNew()) {
			return rtrim(config('app.incoming'), '/') . '/' . $this->package_path;
		} else {
			return $this->package_path;
		}
	}

	//
	// sharing methods
	//

	public function isPublic(): bool {
		return $this->getSharingStatus() == 'public';
	}

	public function isProtected(): bool {
		return $this->getSharingStatus() == 'protected';
	}

	public function isPrivate(): bool {
		return $this->getSharingStatus() == 'private';
	}

	public function getSharingStatus(): string {
		return strtolower($this->version_sharing_status);
	}

	public function getSharedWith(Project $project): PackageVersionSharing {
		return PackageVersionSharing::where('package_version_uuid', '=', $this->package_version_uuid)
			->where('project_uuid', '=', $project->project_uid)->first();
	}

	public function isSharedWith(Project $project): bool {
		return PackageVersionSharing::where('package_version_uuid', '=', $this->package_version_uuid)
			->where('project_uuid', '=', $project->project_uid)->count() > 0;
	}

	public function shareWith(Project $project) {
		if (!$this->isSharedWith($project)) {
			$packageVersionSharing = new PackageVersionSharing([
				'package_version_uuid' => $this->package_version_uuid,
				'project_uuid' => $project->project_uid
			]);
			$packageVersionSharing->save();
			return $packageVersionSharing;
		} else {
			return $this->getSharedWith($project);
		}
	}

	public function isSharedBy(User $user): bool {
		$projects = $user->getProjects($user);
		foreach ($projects as $project) {
			if ($this->isSharedWith($project)) {
				return true;
			}
		}
		return false;
	}

	public function unshare() {
		$packageVersionSharings = PackageVersionSharing::where('package_version_uuid', '=', $this->package_version_uuid)->get();
		foreach ($packageVersionSharings as $packageVersionSharing) {
			$packageVersionSharing->delete();
		}
	}

	public function getProjects(?User $user = null): Collection {

		// assume current user if not specified
		//
		if (!$user) {
			$user = User::current();
		}

		$projects = $user->getProjects($user);
		$shared = collect();
		foreach ($projects as $project) {
			if ($this->isSharedWith($project)) {
				$shared->push($project);
			}
		}
		return $shared;
	}

	//
	// compatibility methods
	//

	public function getPlatformCompatibility(Platform $platform) {
		$compatibility = PackagePlatform::where('package_version_uuid', '=', $this->package_version_uuid)->
			where('platform_uuid', '=', $platform->platform_uuid)->
			whereNull('platform_version_uuid')->first();
		if ($compatibility) {
			return $compatibility->compatible_flag;
		}
	}

	public function getPlatformVersionCompatibility(PlatformVersion $platformVersion) {
		$compatibility = PackagePlatform::where('package_version_uuid', '=', $this->package_version_uuid)->
			where('platform_version_uuid', '=', $platformVersion->platform_version_uuid)->first();
		if ($compatibility) {
			return $compatibility->compatible_flag;
		}
	}

	//
	// access control methods
	//

	public function isOwnedBy(User $user): bool {
		return $this->getPackage()->isOwnedBy($user);
	}

	public function isReadableBy(User $user): bool {
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

	public function isWriteableBy(User $user): bool {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isOwnedBy($user)) {
			return true;
		} else {
			return false;
		}
	}

	//
	// package creation methods
	//
	
	/*
	 * Add the package version to the database, given the file path.
	 * Argument: The post path of the package file.
	 */
	
	public function add(string $postFilePath) {
		$fullPath = rtrim(config('app.incoming'), '/') . '/' . $postFilePath;
		$packageVersionUuid = $this->package_version_uuid;

		// create stored procedure call
		//
		$connection = DB::connection('package_store');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL add_package_version(
				:packageVersionUuid, :packagePath, @returnStatus, @returnMsg);");

		// bind params
		//
		$stmt->bindParam(":packageVersionUuid", $packageVersionUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":packagePath", $postFilePath, PDO::PARAM_STR, 200);

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

		return $returnMsg;
		// delete package version if error
		//
		if ($returnStatus == 'ERROR') {
			$this->delete();
		}

		// remove file and directory
		//
		$files = glob(dirname($fullPath).'/*'); 	// get all file names
		foreach ($files as $file) { 	// iterate files
				if (is_file($file)) {
				unlink($file); 		// delete file
			}
		}
		rmdir(dirname($fullPath));

		// return values
		//
		return $returnStatus; 
	}

	//
	// archive inspection methods
	//

	public function contains(?string $dirname, string $filename, bool $recursive = true): bool {
		$archive = Archive::create($this->getPackagePath());
		if ($recursive) {
			return $archive->found($dirname, $filename);
		} else {
			return $archive->contains($dirname, $filename);
		}
	}

	public function getFileTypes(?string $dirname): array {
		$archive = Archive::create($this->getPackagePath());
		return $archive->getFileTypes($dirname);
	}

	public function getFileInfoList(?string $dirname, ?string $filter): array {
		$archive = Archive::create($this->getPackagePath());
		return $archive->getFileInfoList($dirname, $filter);
	}

	public function getFileInfoTree(?string $dirname, ?string $filter): array {
		$archive = Archive::create($this->getPackagePath());
		return $archive->getFileInfoTree($dirname, $filter);
	}

	public function getDirectoryInfoList(?string $dirname, ?string $filter): array {		
		$archive = Archive::create($this->getPackagePath());
		return $archive->getDirectoryInfoList($dirname, $filter);
	}

	public function getDirectoryInfoTree(?string $dirname, ?string $filter): array {
		$archive = Archive::create($this->getPackagePath());
		return $archive->getDirectoryInfoTree($dirname, $filter);	
	}
}
