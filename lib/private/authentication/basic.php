<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Authentication;

use OCP\Authentication\IProvider;

/**
 * Auth provider for HTTP Basic Auth
 */
class Basic extends Base implements IProvider {
	/**
	 * @param array $server the $_SERVER environment
	 * @param array $post the $_POST data
	 * @param array $cookie the $_COOKIE data
	 * @return bool
	 */
	public function tryAuth(&$server, $post, $cookie) {
		if (!isset($server['PHP_AUTH_USER'])
			|| !isset($server['PHP_AUTH_PW'])
			|| (isset($cookie['oc_ignore_php_auth_user']) && $cookie['oc_ignore_php_auth_user'] === $server['PHP_AUTH_USER'])
		) {
			return false;
		}

		if ($this->userSession->login($server['PHP_AUTH_USER'], $server['PHP_AUTH_PW'])) {
			$this->userSession->unsetMagicInCookie();
			$server['HTTP_REQUESTTOKEN'] = \OC_Util::callRegister();
			return true;
		}
		return false;
	}
}
