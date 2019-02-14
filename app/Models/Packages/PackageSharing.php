<?php
/******************************************************************************\
|                                                                              |
|                              PackageSharing.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of the sharing associated with a package.        |
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

use App\Models\BaseModel;

class PackageSharing extends BaseModel {

	// database attributes
	//
	/*
	protected $connection = 'package_store';
	protected $table = 'package_sharing';
	*/
	protected $primaryKey = 'package_sharing_id';

	// mass assignment policy
	//
	protected $fillable = [
		'package_uuid',
		'project_uuid'
	];

	//
	// relation methods
	//
	
	public function package() {
		return $this->belongsTo('Models\Packages\Package', 'package_uuid');
	}
}