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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Files;

use ZipArchive;
use Illuminate\Support\Facades\Log;
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

	public function getRoot() {
		$list = $this->getFileInfoList();
		$names = self::infoListToNames($list);
		return self::getRootDirectoryName($names);
	}

	public function getExtension() {
		return pathinfo($this->path, PATHINFO_EXTENSION);
	}

	public function isZipped() {
		return $this->getExtension() == 'zip' || 
			$this->getExtension() == 'Z' || $this->getExtension() == 'gz';
	}

	public function getListing($path) {
		$info = $this->getFileInfoList($path);
		$names = self::infoArrayToNames($info);

		// strip leading ./ from names
		//
		for ($i = 0; $i < sizeof($names); $i++) {
			if (StringUtils::startsWith($names[$i], './')) {
				$names[$i] = substr($names[$i], 2);
			}
		}

		return $names;
	}

	public function contains($dirname, $filename) {
		self::normalizePaths($dirname, $filename);

		if (StringUtils::startsWith($dirname, './')) {
			$dirname = substr($names[$i], 2);
		}

		// look in top level of specified directory
		//
		if ($dirname && $dirname != '.') {
			$path = $dirname.$filename;
		} else {
			$path = $filename;
		}

		return in_array($path, $this->getListing($dirname));
	}

	public function search($dirname, $filenames) {
		self::normalizePaths($dirname, $filename);

		// get top level item names
		//
		if ($dirname && $dirname != '.') {
			$path = $dirname.$filename;
		} else {
			$path = $filename;
		}

		$info = $this->getFileInfoList($path, null, true);
		$names = self::infoArrayToNames($info);

		// strip leading ./ from names
		//
		for ($i = 0; $i < sizeof($names); $i++) {
			if (StringUtils::startsWith($names[$i], './')) {
				$names[$i] = substr($names[$i], 2);
			}
		}

		// sort names by nesting level, then alphabetically
		//
		usort($names, function($a, $b) {
			$aNesting = substr_count($a, '/');
			$bNesting = substr_count($b, '/');
			if ($aNesting != $bNesting) {
				return $aNesting > $bNesting;
			} else {
				return $a > $b;
			}
		});

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			for ($j = 0; $j < sizeof($filenames); $j++) {
				if (basename($name) == $filenames[$j]) {
					return $name;
				}
			}
		}
	}

	public function found($dirname, $filter, $recursive = true) {
		self::normalizePaths($dirname, $filter);
		return sizeof($this->getFileInfoList($dirname, $filter, $recursive)) != 0;
	}

	public function getFileInfoList($dirname = './', $filter = null, $recursive = false) {
		switch ($this->getExtension()) {

			// zip files
			//
			case 'zip':
			case 'whl':
				return $this->getZipFileInfoList($dirname, $filter, $recursive);

			// jar files
			//
			case 'jar':
			case 'war':
			case 'ear':
				return $this->getJarFileInfoList($dirname, $filter, $recursive);

			// tar files
			//
			default:
				return $this->getTarFileInfoList($dirname, $filter, $recursive);
		}
	}

	public function getDirectoryInfoList($dirname = './', $filter = null, $recursive = false) {
		switch ($this->getExtension()) {

			// zip files
			//
			case 'zip':
			case 'whl':
				return $this->getZipDirectoryInfoList($dirname, $filter, $recursive);

			// jar files
			//
			case 'jar':
			case 'war':
			case 'ear':
				return $this->getJarDirectoryInfoList($dirname, $filter, $recursive);

			// tar files
			//
			default:
				return $this->getTarDirectoryInfoList($dirname, $filter, $recursive);
		}
	}

	public function getFileInfoTree($dirname, $filter) {
		$list = $this->getFileInfoList($dirname, $filter);
		return self::directoryInfoListToTree($list);
	}

	public function getDirectoryInfoTree($dirname, $filter) {
		$list = $this->getDirectoryInfoList($dirname, $filter);
		return self::directoryInfoListToTree($list);
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

	public $path;

	//
	// private directory utility methods
	//

	private static function getRootDirectoryName($array) {

		if (sizeof($array) == 0) {
			return './';
		}

		// take the first item as initial prefix
		//
		$prefix = $array[0];  
		$length = strlen($prefix);

		// find the common prefix
		//
		foreach ($array as $name) {

			// strip leading ./
			//
			if (StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			// check if there is a match; if not, decrease the prefix length by one
			//
			while ($length > 0 && substr($name, 0, $length) !== $prefix) {
				$length--;
				$prefix = substr($prefix, 0, $length);
			}
		}

		// find directory name of common prefix
		//
		$last = strpos($prefix, '/');
		if ($last != false) {
			$prefix = substr($prefix, 0, $last + 1);
		} else {
			$prefix = null;
		}

		if (!$prefix) {
			$prefix = './';
		}

		return $prefix;
	}

	private static function isDirectoryName($path) {
		return $path[strlen($path) - 1] == '/';
	}

	private static function addName($name, &$dirnames) {
		if (!in_array($name, $dirnames)) {

			// add directory of name
			//
			$dirname = dirname($name);
			if ($dirname != '.' && $dirname != './') {
				self::addName($dirname.'/', $dirnames);
			}

			// add name
			//
			array_push($dirnames, $name);
		}
	}

	private static function getFileAndDirectoryNames($names) {
		$dirnames = [];

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];

			// strip leading ./
			//
			if (StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			if ($name && !in_array($name, $dirnames)) {

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
		$directoryNames = [];

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			if (self::isDirectoryName($name)) {

				// strip leading ./
				//
				if (StringUtils::startsWith($name, './')) {
					$name = substr($name, 2);
				}

				array_push($directoryNames, $name);
			}
		}

		return $directoryNames;
	}

	private static function getFileNames($names) {
		$fileNames = [];

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			if (!self::isDirectoryName($name)) {

				// strip leading ./
				//
				if (StringUtils::startsWith($name, './')) {
					$name = substr($name, 2);
				}

				array_push($fileNames, $name);
			}
		}

		return $fileNames;
	}

	//
	// private name / directory filtering methods
	//

	private static function getNamesInDirectory($names, $dirname, $recursive = false) {

		// make sure that dirname ends with a slash
		//
		if (!StringUtils::endsWith($dirname, '/')) {
			$dirname = $dirname.'/';
		}

		// return all if no dirname
		//
		if (!$dirname) {
			$dirnames = [];
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
		$dirnames = [];
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
			$dirnames = [];
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
		$dirnames = [];
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
		$filteredNames = [];
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
		return [
			'name' => $name
		];
	}

	private static function namesToInfoArray($names) {
		$info = [];
		foreach ($names as $name) {
			array_push($info, self::nameToInfo($name));
		}
		return $info;
	}

	private static function infoArrayToNames($info) {
		$names = [];
		foreach ($info as $item) {
			array_push($names, $item['name']);
		}
		return $names;
	}

	//
	// private file type inspection methods
	//

	private static function getFileTypesFromNames($names) {
		$fileTypes = [];
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

	private static function addPath($path, &$names) {
		if ($path && $path != '.' && $path != '/' && !in_array($path, $names)) {

			// add parent dirname
			//
			$dirname = dirname($path);
			if ($dirname && $dirname != '.' && $dirname != '/') {
				self::addPath($dirname.'/', $names);
			}

			// add path
			//
			array_push($names, $path);
		}
	}

	private static function getZipArchiveFilenames($zipArchive) {
		$names = [];
		for ($i = 0; $i < $zipArchive->numFiles; $i++) {
			$stat = $zipArchive->statIndex($i);
			$name = $stat['name'];

			// make sure that directory name for path exists
			//
			$dirname = dirname($name);
			if ($dirname && $dirname != '.' && $dirname != '/') {
				self::addPath($dirname.'/', $names);
			}

			// add name
			//
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

						Log::info("adding path: $path");

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

		// get root directory name
		//
		if (!$dirname || $dirname == './') {
			$root = self::getRootDirectoryName($names);
		} else {
			$root = $dirname;
		}

		// filter for directory names
		//
		if ($dirname) {
			$names = self::getNamesInDirectory($names, $root, $recursive);
		}

		// apply filter
		//
		if ($filter) {
			$names = self::getFilteredNames($names, $filter);
		}

		// return names converted to info
		//
		return self::namesToInfoArray($names);
	}

	private function getZipDirectoryInfoList($dirname, $filter, $recursive = false) {

		// open zip archive
		//
		$zipArchive = new ZipArchive();
		$zipArchive->open($this->path);

		// get root directory name
		//
		if ($dirname == '.' || $dirname == './') {
			$dirname = '';
		}

		// get directory info array from zip archive
		//
		$directories = [];
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

		return $directories;
	}

	private function getZipFileTypes($dirname) {

		// open zip archive
		//
		$za = new ZipArchive();
		$za->open($this->path);

		// get root directory name
		//
		if ($dirname == '.' || $dirname == './') {
			$dirname = '';
		}

		// get file info array from zip archive
		//
		$fileTypes = [];
		for ($i = 0; $i < $za->numFiles; $i++) {
			$stat = $za->statIndex($i);
			$name = $stat['name'];

			if (!$this->isDirectoryName($name)) {
				$info = [
					'name' => $name
				];

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
		$names = [];
		exec($script, $names);
		$names = self::getFileAndDirectoryNames($names);

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

		// return names converted to info
		//
		return self::namesToInfoArray($names);
	}

	private function getTarDirectoryInfoList($dirname, $filter, $recursive = false) {

		// get file and directory names
		//
		if ($this->isZipped()) {
			$script = 'tar -ztf '.$this->path;
		} else {
			$script = 'tar -tf '.$this->path;
		}
		$names = [];
		exec($script, $names);

		// filter for directory names
		//
		$names = self::getDirectoryNames($names, $recursive);

		// apply filter
		//
		if ($filter) {
			$names = self::getFilteredNames($names, $filter);
		}

		// return names converted to info
		//
		return self::namesToInfoArray($names);
	}

	private function getTarFileTypes($dirname) {

		// get file names
		//
		if ($this->isZipped()) {
			$script = 'tar -ztf '.$this->path;
		} else {
			$script = 'tar -tf '.$this->path;
		}
		$names = [];
		exec($script, $names);

		// filter for file names
		//
		$names = self::getFileNames($names);

		// get names that are part of directory
		//
		if ($dirname && $dirname != '.' && $dirname != './') {
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
		$names = [];
		exec($script, $names);
		$names = self::getFileAndDirectoryNames($names);

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

		// return names converted to info
		//
		return self::namesToInfoArray($names);
	}

	private function getJarDirectoryInfoList($dirname, $filter, $recursive = false) {

		// get file and directory names
		//
		$script = 'jar -tf '.$this->path;
		$names = [];
		exec($script, $names);

		// filter for directory names
		//
		$names = self::getDirectoryNames($names, $recursive);

		// apply filter
		//
		if ($filter) {
			$names = self::getFilteredNames($names, $filter);
		}

		// return names converted to info
		//
		return self::namesToInfoArray($names);
	}

	private function getJarFileTypes($dirname) {

		// get file names
		//
		$script = 'jar -tf '.$this->path;
		$names = [];
		exec($script, $names);

		// filter for file names
		//
		$names = self::getFileNames($names);

		// get names that are part of directory
		//
		if ($dirname && $dirname != '.' && $dirname != './') {
			$names = self::getNamesNestedInDirectory($names, $dirname);
		}

		// return file types of names
		//
		return self::getFileTypesFromNames($names);
	}

	//
	// private directory list to tree conversion methods
	//

	private static function infoListToNames($list) {
		$names = [];
		for ($i = 0; $i < sizeof($list); $i++) {
			$name = $list[$i]['name'];

			// strip leading ./
			//
			if (StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			array_push($names, $name);
		}

		return $names;
	}

	private static function directoryInfoListToTree($list) {
		$tree = [];

		function &findLeaf(&$tree, $name) {

			// strip leading ./
			//
			if (StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			for ($i = 0; $i < sizeof($tree); $i++) {
				$treeName = $tree[$i]['name'];

				// strip leading ./
				//
				if (StringUtils::startsWith($treeName, './')) {
					$treeName = substr($treeName, 2);
				}

				if ($treeName == $name) {
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
			$dirname = dirname($item['name']);

			// strip leading ./
			//
			if (StringUtils::startsWith($dirname, './')) {
				$dirname = substr($dirname, 2);
			}

			// search for item in tree
			//
			$leaf = &findLeaf($tree, $dirname != '/'? $dirname.'/' : $dirname);

			if ($leaf != null) {

				// create directory contents
				//
				if (!array_key_exists('contents', $leaf)) {
					$leaf['contents'] = [];
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

		if (!array_key_exists('contents', $tree)) {

			// find root name
			//
			$names = self::infoListToNames($tree);
			if (sizeof($names) > 0) {
				$name = self::getRootDirectoryName($names);
				if ($name == '') {
					$name = './';
				}
			} else {
				$name = './';
			}

			$tree = [
				'name' => $name,
				'contents' => $tree
			];
		}

		return $tree;
	}
}
