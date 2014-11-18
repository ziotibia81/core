<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\App;

use OC\App\Directory;
use OC\Group\Group;
use OC\User\User;

class Manager extends \PHPUnit_Framework_TestCase {
	/**
	 * @return \OCP\IAppConfig | \PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getAppConfig() {
		$appConfig = array();
		$config = $this->getMockBuilder('\OCP\IAppConfig')
			->disableOriginalConstructor()
			->getMock();

		$config->expects($this->any())
			->method('getValue')
			->will($this->returnCallback(function ($app, $key, $default) use (&$appConfig) {
				return (isset($appConfig[$app]) and isset($appConfig[$app][$key])) ? $appConfig[$app][$key] : $default;
			}));
		$config->expects($this->any())
			->method('setValue')
			->will($this->returnCallback(function ($app, $key, $value) use (&$appConfig) {
				if (!isset($appConfig[$app])) {
					$appConfig[$app] = array();
				}
				$appConfig[$app][$key] = $value;
			}));
		$config->expects($this->any())
			->method('getValues')
			->will($this->returnCallback(function ($app, $key) use (&$appConfig) {
				if ($app) {
					return $appConfig[$app];
				} else {
					$values = array();
					foreach ($appConfig as $app => $appData) {
						if (isset($appData[$key])) {
							$values[$app] = $appData[$key];
						}
					}
					return $values;
				}
			}));

		return $config;
	}

	private function getDirectoryManager($dirs) {
		$directoryManager = $this->getMockBuilder('\OC\App\DirectoryManager')
			->disableOriginalConstructor()
			->setMethods(array('getAppDirectories'))
			->getMock();
		$directoryManager->expects($this->any())
			->method('getAppDirectories')
			->will($this->returnValue($dirs));
		return $directoryManager;
	}

	public function testEnableApp() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$manager->enableApp('test');
		$this->assertEquals('yes', $appConfig->getValue('test', 'enabled', 'no'));
	}

	public function testDisableApp() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$manager->disableApp('test');
		$this->assertEquals('no', $appConfig->getValue('test', 'enabled', 'no'));
	}

	public function testEnableAppForGroups() {
		$groups = array(
			new Group('group1', array(), null),
			new Group('group2', array(), null)
		);
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$userSession = $this->getMock('\OCP\IUserSession');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$manager->enableAppForGroups('test', $groups);
		$this->assertEquals('["group1","group2"]', $appConfig->getValue('test', 'enabled', 'no'));
	}

	public function testIsInstalledEnabled() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$appConfig->setValue('test', 'enabled', 'yes');
		$this->assertTrue($manager->isInstalled('test'));
	}

	public function testIsInstalledDisabled() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$appConfig->setValue('test', 'enabled', 'no');
		$this->assertFalse($manager->isInstalled('test'));
	}

	public function testIsInstalledEnabledForGroups() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertTrue($manager->isInstalled('test'));
	}

	public function testIsEnabledForUserEnabled() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$appConfig->setValue('test', 'enabled', 'yes');
		$user = new User('user1', null);
		$this->assertTrue($manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserDisabled() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$appConfig->setValue('test', 'enabled', 'no');
		$user = new User('user1', null);
		$this->assertFalse($manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserEnabledForGroup() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$user = new User('user1', null);

		$groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->will($this->returnValue(array('foo', 'bar')));

		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertTrue($manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserDisabledForGroup() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$user = new User('user1', null);

		$groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->will($this->returnValue(array('bar')));

		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertFalse($manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserLoggedOut() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());

		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertFalse($manager->IsEnabledForUser('test'));
	}

	public function testIsEnabledForUserLoggedIn() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$user = new User('user1', null);

		$userSession->expects($this->once())
			->method('getUser')
			->will($this->returnValue($user));
		$groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->will($this->returnValue(array('foo', 'bar')));

		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertTrue($manager->isEnabledForUser('test'));
	}

	public function testGetInstalledVersion() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();

		$appConfig->setValue('test', 'installed_version', '1.2.3');

		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$this->assertEquals('1.2.3', $manager->getInstalledVersion('test'));
	}

	public function testGetInstalledVersionNotInstalled() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();

		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$this->assertEquals('0.0.0', $manager->getInstalledVersion('test'));
	}

	public function testGetVersion() {
		$path = \OC::$server->getTempManager()->getTemporaryFolder();
		mkdir($path . '/test');
		mkdir($path . '/test/appinfo');
		file_put_contents($path . '/test/appinfo/app.php', '');
		file_put_contents($path . '/test/appinfo/version', '1.2.3');
		$dir = new Directory($path, '', false);

		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array($dir));
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$this->assertEquals('1.2.3', $manager->getAppVersion('test'));
	}

	public function testGetVersionNoDirs() {
		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$this->assertEquals('0.0.0', $manager->getAppVersion('test'));
	}

	public function testGetVersionNotInstalled() {
		$path = \OC::$server->getTempManager()->getTemporaryFolder();
		$dir = new Directory($path, '', false);

		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array($dir));
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$this->assertEquals('0.0.0', $manager->getAppVersion('test'));
	}

	public function testGetAppInfo() {
		$path = \OC::$server->getTempManager()->getTemporaryFolder();
		mkdir($path . '/test');
		mkdir($path . '/test/appinfo');
		file_put_contents($path . '/test/appinfo/app.php', '');
		file_put_contents($path . '/test/appinfo/info.xml', '<?xml version="1.0"?><info><id>files</id><name>Files</name></info>');
		file_put_contents($path . '/test/appinfo/version', '1.2.3');
		$dir = new Directory($path, '', false);

		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array($dir));
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$info = $manager->getAppInfo('test');
		$this->assertEquals('0.0.0', $info->getInstalledVersion());
		$this->assertEquals('1.2.3', $info->getVersion());
		$this->assertEquals('Files', $info->getName());
	}

	public function testGetAppInfoNotInstalled() {
		$path = \OC::$server->getTempManager()->getTemporaryFolder();
		$dir = new Directory($path, '', false);

		$userSession = $this->getMock('\OCP\IUserSession');
		$groupManager = $this->getMock('\OCP\IGroupManager');
		$directoryManager = $this->getDirectoryManager(array($dir));
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\AppManager($userSession, $appConfig, $groupManager, $directoryManager);
		$this->assertNull($manager->getAppInfo('test'));
	}
}
