<?php
/**
 * Copyright (c) 2014, Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

namespace OC\Core\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;

/**
 * Class AuthController
 *
 * @package OC\Core\Controller
 */
class AuthController extends Controller {

	/** @var ISession */
	private $session;
	/** @var IUserSession */
	private $userSession;
	/** @var IConfig */
	private $config;
	/** @var ISecureRandom */
	private $random;
	/** @var IURLGenerator */
	private $urlGenerator;

	/** Contains the oc_token */
	const rememberMeCookieName = 'oc_token';

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param ISession $session
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 * @param ISecureRandom $random
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct($appName,
								IRequest $request,
								ISession $session,
								IUserSession $userSession,
								IConfig $config,
								ISecureRandom $random,
								IURLGenerator $urlGenerator) {
		parent::__construct($appName, $request);
		$this->session = $session;
		$this->userSession = $userSession;
		$this->config = $config;
		$this->random = $random;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @PublicPage
	 * @GuestOnly
	 * @NoCSRFRequired
	 *
	 * @param string $redirectUrl
	 * @return TemplateResponse
	 */
	public function showLoginForm($redirectUrl) {
		$params = array(
			'authenticateRoute' => $this->urlGenerator->linkToRouteAbsolute('core.auth.tryLoginForm'),
			'redirectUrl' => $redirectUrl
		);
		return new TemplateResponse($this->appName, 'login', $params, 'guest');
	}

	/**
	 * @PublicPage
	 * @GuestOnly
	 * @UseSession
	 *
	 * @param string $user
	 * @param string $password
	 * @param string $redirectUrl
	 * @param string $rememberLogin
	 * @param string $timezone
	 * @param int $timezoneOffset
	 * @return RedirectResponse
	 */
	public function tryLoginForm($user, $password, $redirectUrl, $rememberLogin, $timezone, $timezoneOffset = 0) {
		if(empty($user) || empty($password)) {
			// TODO: return false
		}

		// Deny the redirect if the URL contains a @
		// This prevents unvalidated redirects like ?redirectUrl=:user@domain.com
		if (strpos($redirectUrl, '@') !== false) {
			$redirectUrl = '';
		}

		if(\OC_User::login($user, $password)) {
			$userId = $this->userSession->getUser()->getUID();

			// setting up the time zone
			if (!is_null($timezoneOffset)) {
				$this->session->set('timezone', $timezoneOffset);
				$this->config->setUserValue($userId, 'core', 'timezone', $timezone);
			}

			$this->cleanupLoginTokens($userId);

			if (!empty($rememberLogin)) {
				$token = $this->random->getMediumStrengthGenerator()->generate(32);
				$this->config->setUserValue($userId, 'login_token', $token, time());
				$this->userSession->setMagicInCookie($userId, $token);
			} else {
				$this->userSession->unsetMagicInCookie();
			}

			if(empty($redirectUrl)) {
				return new RedirectResponse(\OC_Util::getDefaultPageUrl());
			} else {
				return new RedirectResponse($this->urlGenerator->getAbsoluteURL($redirectUrl));
			}
		}

		// TODO: Handle non-successful logins
		return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('core.auth.showLoginForm'));
	}

	/**
	 * @return RedirectResponse
	 */
	public function logout() {
		$rememberMeCookieValue = $this->request->getCookie(self::rememberMeCookieName);
		if (!empty($rememberMeCookieValue)) {
			$this->config->deleteUserValue($this->userSession->getUser()->getUID(), 'login_token', $rememberMeCookieValue);
		}
		$this->userSession->logout();
		return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('index'));
	}

	/**
	 * Remove outdated and therefore invalid tokens for a user
	 * @param string $user
	 */
	private function cleanupLoginTokens($user) {
		$cutoff = time() - $this->config->getSystemValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15);
		$tokens = $this->config->getUserKeys($user, 'login_token');
		foreach ($tokens as $token) {
			$time = $this->config->getUserValue($user, 'login_token', $token);
			if ($time < $cutoff) {
				$this->config->deleteUserValue($user, 'login_token', $token);
			}
		}
	}
}
