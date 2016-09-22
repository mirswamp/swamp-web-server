<?php
/******************************************************************************\
|                                                                              |
|                        PlatformVersionsController.php                        |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for platform versions.                      |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Platforms;

use App\Models\Platforms\PlatformVersion;
use App\Http\Controllers\BaseController;

class PlatformVersionsController extends BaseController {

	// upload
	//
	public function postUpload() {
		if (Input::hasFile('file')) {
			$file = Input::file('file');

			// query uploaded file
			//
			$filename = $file->getClientOriginalName();
			$path = $file->getRealPath();
			$extension = $file->getClientOriginalExtension();
			$mime = $file->getMimeType();
			$size = $file->getSize();

			// move file to destination
			//
			$destinationPath = 'uploads/'.str_random(8);
			$uploadSuccess = Input::file('file')->move($destinationPath, $filename);
			 
			if ($uploadSuccess) {
				return response(array(
					'filename' => $filename,
					'path' => $path,
					'extension' => $extension,
					'mime' => $mime,
					'size' => $size,
					'destination_path' => $destinationPath
				), 200);
			} else {
				return response()->json('Error moving uploaded file.', 400);
			}
		} else {
			return response('No uploaded file.', 404);
		}
	}

	// add
	//
	public function postAdd($platformVersionUuid) {
		$platformVersion = PlatformVersion::where('platform_version_uuid', '=', $platformVersionUuid)->first();

		// create stored procedure call
		//
		$connection = DB::connection('platform_shed');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL add_platform_version(
			:platformUuid, :versionString, :releaseDate, :retireDate, :commentPublic, :commentPrivate, :platformPath, :checksum, 
			:invocationCmd, :depoloymentCmd, :uploadPath, @returnString);");

		// bind params
		//
		$stmt->bindParam(":platformUuid", $platformUuid, PDO::PARAM_STR, 50);
		$stmt->bindParam(":versionString", $versionString, PDO::PARAM_STR, 50);
		$stmt->bindParam(":releaseDate", $releaseDate, PDO::PARAM_STR, 100);
		$stmt->bindParam(":retireDate", $retireDate, PDO::PARAM_STR, 100);
		$stmt->bindParam(":commentPublic", $commentPublic, PDO::PARAM_STR, 100);
		$stmt->bindParam(":commentPrivate", $commentPrivate, PDO::PARAM_STR, 100);
		$stmt->bindParam(":platformPath", $platformPath, PDO::PARAM_STR, 100);
		$stmt->bindParam(":checksum", $checksum, PDO::PARAM_STR, 100);
		$stmt->bindParam(":invocationCmd", $invocationCmd, PDO::PARAM_STR, 100);
		$stmt->bindParam(":buildOutputPath", $buildOutputPath, PDO::PARAM_STR, 100);
		$stmt->bindParam(":deploymentCmd", $deploymentCmd, PDO::PARAM_STR, 100);
		$stmt->bindParam(":uploadPath", $uploadPath, PDO::PARAM_STR, 255);

		// set param values
		//
		$platformUuid = $platformVersion->platform_uuid;
		$versionString = '1.0';
		$releaseDate = $platformVersion->release_date;
		$retireDate = $platformVersion->retire_date;
		$notes = $platformVersion->notes;
		$platformPath = $platformVersion->platform_path;
		$checksum = $platformVersion->checksum;
		$invocationCmd = $platformVersion->invocation_cmd;
		$deploymentCmd = $platformVersion->deployment_cmd;
		$buildCmd = $platformVersion->build_cmd;
		$uploadPath = 'uploads/test_new_platform.platform';
		$returnString = null;

		// call stored procedure
		//
		$results = $stmt->execute();

		// fetch return parameters
		//
		$select = $pdo->query('SELECT @returnString');
		$results = $select->fetchAll();
		$returnString = $results[0]["@returnString"];

		// return values
		//
		return $returnString;
	}

	// create
	//
	public function postCreate() {
		$platformVersion = new ToolVersion(array(
			'platform_version_uuid' => Guid::create(),
			'platform_uuid' => Input::get('platform_uuid'),
			'version_string' => Input::get('version_string'),

			'release_date' => Input::get('release_date'),
			'retire_date' => Input::get('retire_date'),
			'notes' => Input::get('notes'),

			'platform_path' => Input::get('platform_path'),
			'deployment_cmd' => Input::get('deployment_cmd')
		));
		$platformVersion->save();
		return $platformVersion;
	}

	// get all
	//
	public function getAll() {
		return PlatformVersion::all();
	}

	// get by index
	//
	public function getIndex($platformVersionUuid) {
		$platformVersion = PlatformVersion::where('platform_version_uuid', '=', $platformVersionUuid)->first();
		return $platformVersion;
	}

	// update by index
	//
	public function updateIndex($platformVersionUuid) {

		// get model
		//
		$platformVersion = $this->getIndex($platformVersionUuid);

		// update attributes
		//
		$platformVersion->platform_version_uuid = Input::get('platform_version_uuid');
		$platformVersion->platform_uuid = Input::get('platform_uuid');
		$platformVersion->version_string = Input::get('version_string');

		$platformVersion->release_date = Input::get('release_date');
		$platformVersion->retire_date = Input::get('retire_date');
		$platformVersion->notes = Input::get('notes');

		$platformVersion->platform_path = Input::get('platform_path');
		$platformVersion->deployment_cmd = Input::get('deployment_cmd');

		// save and return changes
		//
		$changes = $platformVersion->getDirty();
		$platformVersion->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex($platformVersionUuid) {
		$platformVersion = PlatformVersion::where('platform_version_uuid', '=', $platformVersionUuid)->first();
		$platformVersion->delete();
		return $platformVersion;
	}
}
