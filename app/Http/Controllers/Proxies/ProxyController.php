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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Proxies;

use PDO;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Models\Viewers\ViewerInstance;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

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
		$headers = array();

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

	private static function setResponseHeaders($response, $headers, $status) {

		// set content type
		//
		if (isset($headers) && array_key_exists('Content-Type', $headers)) {
			$response->header('Content-Type', $headers['Content-Type'][0]);
		}

		// handle 301 / 302 redirect locations
		//
		if (in_array($status, array('301', '302'))) {
			if (isset($headers) && array_key_exists('Location', $headers)) {

				// strip https://vmip from proxy location for ThreadFix
				// CodeDX Location does not contain the vmip
				//
				$pos = strpos($headers['Location'][0], '/proxy-');
				$newloc = substr($headers['Location'][0], $pos);
				$response->header('Location', $newloc);
			}
		}

		// set JSESSIONID when present
		//
		if (array_key_exists('Set-Cookie', $headers)) {
			foreach($headers['Set-Cookie'] as $setcookie) {
				$response->header('Set-Cookie', $setcookie);
			}
		}
	}

	public function proxyCodeDxRequest() {
		$user = User::getIndex(Session::get('user_uid'));

		// get viewer instance
		//
		$proxyUrl = Request::segment(1);
		$viewerInstance = ViewerInstance::where('proxy_url', '=', $proxyUrl)->first();
		
		// query failed, keep trying
		//
		$iterations = 0;
		$maxIterations = 1000;
		while (!$viewerInstance && $iterations < $maxIterations) {
			$iterations++;
			usleep(10000);
			$viewerInstance = ViewerInstance::where('proxy_url','=', $proxyUrl)->first();
		}

		if ($viewerInstance) {

			// get virtual machine info
			//
			$vm_ip = $viewerInstance->vm_ip_address;
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
						$response = response( $body ? $body : '', $status );
						self::setResponseHeaders($response, $headers, $status);
						return $response;
					} else {
						return "Error - insuffient curl response in proxy controller.";
					}
				} else {
					return "Error - insuffient curl response in proxy controller.";
				}
			} else {
				return "Error - no response to command: $command.";
			}
		} else {
			return "Error - no viewer instance is available.";
		}
	}
}