<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Authentication;

use OC\User\User;

class Form extends \PHPUnit_Framework_TestCase {
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

	public function testNoAuthPost() {
		$provider = new \OC\Authentication\Form($this->getSession(), $this->getUserSession(), $this->getConfig(), 5000);
		$server = array();
		$this->assertFalse($provider->tryAuth($server, array(), array()));
	}

	public function testValidLogin() {
		$userSession = $this->getUserSession();
		$config = $this->getConfig();
		$config->expects($this->once())
			->method('getUserKeys')
			->will($this->returnValue(array()));
		$provider = new \OC\Authentication\Form($this->getSession(), $userSession, $config, 5000);
		$user = new User('foo', null);
		$userSession->expects($this->once())
			->method('login')
			->with('foo', 'bar')
			->will($this->returnValue(true));
		$userSession->expects($this->once())
			->method('getUser')
			->will($this->returnValue($user));

		$server = array();
		$this->assertTrue($provider->tryAuth($server, array('user' => 'foo', 'password' => 'bar'), array()));
	}

	public function testInValidLogin() {
		$userSession = $this->getUserSession();
		$provider = new \OC\Authentication\Form($this->getSession(), $userSession, $this->getConfig(), 5000);
		$userSession->expects($this->once())
			->method('login')
			->with('foo', 'baz')
			->will($this->returnValue(false));
		$userSession->expects($this->never())
			->method('getUser');

		$server = array();
		$this->assertFalse($provider->tryAuth($server, array('user' => 'foo', 'password' => 'baz'), array()));
	}

	public function testValidLoginRemembersTimeZone() {
		$userSession = $this->getUserSession();
		$session = $this->getSession();
		$config = $this->getConfig();
		$config->expects($this->once())
			->method('getUserKeys')
			->will($this->returnValue(array()));
		$provider = new \OC\Authentication\Form($session, $userSession, $config, 5000);
		$user = new User('foo', null);
		$userSession->expects($this->once())
			->method('login')
			->will($this->returnValue(true));
		$userSession->expects($this->once())
			->method('getUser')
			->will($this->returnValue($user));
		$session->expects($this->once())
			->method('set')
			->with('timezone', 1);

		$server = array();
		$this->assertTrue($provider->tryAuth($server, array('user' => 'foo', 'password' => 'bar', 'timezone-offset' => 1), array()));
	}

	public function testInValidLoginNoRemembersTimeZone() {
		$userSession = $this->getUserSession();
		$session = $this->getSession();
		$config = $this->getConfig();
		$provider = new \OC\Authentication\Form($session, $userSession, $config, 5000);
		$userSession->expects($this->once())
			->method('login')
			->will($this->returnValue(false));
		$session->expects($this->never())
			->method('set');

		$server = array();
		$this->assertFalse($provider->tryAuth($server, array('user' => 'foo', 'password' => 'bar', 'timezone-offset' => 1), array()));
	}

	public function testValidLoginSetRemember() {
		$userSession = $this->getUserSession();
		$session = $this->getSession();
		$config = $this->getConfig();
		$config->expects($this->once())
			->method('getUserKeys')
			->will($this->returnValue(array()));
		$config->expects($this->once())
			->method('setUserValue');
		$provider = new \OC\Authentication\Form($session, $userSession, $config, 5000);
		$user = new User('foo', null);
		$userSession->expects($this->once())
			->method('login')
			->will($this->returnValue(true));
		$userSession->expects($this->once())
			->method('getUser')
			->will($this->returnValue($user));

		$server = array();
		$this->assertTrue($provider->tryAuth($server, array('user' => 'foo', 'password' => 'bar', 'remember_login' => true), array()));
	}

	public function testInValidLoginNoSetRemember() {
		$userSession = $this->getUserSession();
		$session = $this->getSession();
		$config = $this->getConfig();
		$config->expects($this->never())
			->method('setUserValue');
		$provider = new \OC\Authentication\Form($session, $userSession, $config, 5000);
		$userSession->expects($this->once())
			->method('login')
			->will($this->returnValue(false));

		$server = array();
		$this->assertFalse($provider->tryAuth($server, array('user' => 'foo', 'password' => 'bar', 'remember_login' => true), array()));
	}
}
