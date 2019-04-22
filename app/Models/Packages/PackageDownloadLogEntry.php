<?php
/******************************************************************************\
|                                                                              |
|                         PackageDownloadLogEntry.php                          |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model for logging package version downloads.           |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use App\Models\TimeStamps\UserStamped;

class PackageDownloadLogEntry extends UserStamped
{
	// database attributes
	//
	protected $connection = 'package_store';
	protected $table = 'package_download_log';
	protected $primaryKey = 'package_download_log_id';

	// mass assignment policy
	//
	protected $fillable = [
		'package_uuid',
		'package_version_uuid',
		'user_uuid',
		'name',
		'version_string'
	];
}