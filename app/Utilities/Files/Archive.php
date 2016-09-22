<?php
/******************************************************************************\
|                                                                              |
|                                  Archive.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for handling archive files in a                |
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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Files;

use ZipArchive;
use App\Utilities\Strings\StringUtils;

class Archive {

	//
	// constructor
	//

	public function __construct($path) {
		$this->path = $path;
	}

	//
	// static path related utility methods
	//

	static function toPath($str) {
		if ($str && $str != '') {
			if ($str == '.') {
				return '';
			} else if (!StringUtils::endsWith($str, '/')) {
				return $str.'/';
			}
		}
		return $str;
	}

	static function normalizePaths(&$path, &$file) {

		// normalize dot path
		//
		if ($path == '.' || $path == './') {
			$path = '';
		}

		// back out of path if filename starts with ../
		//
		while (StringUtils::startsWith($file, '../')) {

			// strip leading ../
			//
			$file = substr($file, 3);

			// strip trailing slash
			//
			$path = substr($path, 0, strlen($path) - 1);

			// back up one level in path
			//
			if (strpos($path, '/') != false) {

				// strip from end until next slash
				//
				$path = substr($path, 0, strrpos($path, '/') + 1);
			} else {

				// path is empty
				//
				$path = '';
			}
		}

		// move directory names from file to path
		//
		$slashPosition = strpos($file, '/');
		if ($slashPosition) {
			$filepath = substr($file, 0, $slashPosition + 1);
			$filename = substr($file, $slashPosition + 1);
			$path = $path.$filepath;
			$file = $filename;
		}
	}

	static function concatPaths($path1, $path2) {
		self::normalizePaths($path1, $path2);
		$path = self::toPath($path1).self::toPath($path2);
		if ($path == '') {
			$path = '.';
		}
		return $path;
	}

	//
	// public methods
	//

	public function getExtension() {
		return pathinfo($this->path, PATHINFO_EXTENSION);
	}

	public function isZipped() {
		return $this->getExtension() == 'zip' || 
			$this->getExtension() == 'Z' || $this->getExtension() == 'gz';
	}

	public function contains($dirname, $filename) {
		self::normalizePaths($dirname, $filename);
		if ($dirname && $dirname != '.') {
			$path = $dirname.$filename;
		} else {
			$path = $filename;
		}
		$info = $this->getFileInfoList(null, $path);
		$names = self::infoArrayToNames($info);

		// strip leading ./ from names
		//
		for ($i = 0; $i < sizeof($names); $i++) {
			if (StringUtils::startsWith($names[$i], './')) {
				$names[$i] = substr($names[$i], 2);
			}
		}

		return in_array($path, $names);
	}

	public function found($dirname, $filter) {
		self::normalizePaths($dirname, $filter);
		return sizeof($this->getFileInfoList($dirname, $filter, true)) != 0;
	}

	public function getFileInfoList($dirname, $filter, $recursive = false) {
		switch ($this->getExtension()) {

			// zip files
			//
			case 'zip':
			case 'whl':
				$fileInfoList = $this->getZipFileInfoList($dirname, $filter, $recursive);
				break;

			// jar files
			//
			case 'jar':
			case 'war':
			case 'ear':
				$fileInfoList = $this->getJarFileInfoList($dirname, $filter, $recursive);
				break;

			// tar files
			//
			default:
				$fileInfoList = $this->getTarFileInfoList($dirname, $filter, $recursive);
		}

		return $fileInfoList;
	}

	public function getDirectoryInfoList($dirname, $filter, $recursive = false) {
		switch ($this->getExtension()) {

			// zip files
			//
			case 'zip':
			case 'whl':
				$directoryInfoList = $this->getZipDirectoryInfoList($dirname, $filter, $recursive);
				break;

			// jar files
			//
			case 'jar':
			case 'war':
			case 'ear':
				$directoryInfoList = $this->getJarDirectoryInfoList($dirname, $filter, $recursive);
				break;

			// tar files
			//
			default:
				$directoryInfoList = $this->getTarDirectoryInfoList($dirname, $filter, $recursive);
		}

		return $directoryInfoList;
	}

	public function getFileInfoTree($dirname, $filter) {
		$list = $this->getFileInfoList($dirname, $filter);
		if ($dirname) {
			return $list;
		} else {
			return self::directoryInfoListToTree($list);
		}
	}

	public function getDirectoryInfoTree($dirname, $filter) {
		$list = $this->getDirectoryInfoList($dirname, $filter);
		if ($dirname) {
			return $list;
		} else {
			return self::directoryInfoListToTree($list);
		}
	}

	public function getFileTypes($dirname) {
		switch ($this->getExtension()) {

			// zip files
			//
			case 'zip':
			case 'whl':
				$fileTypes = $this->getZipFileTypes($dirname);
				break;

			// jar files
			//
			case 'jar':
			case 'war':
			case 'ear':
				$fileTypes = $this->getJarFileTypes($dirname);
				break;

			// tar files
			//
			default:
				$fileTypes = $this->getTarFileTypes($dirname);
		}

		return $fileTypes;
	}

	//
	// private attributes
	//

	private $path;

	//
	// private directory utility methods
	//

	private static function isDirectoryName($path) {
		return $path[strlen($path) - 1] == '/';
	}

	private static function addName($name, &$dirnames) {
		if (!in_array($name, $dirnames)) {

			// add directory of name
			//
			$dirname = dirname($name);
			if ($dirname != '.') {
				self::addName($dirname.'/', $dirnames);
			}

			// add name
			//
			array_push($dirnames, $name);
		}
	}

	private static function getFileAndDirectoryNames($names) {
		$dirnames = array();

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			if (!in_array($name, $dirnames)) {

				// add directory of name
				//
				$dirname = dirname($name);
				if ($dirname != '.') {
					self::addName($dirname.'/', $dirnames);
				}

				// add file or directory
				//
				array_push($dirnames, $name);
			}
		}

		return $dirnames;
	}

	//
	// private file and directory name filtering methods
	//

	private static function getDirectoryNames($names) {
		$directoryNames = array();

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			if (self::isDirectoryName($name)) {
				array_push($directoryNames, $name);
			}
		}

		return $directoryNames;
	}

	private static function getFileNames($names) {
		$fileNames = array();

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			if (!self::isDirectoryName($name)) {
				array_push($fileNames, $name);
			}
		}

		return $fileNames;
	}

	//
	// private name / directory filtering methods
	//

	private static function getNamesInDirectory($names, $dirname, $recursive = false) {

		// return all if no dirname
		//
		if (!$dirname) {
			$dirnames = array();
			for ($i = 0; $i < sizeof($names); $i++) {
				$name = $names[$i];

				// strip leading ./
				//
				if (StringUtils::startsWith($name, './')) {
					$name = substr($name, 2);
				}

				array_push($dirnames, $name);
			}
			return $dirnames;
		}

		// get names that are part of target directory
		//
		$dirnames = array();
		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];

			// strip leading ./
			//
			if (StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			if ($recursive) {
				if (StringUtils::startsWith(dirname($name).'/', $dirname)) {
					array_push($dirnames, $name);
				}
			} else {
				if (dirname($name).'/' == $dirname) {
					array_push($dirnames, $name);
				}
			}
		}

		return $dirnames;
	}

	private static function getNamesNestedInDirectory($names, $dirname) {

		// return all if no dirname
		//
		if (!$dirname) {
			$dirnames = array();
			for ($i = 0; $i < sizeof($names); $i++) {
				$name = $names[$i];

				// strip leading ./
				//
				if (StringUtils::startsWith($name, './')) {
					$name = substr($name, 2);
				}

				array_push($dirnames, $name);
			}
			return $dirnames;
		}

		// get names that are part of target directory (nested)
		//
		$dirnames = array();
		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];

			// strip leading ./
			//
			if (StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			if (StringUtils::startsWith($name, $dirname)) {
				array_push($dirnames, $name);
			}
		}

		return $dirnames;
	}

	private static function getFilteredNames($names, $filter) {

		// return all if no filter
		//
		if (!$filter) {
			return $names;
		}

		// get names that are inside of target directory
		//
		$filteredNames = array();
		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			if ($filter == $name || $filter == basename($name)) {
				array_push($filteredNames, $name);
			}
		}

		return $filteredNames;
	}

	//
	// private file name / info conversion methods
	//

	private static function nameToInfo($name) {
		return array(
			'name' => $name
		);
	}

	private static function namesToInfoArray($names) {
		$info = array();
		for ($i = 0; $i < sizeof($names); $i++) {
			array_push($info, self::nameToInfo($names[$i]));
		}
		return $info;
	}

	private static function infoArrayToNames($info) {
		$names = array();
		for ($i = 0; $i < sizeof($info); $i++) {
			array_push($names, $info[$i]['name']);
		}
		return $names;
	}

	//
	// private file type inspection methods
	//

	private static function getFileTypesFromNames($names) {
		$fileTypes = array();
		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			$extension = pathinfo($name, PATHINFO_EXTENSION);
			if (array_key_exists($extension, $fileTypes)) {
				$fileTypes[$extension]++;
			} else {
				$fileTypes[$extension] = 1;
			}
		}
		return $fileTypes;
	}

	//
	// private zip archive methods
	//

	private static function getZipArchiveFilenames($zipArchive) {
		$names = array();
		for ($i = 0; $i < $zipArchive->numFiles; $i++) {
			$stat = $zipArchive->statIndex($i);
			$name = $stat['name'];
			array_push($names, $name);
		}
		return $names;
	}

	private function containsDirectoryNames($names) {
		foreach ($names as $value) {
			if (StringUtils::endsWith($value, '/')) {
				return true;
			}
		}
		return false;
	}

	private function inferDirectoryNames($names) {
		foreach ($names as $value) {

			// for each file name
			//
			if (!StringUtils::endsWith($value, '/')) {
				$terms = explode('/', $value);
				if (sizeof($terms) > 1) {

					// iterate over directory names
					//
					$path = '';
					foreach (array_slice($terms, 0, -1) as $directory) {

						// compose path
						//
						$path .= $directory.'/'; 

						// add new paths to list
						//
						if (!in_array($path, $names)) {
							array_push($names, $path);
						}
					}
				}
			}
		}

		// sort names so that new paths are not at the end
		//
		sort($names);

		return $names;
	}

	private function getZipFileInfoList($dirname, $filter, $recursive = false) {

		// open zip archive
		//
		$zipArchive = new ZipArchive();
		$zipArchive->open($this->path);

		// get file names from archive
		//
		$names = self::getZipArchiveFilenames($zipArchive);

		// infer directory names for zip files with no directory info
		//
		if (!self::containsDirectoryNames($names)) {
			$names = self::inferDirectoryNames($names);
		}

		// get root directory name
		//
		if ($dirname == '.') {
			$dirname = './';
		}

		// filter for directory names
		//
		if ($dirname) {
			$names = self::getNamesInDirectory($names, $dirname, $recursive);
		}

		// apply filter
		//
		if ($filter) {
			$names = self::getFilteredNames($names, $filter);
		}

		// return info
		//
		if ($dirname == './' && sizeof($names) == 1) {
			return self::nameToInfo($names[0]);
		} else {
			return self::namesToInfoArray($names);
		}
	}

	private function getZipDirectoryInfoList($dirname, $filter, $recursive = false) {

		// open zip archive
		//
		$zipArchive = new ZipArchive();
		$zipArchive->open($this->path);

		// get root directory name
		//
		if ($dirname == '.') {
			$dirname = './';
		}

		// get directory info array from zip archive
		//
		$directories = array();
		for ($i = 0; $i < $zipArchive->numFiles; $i++) {
			$stat = $zipArchive->statIndex($i);
			$name = $stat['name'];

			if ($filter == NULL || $filter == basename($name)) {
				if (self::isDirectoryName($name)) {
					if ($dirname == NULL) {

						// all files and directories
						//
						array_push($directories, $stat);
					} else if ($recursive) {
						if (StringUtils::startsWith(dirname($name).'/', $dirname)) {

							// found file in target path
							//
							array_push($directories, $stat);
						}
					} else {
						if (dirname($name).'/' == $dirname) {

							// found file in target directory
							//
							array_push($directories, $stat);
						}
					}
				}
			}
		}

		if ($dirname == '/' && $directories.length == 1) {
			return $directories[1];
		} else {
			return $directories;
		}
	}

	private function getZipFileTypes($dirname) {

		// open zip archive
		//
		$za = new ZipArchive();
		$za->open($this->path);

		// get root directory name
		//
		if ($dirname == '.') {
			$dirname = '';
		}

		// get file info array from zip archive
		//
		$fileTypes = array();
		for ($i = 0; $i < $za->numFiles; $i++) {
			$stat = $za->statIndex($i);
			$name = $stat['name'];

			if (!$this->isDirectoryName($name)) {
				$info = array(
					'name' => $name
				);

				if ($dirname == NULL) {

					// all files and directories
					//
					$extension = pathinfo($name, PATHINFO_EXTENSION);
					if (array_key_exists($extension, $fileTypes)) {
						$fileTypes[$extension]++;
					} else {
						$fileTypes[$extension] = 1;
					}
				} else if (StringUtils::startsWith($name, $dirname)) {

					// found file in target directory
					//
					$extension = pathinfo($name, PATHINFO_EXTENSION);
					if (array_key_exists($extension, $fileTypes)) {
						$fileTypes[$extension]++;
					} else {
						$fileTypes[$extension] = 1;
					}
				}
			}
		}
		return $fileTypes;
	}

	//
	// private tar archive methods
	//

	private function getTarFileInfoList($dirname, $filter, $recursive = false) {

		// get file names
		//
		if ($this->isZipped()) {
			$script = 'tar -ztf '.$this->path;
		} else {
			$script = 'tar -tf '.$this->path;
		}
		$names = array();
		exec($script, $names);
		$names = self::getFileAndDirectoryNames($names);

		// get root directory name
		//
		if ($dirname == '.') {
			$dirname = './';
		}

		// get names that are part of directory
		//
		if ($dirname) {
			$names = self::getNamesInDirectory($names, $dirname, $recursive);
		}

		// apply filter
		//
		if ($filter) {
			$names = self::getFilteredNames($names, $filter);
		}

		// return info
		//
		if ($dirname == './' && sizeof($names) == 1) {
			return self::nameToInfo($names[0]);
		} else {
			return self::namesToInfoArray($names);
		}
	}

	private function getTarDirectoryInfoList($dirname, $filter, $recursive = false) {

		// get file and directory names
		//
		if ($this->isZipped()) {
			$script = 'tar -ztf '.$this->path;
		} else {
			$script = 'tar -tf '.$this->path;
		}
		$names = array();
		exec($script, $names);

		// filter for directory names
		//
		$names = self::getDirectoryNames($names, $recursive);

		// apply filter
		//
		if ($filter) {
			$names = self::getFilteredNames($names, $filter);
		}

		// return info
		//
		if ($dirname == './' && sizeof($names) == 1) {
			return self::nameToInfo($names[0]);
		} else {
			return self::namesToInfoArray($names);
		}
	}

	private function getTarFileTypes($dirname) {

		// get file names
		//
		if ($this->isZipped()) {
			$script = 'tar -ztf '.$this->path;
		} else {
			$script = 'tar -tf '.$this->path;
		}
		$names = array();
		exec($script, $names);

		// filter for file names
		//
		$names = self::getFileNames($names);

		// get root directory name
		//
		if ($dirname == '.') {
			$dirname = '';
		}

		// get names that are part of directory
		//
		if ($dirname) {
			$names = self::getNamesNestedInDirectory($names, $dirname);
		}

		// return file types of names
		//
		return self::getFileTypesFromNames($names);
	}

	//
	// private jar archive methods
	//

	private function getJarFileInfoList($dirname, $filter, $recursive = false) {

		// get file names
		//
		$script = 'jar -tf '.$this->path;
		$names = array();
		exec($script, $names);
		$names = self::getFileAndDirectoryNames($names);

		// get root directory name
		//
		if ($dirname == '.') {
			$dirname = './';
		}

		// get names that are part of directory
		//
		if ($dirname) {
			$names = self::getNamesInDirectory($names, $dirname, $recursive);
		}

		// apply filter
		//
		if ($filter) {
			$names = self::getFilteredNames($names, $filter);
		}

		// return info
		//
		if ($dirname == './' && sizeof($names) == 1) {
			return self::nameToInfo($names[0]);
		} else {
			return self::namesToInfoArray($names);
		}
	}

	private function getJarDirectoryInfoList($dirname, $filter, $recursive = false) {

		// get file and directory names
		//
		$script = 'jar -tf '.$this->path;
		$names = array();
		exec($script, $names);

		// filter for directory names
		//
		$names = self::getDirectoryNames($names, $recursive);

		// apply filter
		//
		if ($filter) {
			$names = self::getFilteredNames($names, $filter);
		}

		// return info
		//
		if ($dirname == './' && sizeof($names) == 1) {
			return self::nameToInfo($names[0]);
		} else {
			return self::namesToInfoArray($names);
		}
	}

	private function getJarFileTypes($dirname) {

		// get file names
		//
		$script = 'jar -tf '.$this->path;
		$names = array();
		exec($script, $names);

		// filter for file names
		//
		$names = self::getFileNames($names);

		// get root directory name
		//
		if ($dirname == '.') {
			$dirname = '';
		}

		// get names that are part of directory
		//
		if ($dirname) {
			$names = self::getNamesNestedInDirectory($names, $dirname);
		}

		// return file types of names
		//
		return self::getFileTypesFromNames($names);
	}

	//
	// private directory list to tree conversion methods
	//

	private static function directoryInfoListToTree($list) {
		$tree = array();

		function &findLeaf(&$tree, $name) {
			for ($i = 0; $i < sizeof($tree); $i++) {
				if ($tree[$i]['name'] == $name) {
					return $tree[$i];
				} else if (array_key_exists('contents', $tree[$i])) {
					$leaf = &findLeaf($tree[$i]['contents'], $name);
					if ($leaf != null) {
						return $leaf;
					}
				}
			}
			$leaf = null;
			return $leaf;
		}

		for ($i = 0; $i < sizeof($list); $i++) {
			$item = $list[$i];

			// search for item in tree
			//
			$dirname = dirname($item['name']);
			$leaf = &findLeaf($tree, $dirname.'/');

			if ($leaf != null) {

				// create diretory contents
				//
				if (!array_key_exists('contents', $leaf)) {
					$leaf['contents'] = array();
				}

				// add item to leaf
				//
				array_push($leaf['contents'], $item);
			} else {

				// add item to root of tree
				//
				array_push($tree, $item);
			}
		}

		return $tree;
	}
}
