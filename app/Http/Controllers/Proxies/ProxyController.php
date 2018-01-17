<?php
/******************************************************************************\
|                                                                              |
|                             ProxyController.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for viewer virtual machine proxies.         |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Proxies;

use PDO;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Viewers\ViewerInstance;
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Http\Controllers\BaseController;
use App\Services\HTCondorCollector;

class ProxyController extends BaseController {

	//
	// curl related methods
	//

	private static function getCurlHeader($key, $value) {
		$header = NULL;
		switch (strtolower($key)) {
			case 'origin':
				$header = escapeshellarg("Origin: $value"); 
				break;
			case 'accept-encoding':
				$header = escapeshellarg("Accept-Encoding: $value");
				break;
			case 'accept-language':
				$header = escapeshellarg("Accept-Language: $value");
				break;
			case 'content-type':
				$header = escapeshellarg("Content-Type: $value");
				break;
			case 'accept':
				$header = escapeshellarg("Accept: $value");
				break;
			case 'cache-control':
				$header = escapeshellarg("Cache-Control: $value");
				break;
			case 'x-requested-with':
				$header = escapeshellarg("X-Requested-With: $value");
				break;
			case 'connection':
				$header = escapeshellarg("Connection: $value");
				break;
			case 'referer':
				$header = escapeshellarg("Referer: $value");
				break;
		}
		return $header;
	}

	private static function getCurlHeaders($headers) {
		$curlHeaders = '';

		// convert headers to curl format
		//
		foreach ($headers as $key => $value) {
			$header = self::getCurlHeader($key, $value);
			if ($header) {
				$curlHeaders .= " -H ".$header;
			}	
		}

		return $curlHeaders;
	}

	private static function getCurlCommand($url, $uri, $user, $vm_ip) {

		// create curl command
		//
		$command = "curl -X $_SERVER[REQUEST_METHOD] '$url' "; 

		// add curl options
		//
		if (isset( $_COOKIE['JSESSIONID'])) {
			$command .= " -H 'Cookie: JSESSIONID=$_COOKIE[JSESSIONID]' "; 
		}
		$command .= " -H 'Host: $vm_ip' "; 

		// convert headers to curl format
		//
		$command .= self::getCurlHeaders(getallheaders());

		// add additional curl options
		//
		$command .= " -H 'AUTHORIZATION: SWAMP ".strtolower($user->username)."' ";
		$command .= " -H 'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36' ";
		$command .= " --data-binary @$uri ";
		$command .= " --compressed --insecure -i";

		return $command;
	}

	private static function getHeadersFromCurlResponse($headerContent){
		$headers = [];

		// split the string on every "double" new line.
		//
		foreach (explode("\r\n", $headerContent) as $i => $line) {
			if ($i === 0) {
				$headers['http_code'] = $line;
			} else {
				if (strpos($line, ': ') !== false) {
					list ($key, $value) = explode(': ', $line);
					$headers[$key][] = $value;
				}
			}
		}
		return $headers;
	}

	private static function modifyHeaders($headers, $status) {
		$modified = [];
		foreach ($headers as $key => $value) {
			switch (strtolower($key)) {

				// pass along these headers
				//
				case 'content-type':
				case 'set-cookie':
					$modified[$key] = $value;
					break;

				// handle 301 / 302 redirect locations
				//
				case 'location':
					if (in_array($status, ['301', '302'])) {

						// strip https://vmip from proxy location for ThreadFix
						// CodeDX Location does not contain the vmip
						//
						$pos = strpos($value[0], '/proxy-');
						$newloc = substr($value[0], $pos);
						$modified[$key] = [$newloc];
					}
					break;
			}
		}
		return $modified;
	}

	private static function setResponseHeaders($response, $headers) {
		foreach ($headers as $key => $value) {
			for ($i = 0; $i < sizeof($value); $i++) {
				$response->header($key, $value[$i]);
			}
		}
	}

	public function proxyCodeDxRequest() {
		$user = User::getIndex(session('user_uid'));

		// get viewer instance
		//
		$proxyUrl = Request::segment(1);
		list($vm_ip, $projectUid) = HTCondorCollector::getViewerData($proxyUrl);
		$iterations = 0;
		$maxIterations = 1000;
		while (!$vm_ip && $iterations < $maxIterations) {
			$iterations++;
			usleep(10000);
			list($vm_ip, $projectUid) = HTCondorCollector::getViewerData($proxyUrl);
		}

		// check if associated project is valid
		//	
		$project = Project::where('project_uid', '=', $projectUid)->first();
		if (!$project) {
			return response('No valid project is associated with these results.', 400);
		}

		// check whether current user is a member of this project
		//
		$currentUser = User::getIndex(session('user_uid'));
		if (!$project->isOwnedBy($user) && !$currentUser->isMemberOf($project)) {
			return response('The current user is not a member of this project', 400);
		}

		if ($vm_ip) {

			// get virtual machine info
			//
			$content = Request::instance()->getContent();
			$tfh = tmpfile();
			fwrite($tfh, $content);
			$uri = stream_get_meta_data($tfh)['uri'];
			$url = "https://$vm_ip".$_SERVER['REQUEST_URI'];
			$command = self::getCurlCommand($url, $uri, $user, $vm_ip);
			$response = `$command`;
			fclose($tfh);

			// decipher curl response
			//
			if ($response) {
				$values = preg_split("/\R\R/", $response, 2);
				if ($values && sizeof($values > 2)) {
					$header = isset( $values[0] ) ? $values[0] : '';
					$body   = isset( $values[1] ) ? $values[1] : '';
					preg_match('|HTTP/\d\.\d\s+(\d+)\s+.*|', $header, $match);

					if ($match && sizeof($match > 0)) {
						$status = $match[1];
						$headers = self::getHeadersFromCurlResponse($header);
						$headers = self::modifyHeaders($headers, $status);
						$response = response( $body ? $body : '', $status );
						self::setResponseHeaders($response, $headers, $status);
						return $response;
					} else {
						Log::warning("Proxy Controller Error - insuffient curl response in proxy controller.");
						return "Error - viewer is no longer available.  Please try again.";
					}
				} else {
					Log::warning("Proxy Controller Error - insuffient curl response in proxy controller.");
					return "Error - viewer is no longer available.  Please try again.";
				}
			} else {
				Log::warning("Proxy Controller Error - no response to command.");
				return "Error - viewer is no longer available.  Please try again.";
			}
		} else {
			Log::warning("Proxy Controller Error - no viewer instance is available.");
			return "Error - viewer is no longer available.  Please try again.";
		}
	}
}
