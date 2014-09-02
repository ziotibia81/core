<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Authentication;

use OCP\Authentication\Exception;
use OCP\Authentication\IProvider;

class Cookie extends \PHPUnit_Framework_TestCase {
	/**
	 * @return \OCP\IUserSession | \PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getUserSession() {
		return $this->getMockBuilder('\OC\User\Session')
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return \OCP\ISession | \PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getSession() {
		return $this->getMockBuilder('\OCP\ISession')
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return \OCP\IConfig | \PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getConfig() {
		return $this->getMockBuilder('\OCP\IConfig')
			->disableOriginalConstructor()
			->getMock();
	}

	public function testNoCookie() {
		$provider = new \OC\Authentication\Cookie($this->getSession(), $this->getUserSession(), $this->getConfig(), 5000);
		$server = array();
		$this->assertEquals(IProvider::NOT_APPLICABLE, $provider->tryAuth($server, array(), array()));
	}
}
