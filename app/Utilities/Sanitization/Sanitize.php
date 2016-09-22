<?php
/******************************************************************************\
|                                                                              |
|                                 Sanitize.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for wrapping the HTML Purifier library.        |
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

namespace App\Utilities\Sanitization;

require_once app_path().'/Lib/HTMLPurifier/sanitize.php';

class Sanitize extends \Sanitize {
}
