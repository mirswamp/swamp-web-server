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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use App\Models\TimeStamps\TimeStamped;

class PackageDownloadLogEntry extends TimeStamped
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
	protected $table = 'package_download_log';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'package_download_log_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'package_uuid',
		'package_version_uuid',
		'user_uuid',
		'name',
		'version_string'
	];
}