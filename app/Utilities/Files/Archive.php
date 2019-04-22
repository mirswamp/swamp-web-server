<?php
/******************************************************************************\
|                                                                              |
|                                  Archive.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for creating archive files in a                |
|        variety of formats.                                                   |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Files;

use App\Utilities\Files\BaseArchive;
use App\Utilities\Files\ZipArchive;
use App\Utilities\Files\JarArchive;
use App\Utilities\Files\TarArchive;

class Archive
{
	//
	// constructor
	//

	public static function create($path) {
		$extension = pathinfo($path, PATHINFO_EXTENSION);

		switch ($extension) {

			// zip files
			//
			case 'zip':
			case 'whl':
				return new ZipArchive($path);

			// jar files
			//
			case 'jar':
			case 'war':
			case 'ear':
				return new JarArchive($path);

			// tar files
			//
			default:
				return new TarArchive($path);	
		}
	}
}
