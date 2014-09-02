<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Authentication;

class Basic extends \PHPUnit_Framework_TestCase {
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

	public function testNoAuthHeaders() {
		$provider = new \OC\Authentication\Basic($this->getSession(), $this->getUserSession(), $this->getConfig(), 5000);
		$server = array();
		$this->assertFalse($provider->tryAuth($server, array(), array()));
	}

	public function testValidLogin() {
		$userSession = $this->getUserSession();
		$provider = new \OC\Authentication\Basic($this->getSession(), $userSession, $this->getConfig(), 5000);
		$userSession->expects($this->once())
			->method('login')
			->with('foo', 'bar')
			->will($this->returnValue(true));
		$server = array('PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'bar');
		$this->assertTrue($provider->tryAuth($server, array(), array()));
	}

	public function testInValidLogin() {
		$userSession = $this->getUserSession();
		$provider = new \OC\Authentication\Basic($this->getSession(), $userSession, $this->getConfig(), 5000);
		$userSession->expects($this->once())
			->method('login')
			->with('foo', 'baz')
			->will($this->returnValue(false));
		$server = array('PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'baz');
		$this->assertFalse($provider->tryAuth($server, array(), array()));
	}

	public function testValidLoginSetsRequestToken() {
		$userSession = $this->getUserSession();
		$provider = new \OC\Authentication\Basic($this->getSession(), $userSession, $this->getConfig(), 5000);
		$userSession->expects($this->once())
			->method('login')
			->will($this->returnValue(true));
		$server = array('PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'bar');
		$this->assertTrue($provider->tryAuth($server, array(), array()));
		$this->assertArrayHasKey('HTTP_REQUESTTOKEN', $server);
	}

	public function testInvalidLoginNoRequestToken() {
		$userSession = $this->getUserSession();
		$provider = new \OC\Authentication\Basic($this->getSession(), $userSession, $this->getConfig(), 5000);
		$userSession->expects($this->once())
			->method('login')
			->will($this->returnValue(false));
		$server = array('PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'bar');
		$this->assertFalse($provider->tryAuth($server, array(), array()));
		$this->assertArrayNotHasKey('HTTP_REQUESTTOKEN', $server);
	}

	public function testValidLoginUnsetAuthCookie() {
		$userSession = $this->getUserSession();
		$userSession->expects($this->once())
			->method('unsetMagicInCookie');
		$provider = new \OC\Authentication\Basic($this->getSession(), $userSession, $this->getConfig(), 5000);
		$userSession->expects($this->once())
			->method('login')
			->will($this->returnValue(true));
		$server = array('PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'bar');
		$this->assertTrue($provider->tryAuth($server, array(), array()));
	}

	public function testInvalidLoginNoUnsetAuthCookie() {
		$userSession = $this->getUserSession();
		$userSession->expects($this->never())
			->method('unsetMagicInCookie');
		$provider = new \OC\Authentication\Basic($this->getSession(), $userSession, $this->getConfig(), 5000);
		$userSession->expects($this->once())
			->method('login')
			->will($this->returnValue(false));
		$server = array('PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'bar');
		$this->assertFalse($provider->tryAuth($server, array(), array()));
	}
}
