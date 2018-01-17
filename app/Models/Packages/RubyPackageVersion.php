<?php
/******************************************************************************\
|                                                                              |
|                            RubyPackageVersion.php                            |
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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;

class RubyPackageVersion extends PackageVersion {

	//
	// querying methods
	//

	function getGemInfo($dirname) {
		$contents = self::getFileContents('Gemfile', $dirname);

		if ($contents) {

			// parse file
			//
			return self::parseGemInfo(explode("\n", $contents));
		} else {
			return response("Could not get Gemfile contents.", 404);
		}
	}

	function getGemType() {
		$contents = file_get_contents($this->getPackagePath());

		// check for framework dependencies
		//
		if (strpos($contents, "gem 'sinatra'") !== false) {
			return 'sinatra';
		} else if (strpos($contents, "gem 'rails'") !== false) {
			return 'rails';
		} else if (strpos($contents, "gem 'padrino'") !== false) {
			return 'padrino';

		// no framework dependencies found
		//
		} else {
			return 'ruby';
		}
	}

	function getBuildSystem() {

		// check file extension of archive file
		//
		$path_parts = pathinfo($this->getPackagePath());
		if ($path_parts['extension'] == 'gem') {
			return 'ruby-gem';
		} else {

			// check in build path
			//
			$archive = new Archive($this->getPackagePath());
			$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);

			// check for build files
			//
			$foundGemfile = $archive->found($buildPath, 'Gemfile');
			$foundRakefile = $archive->found($buildPath, 'Rakefile');

			if ($foundGemfile && $foundRakefile) {
				return 'bundler+rake'; 
			} else if ($foundGemfile) {
				return 'bundler+other';
			} else if ($foundRakefile) {
				return 'rake';
			} else {
				return none;
			}
		}
	}

	function checkBuildSystem() {
		switch ($this->build_system) {

			case 'bundler+rake':

				// create archive from package
				//
				$archive = new Archive($this->getPackagePath());
				$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);
				$buildFile = $this->build_file;

				// check for Gemfile
				//
				if (!$archive->contains($buildPath, 'Gemfile')) {
					return response("Could not find a Gemfile within the '".$buildPath."' directory.  You may need to set your build path or the path to your build file.", 404);
				}

				// search archive for build file in build path
				//
				if ($buildFile != NULL) {

					// check for specified build file
					//
					if ($archive->contains($buildPath, $buildFile)) {
						return response("Ruby package build system ok for bundler+rake.", 200);
					} else {
						return response("Could not find a build file called '".$buildFile."' within the '".$buildPath."' directory.  You may need to set your build path or the path to your build file.", 404);
					}
				} else {

					// search archive for default build file in build path
					//
					if ($archive->contains($buildPath, 'rakefile') || 
						$archive->contains($buildPath, 'Rakefile')) {
						return response("Ruby package build system ok for bundler+rake.", 200);
					} else {
						return response("Could not find a build file called 'rakefile' or 'Rakefile' within '".$this->source_path."' directory. You may need to set your build path or the path to your build file.", 404);
					}
				}
				break;

			case 'bundler':
			case 'bundler+other':

				// create archive from package
				//
				$archive = new Archive($this->getPackagePath());
				$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);
				$buildFile = $this->build_file;

				// check for Gemfile
				//
				if ($archive->contains($buildPath, 'Gemfile')) {
					return response("Ruby package build system ok for bundler+other.", 200);
				} else {
					return response("Could not find a Gemfile within the '".$buildPath."' directory.  You may need to set your build path or the path to your build file.", 404);
				}
				break;

			case 'rake':

				// create archive from package
				//
				$archive = new Archive($this->getPackagePath());
				$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);
				$buildFile = $this->build_file;

				// search archive for build file in build path
				//
				if ($buildFile != NULL) {

					// check for specified build file
					//
					if ($archive->contains($buildPath, $buildFile)) {
						return response("Ruby package build system ok for rake.", 200);
					} else {
						return response("Could not find a build file called '".$buildFile."' within the '".$buildPath."' directory.  You may need to set your build path or the path to your build file.", 404);
					}
				} else {

					// search archive for default build file in build path
					//
					if ($archive->contains($buildPath, 'rakefile') || 
						$archive->contains($buildPath, 'Rakefile')) {
						return response("Ruby package build system ok for rake.", 200);
					} else {
						return response("Could not find a build file called 'rakefile' or 'Rakefile' within '".$this->source_path."' directory. You may need to set your build path or the path to your build file.", 404);
					}		
				}
				break;

			case 'ruby-gem':
				$path_parts = pathinfo($this->getPackagePath());
				if ($path_parts['extension'] == 'gem') {
					return response("Ruby package build system ok for ruby-gem.", 200);
				} else {
					return response("The package archive file extension should be '.gem' for the ruby-gem build system.", 404);
				}
				break;

			case 'other':
			case 'no-build':
				return response("Ruby package build system ok for ".$this->build_system.".", 200);
		}
	}

	//
	// utility methods
	//

	private static function parseGemItem($string) {
		$string = trim($string);

		// single quotated string literals
		//
		if ($string[0] == "'") {
			return (object)[
				'strlit' => trim($string, "'")
			];

		// double quotated string literals
		//
		} else if ($string[0] == '"') {
			return (object)[
				'strlit2' => trim($string, '"')
			];

		// symbols
		//
		} else if ($string[0] == ':') {
			$string = ltrim($string, ':');
			$string = rtrim($string);

			// symbol definition
			//
			if (strpos($string, '=>') != false) {
				$items = explode('=>', $string);
				return [
					'symbol' => trim($items[0]),
					'value' => self::parseGemItem(trim($items[1]))
				];		

			// symbol instance
			//
			} else {
				return [
					'symbol' => trim($string)
				];
			}

		// requires
		//
		} else if (substr($string, 0, 8) == 'require:') {
			$items = explode(':', $string);
			return [
				'require' => trim($items[1], " ,\'\"\t\n\r\0\x0B")
			];		
		} else {

			// variables
			//
			return $string;
		}
	}

	private static function parseGemItems($string) {
		$words = explode(', ', $string);
		$items = [];
		for ($count = 0; $count < sizeof($words); $count++) {
			array_push($items, self::parseGemItem(trim($words[$count], " ,\t\n\r\0\x0B")));
		}
		return $items;
	}

	private static function parseGemInfo($lines) {
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
					$words = explode(' ', $line);

					// check for source
					//
					if ($words[0] == 'source') {
						if ($words[1]) {
							array_push($array, [
								'source' => self::parseGemItem(ltrim($line, 'source'))
							]);
						}

					// check for ruby version
					//
					} else if ($words[0] == 'ruby') {
						if ($words[1]) {
							array_push($array, [
								'ruby' => self::parseGemItem(ltrim($line, 'ruby'))
							]);
						}

					// check for gems
					//
					} else if ($words[0] == 'gem') {

						// parse list of gem values
						//
						array_push($array, [
							'gem' => self::parseGemItems(ltrim($line, 'gem'))
						]);

					// check for groups
					//
					} else if ($words[0] == 'group') {

						// parse groups
						//
						if ($words[1]) {

							// parse group names
							//
							$line = trim($line);
							$line = ltrim($line, "group");
							$line = rtrim($line, "do");
							$groups = self::parseGemItems($line);
							$gems = [];

							// go to next line
							//
							$line = trim($lines[$currentLine++]);

							while ($line != 'end') {
								$words = explode(' ', $line);

								// check for gems
								//
								if ($words[0] == 'gem') {

									// parse list of gem values
									//
									array_push($gems, [
										'gem' => self::parseGemItems(trim($line, 'gem'))
									]);
								}

								// go to next line
								//
								$line = trim($lines[$currentLine++]);
							}

							// add new group
							//
							array_push($array, [
								'group' => $groups,
								'gems' => $gems
							]);

							// go to next line
							//
							if ($currentLine < $numLines) {
								$line = trim($lines[$currentLine++]);
							}
						}
					} else {

						// other code
						//
						array_push($array, $line);
					}
				} 
			}
		}

		return $array;
	}
}
