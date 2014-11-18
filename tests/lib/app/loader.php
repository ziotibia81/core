<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\App;

use OC\App\Directory;
use OC\User\User;

class Loader extends \PHPUnit_Framework_TestCase {
	private static $loadedApps = array();

	public static function markAppLoaded($appId) {
		self::$loadedApps[] = $appId;
	}

	public function setUp() {
		self::$loadedApps = array();
	}

	private function getAppManager($installedApps = array(), $appForUser = array(), $directoryManager = null) {
		$userSession = $this->getMock('\OCP\IUserSession');
		$appConfig = $this->getMock('\OCP\IAppConfig');
		$groupManager = $this->getMock('\OCP\IGroupManager');

		$mock = $this->getMockBuilder('\OC\App\AppManager')
			->setConstructorArgs(array($userSession, $appConfig, $groupManager, $directoryManager))
			->setMethods(array('listInstalledApps', 'listAppsEnabledForUser', 'getInstalledVersion'))
			->getMock();
		$mock->expects($this->any())
			->method('listInstalledApps')
			->will($this->returnValue($installedApps));
		$mock->expects($this->any())
			->method('listAppsEnabledForUser')
			->will($this->returnValue($appForUser));
		$mock->expects($this->any())
			->method('getInstalledVersion')
			->will($this->returnValue('1.2.3'));

		return $mock;
	}

	private function getDirectoryManager($dir) {
		$mock = $this->getMockBuilder('\OC\App\DirectoryManager')
			->disableOriginalConstructor()
			->setMethods(array('getAppDirectories'))
			->getMock();
		$mock->expects($this->any())
			->method('getAppDirectories')
			->will($this->returnValue(array($dir)));
		return $mock;
	}

	private function mockApp(Directory $dir, $appId, $types = array(), $version = '1.2.3') {
		$path = $dir->getPath();
		mkdir($path . '/' . $appId);
		mkdir($path . '/' . $appId . '/appinfo');
		file_put_contents($path . '/' . $appId . '/appinfo/version', $version);
		file_put_contents($path . '/' . $appId . '/appinfo/app.php', '<?php \Test\App\Loader::markAppLoaded("' . $appId . '");');
		$types = array_map(function ($type) {
			return '<' . $type . '/>';
		}, $types);
		$typeString = implode('', $types);
		file_put_contents($path . '/' . $appId . '/appinfo/info.xml', '<?xml version="1.0"?><info><types>' . $typeString . '</types></info>');
	}

	private function assertAppLoaded($appId) {
		if (array_search($appId, self::$loadedApps) === false) {
			$this->fail('Failed asserting that app ' . $appId . ' was loaded');
		}
	}

	private function assertAppNotLoaded($appId) {
		if (array_search($appId, self::$loadedApps) !== false) {
			$this->fail('Failed asserting that app ' . $appId . ' was not loaded');
		}
	}

	public function testLoadApp() {
		$dir = new Directory(\OC::$server->getTempManager()->getTemporaryFolder(), '', false);
		$this->mockApp($dir, 'test');
		$dirManager = $this->getDirectoryManager($dir);
		$appManager = $this->getAppManager(array(), array(), $dirManager);
		$loader = new \OC\App\Loader($appManager, $dirManager);
		$loader->loadApp('test');
		$this->assertAppLoaded('test');
	}

	/**
	 * @expectedException \OC\NeedsUpdateException
	 */
	public function testLoadAppNeedsUpgrade() {
		$dir = new Directory(\OC::$server->getTempManager()->getTemporaryFolder(), '', false);
		$this->mockApp($dir, 'test', array(), '1.2.4');
		$dirManager = $this->getDirectoryManager($dir);
		$appManager = $this->getAppManager(array(), array(), $dirManager);
		$loader = new \OC\App\Loader($appManager, $dirManager);
		$loader->loadApp('test');
	}

	public function testLoadInstalledApps() {
		$dir = new Directory(\OC::$server->getTempManager()->getTemporaryFolder(), '', false);
		$this->mockApp($dir, 'test');
		$this->mockApp($dir, 'foo');
		$this->mockApp($dir, 'bar');
		$dirManager = $this->getDirectoryManager($dir);
		$appManager = $this->getAppManager(array('test', 'foo'), array(), $dirManager);
		$loader = new \OC\App\Loader($appManager, $dirManager);
		$loader->loadInstalledApps();
		$this->assertAppLoaded('test');
		$this->assertAppLoaded('foo');
		$this->assertAppNotLoaded('bar');
	}

	public function testLoadEnabledApps() {
		$dir = new Directory(\OC::$server->getTempManager()->getTemporaryFolder(), '', false);
		$this->mockApp($dir, 'test');
		$this->mockApp($dir, 'foo');
		$this->mockApp($dir, 'bar');
		$dirManager = $this->getDirectoryManager($dir);
		$appManager = $this->getAppManager(array('test', 'foo'), array('test'), $dirManager);
		$loader = new \OC\App\Loader($appManager, $dirManager);
		$user = new User('foo', null);
		$loader->loadAppsEnabledForUser($user);
		$this->assertAppLoaded('test');
		$this->assertAppNotLoaded('foo');
		$this->assertAppNotLoaded('bar');
	}

	public function testLoadAppsWithType() {
		$dir = new Directory(\OC::$server->getTempManager()->getTemporaryFolder(), '', false);
		$this->mockApp($dir, 'test');
		$this->mockApp($dir, 'foo', array('foo', 'bar'));
		$this->mockApp($dir, 'bar', array('foo'));
		$this->mockApp($dir, 'asd', array('foo'));
		$dirManager = $this->getDirectoryManager($dir);
		$appManager = $this->getAppManager(array('test', 'foo', 'bar'), array('test'), $dirManager);
		$loader = new \OC\App\Loader($appManager, $dirManager);
		$loader->loadAppsWithType('foo');
		$this->assertAppNotLoaded('test');
		$this->assertAppLoaded('foo');
		$this->assertAppLoaded('bar');
	}
}
