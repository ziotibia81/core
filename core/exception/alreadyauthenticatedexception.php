<?php
/**
 * Copyright (c) 2014, Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

namespace OC\Core\Exception;

/**
 * Thrown when the authentication middleware encounters an already logged-in user
 *
 * @package OC\Core\Middleware
 */
class AlreadyAuthenticatedException extends \Exception {

	/**
	 * @param string $msg the security error message
	 */
	public function __construct($msg, $code = 0) {
		parent::__construct($msg, $code);
	}

}
