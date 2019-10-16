<?php
/******************************************************************************\
|                                                                              |
|                                JarArchive.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for handling archive files in jar format.      |
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
use App\Utilities\Strings\StringUtils;

class JarArchive extends BaseArchive
{
	//
	// jar specific archive methods
	//

	function getFileTypes($dirname) {

		// get file names
		//
		$script = 'jar -tf '.$this->path;
		$names = [];
		exec($script, $names);

		// filter for file names
		//
		$names = $this->getFileNames($names);

		// get names that are part of directory
		//
		if ($dirname && $dirname != '.' && $dirname != './') {
			$names = $this->getNamesNestedInDirectory($names, $dirname);
		}

		// return file types of names
		//
		return $this->getFileTypesFromNames($names);
	}

	function getFileInfoList($dirname = null, $filter = null, $recursive = false) {

		// get file names
		//
		$script = 'jar -tf '.$this->path;
		$names = [];
		exec($script, $names);
		$names = $this->getFileAndDirectoryNames($names);

		// get names that are part of directory
		//
		if ($dirname) {
			$names = $this->getNamesInDirectory($names, $dirname, $recursive);
		}

		// apply filter
		//
		if ($filter) {
			$names = $this->getFilteredNames($names, $filter);
		}

		// return names converted to info
		//
		return $this->namesToInfoArray($names);
	}

	function getDirectoryInfoList($dirname = null, $filter = null, $recursive = false) {

		// get file and directory names
		//
		$script = 'jar -tf '.$this->path;
		$names = [];
		exec($script, $names);

		// filter for directory names
		//
		$names = $this->getDirectoryNames($names, $recursive);

		// apply filter
		//
		if ($filter) {
			$names = $this->getFilteredNames($names, $filter);
		}

		// return names converted to info
		//
		return $this->namesToInfoArray($names);
	}

	public function extractTo($destination, $filenames = null) {
		$tarArchive = new \PharData($this->path);

		// remove destination file / directory if already exists
		//
		if (file_exists($destination)) {
			$this->rmdir($destination);
		}

		$tarArchive->extractTo($destination, $filenames);
	}
}