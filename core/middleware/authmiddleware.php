<?php
/**
 * Copyright (c) 2014, Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

namespace OC\Core\Middleware;

use OC\AppFramework\Http;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OC\Core\Exception\AlreadyAuthenticatedException;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;

/**
 * Class AuthMiddleware
 *
 * @package OC\Core\Middleware
 */
class AuthMiddleware extends Middleware {

	/** @var ControllerMethodReflector */
	private $reflector;
	/** @var string */
	private $appName;
	/** @var bool */
	private $isLoggedIn;

	/**
	 * @param ControllerMethodReflector $reflector
	 * @param string $appName
	 * @param bool $isLoggedIn
	 */
	public function __construct(ControllerMethodReflector $reflector,
								$appName,
								$isLoggedIn) {
		$this->reflector = $reflector;
		$this->appName = $appName;
		$this->isLoggedIn = $isLoggedIn;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws AlreadyAuthenticatedException
	 */
	public function beforeController($controller, $methodName) {
		$isGuestOnly = $this->reflector->hasAnnotation('GuestOnly');
		if($isGuestOnly) {
			if($this->isLoggedIn) {
				throw new AlreadyAuthenticatedException('Page is only accessible for guests.', Http::STATUS_UNAUTHORIZED);
			}
		}
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @return RedirectResponse
	 * @throws \Exception
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if($exception instanceof AlreadyAuthenticatedException) {
			return new RedirectResponse('core.index');
		} else {
			throw $exception;
		}
	}

}
