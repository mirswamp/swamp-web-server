<?php
/******************************************************************************\
|                                                                              |
|                            ConfigureLogging.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This class extends the                                                |
|        Illuminate\Foundation\Bootstrap\ConfigureLogging class to override    |
|        the registerLogger() method so that the custom App\Log\Writer         |
|        class is used (instead of Illuminate\Log\Writer). Note that to use    |
|        this class, it must be added to the $bootstrappers array in           |
|        App\Http\Kernel .                                                     |
|                                                                              |
|        Author(s): Terry Fleury                                               |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Bootstrap;

use App\Log\Writer;
use Monolog\Logger as Monolog;
use Illuminate\Foundation\Bootstrap\ConfigureLogging as BaseConfigureLogging;

class ConfigureLogging extends BaseConfigureLogging {

	/**
	 * Register the logger instance in the container. Return a custom
	 * App\Log\Writer instance which logs additional SWAMP-specifc info
	 * to each Log message.
	 *
	 * @param  \Illuminate\Contracts\Foundation\Application  $app
	 * @return \App\Log\Writer
	 */
	protected function registerLogger(
		\Illuminate\Contracts\Foundation\Application $app) {
		$app->instance('log', $log = new Writer(
			new Monolog($app->environment()), $app['events'])
		);

		return $log;
	}

}

