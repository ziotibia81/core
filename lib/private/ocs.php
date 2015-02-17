<?php

/**
 * ownCloud
 *
 * @author Frank Karlitschek
 * @author Michael Gapczynski
 * @copyright 2012 Frank Karlitschek frank@owncloud.org
 * @copyright 2012 Michael Gapczynski mtgap@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

/**
 * Class to handle open collaboration services API requests
 */
class OC_OCS {
	/**
	 * Called when a not existing OCS endpoint has been called
	 */
	public static function notFound() {
		$format = \OC::$server->getRequest()->getParam('format', 'xml');
		$txt = 'Invalid query, please check the syntax. API specifications are here:'
			.' http://www.freedesktop.org/wiki/Specifications/open-collaboration-services.';
		echo self::generateXml($format, 'failed', 999, $txt);
	}

	/**
	 * generates the xml or json response for the API call from an multidimensional data array.
	 * @param string $format
	 * @param string $status
	 * @param string $statuscode
	 * @param string $message
	 * @param array $data
	 * @return string xml/json
	 */
	public static function generateXml($format, $status, $statuscode, $message, array $data = null) {
		if($format === 'json') {
			$json = [];
			$json['status'] = $status;
			$json['statuscode'] = $statuscode;
			$json['message'] = $message;
			if(!is_null($data)) {
				$json['data'] = $data;
			}
			return json_encode($json);
		} else {
			$txt = '';
			$writer = xmlwriter_open_memory();
			xmlwriter_set_indent($writer, 2);
			xmlwriter_start_document($writer );
			xmlwriter_start_element($writer, 'ocs');
			xmlwriter_start_element($writer, 'meta');
			xmlwriter_write_element($writer, 'status', $status);
			xmlwriter_write_element($writer, 'statuscode', $statuscode);
			xmlwriter_write_element($writer, 'message', $message);
			xmlwriter_end_element($writer);
			if(!is_null($data)) {
				xmlwriter_start_element($writer, 'data');
				foreach($data as $key => $element) {
					xmlwriter_write_element($writer, $key, $element);
				}
				xmlwriter_end_element($writer);
			}
			xmlwriter_end_element($writer);
			xmlwriter_end_document($writer);
			$txt.=xmlwriter_output_memory($writer);
			unset($writer);
			return $txt;
		}
	}
}
