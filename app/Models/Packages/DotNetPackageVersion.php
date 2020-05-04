<?php
/******************************************************************************\
|                                                                              |
|                           DotNetPackageVersion.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of type of package version.                      |
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

use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;
use App\Utilities\Strings\StringUtils;

class DotNetPackageVersion extends PackageVersion
{
	// return sample JSON results data
	//
	const SAMPLEOUTPUT = null;

	// attribute types
	//
	protected $casts = [
		'package_info' => 'json',
		'package_build_settings' => 'json'
	];
	
	//
	// querying methods
	//

	function getBuildInfo(): array {

		// extract package to temp directory
		//
		$workdir = '/tmp/' . uniqid();
		$archive = Archive::create($this->getPackagePath());
		$archive->extractTo($workdir);

		if (self::SAMPLEOUTPUT) {

			// use dot net test output
			//
			$output = file_get_contents(base_path() . '/' . self::SAMPLEOUTPUT);
			$return = null;
		} else {

			// set environment variable for dot net utility
			//
			putenv('LANG=en_US.UTF-8');

			// run dot net package inspection utility
			//
			$dotNetPkgInfoPath = base_path() . '/bin/dotnet-pkg-info-1.0.0/bin/dotnet_pkg_info';
			$command = config('app.python') . " " . $dotNetPkgInfoPath . " " . $workdir;
			$output = null;
			$return = null;
			exec($command, $output, $return);
			$output = implode($output);
		}

		// return results
		//
		return [
			'build_system' => 'msbuild',
			'config_dir' => null,
			'config_cmd' => null,
			'config_opt' => null,
			'build_dir' => null,
			'build_file' => null,
			'build_cmd' => null,
			'build_opt' => null,
			'package_info' => json_decode($output),
			'package_build_settings' => null,
			'command' => $command,
			'error_code' => $return
		];
	}

	function checkBuildSystem(): string {
		return "ok";
	}
}
