<?php
/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012, 2014 Bernhard Posselt <dev@bernhard-posselt.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Public interface of ownCloud for apps to use.
 * AppFramework\Controller class
 */

namespace OCP\AppFramework;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;


/**
 * Base class to inherit your controllers from
 */
abstract class Controller {

	/**
	 * app name
	 * @var string
	 */
	protected $appName;

	/**
	 * current request
	 * @var \OCP\IRequest
	 */
	protected $request;

	private $responders = [];

	/**
	 * constructor of the controller
	 * @deprecated
	 * @param string $appName the name of the app
	 * @param IRequest $request an instance of the request
	 */
	public function __construct($appName = '', IRequest $request = null){
		$this->appName = $appName;
		$this->request = $request;
	}


	/**
	 * Parses an HTTP accept header and returns the supported responder type
	 * @param string $acceptHeader
	 * @return string the responder type
	 */
	public function getResponderByHTTPHeader($acceptHeader) {
		$headers = explode(',', $acceptHeader);

		// return the first matching responder
		foreach ($headers as $header) {
			$header = strtolower(trim($header));

			$responder = str_replace('application/', '', $header);

			if (array_key_exists($responder, $this->responders)) {
				return $responder;
			}
		}

		// no matching header defaults to json
		return 'json';
	}


	/**
	 * Registers a formatter for a type
	 * @param string $format
	 * @param \Closure $responder
	 */
	protected function registerResponder($format, \Closure $responder) {
		$this->responders[$format] = $responder;
	}


	/**
	 * Serializes and formats a response
	 * @param mixed $response the value that was returned from a controller and
	 * is not a Response instance
	 * @param string $format the format for which a formatter has been registered
	 * @throws \DomainException if format does not match a registered formatter
	 * @return Response
	 */
	public function buildResponse($response, $format='json') {
		// register default responders
		if ($format === 'json' && !isset($this->responders['json'])) {
			$this->responders['json'] = function ($data) {
				if ($data instanceof DataResponse) {
					$response = new JSONResponse(
						$data->getData(),
						$data->getStatus()
					);
					$response->setHeaders(array_merge(
						$data->getHeaders(), $response->getHeaders()
					));
					return $response;
				} else {
					return new JSONResponse($data);
				}
			};
		}

		if(array_key_exists($format, $this->responders)) {

			$responder = $this->responders[$format];

			return $responder($response);

		} else {
			throw new \DomainException('No responder registered for format ' .
				$format . '!');
		}
	}


}
