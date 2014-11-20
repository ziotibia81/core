<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\App;

use OC\App\Directory;

class InfoManager extends \PHPUnit_Framework_TestCase {
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

	public function testGetInstalledVersion() {
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();

		$appConfig->setValue('test', 'installed_version', '1.2.3');

		$manager = new \OC\App\InfoManager($directoryManager, $appConfig);
		$this->assertEquals('1.2.3', $manager->getInstalledVersion('test'));
	}

	public function testGetInstalledVersionNotInstalled() {
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();

		$manager = new \OC\App\InfoManager($directoryManager, $appConfig);
		$this->assertEquals('0.0.0', $manager->getInstalledVersion('test'));
	}

	public function testGetVersion() {
		$path = \OC::$server->getTempManager()->getTemporaryFolder();
		mkdir($path . '/test');
		mkdir($path . '/test/appinfo');
		file_put_contents($path . '/test/appinfo/app.php', '');
		file_put_contents($path . '/test/appinfo/version', '1.2.3');
		$dir = new Directory($path, '', false);

		$directoryManager = $this->getDirectoryManager(array($dir));
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\InfoManager($directoryManager, $appConfig);
		$this->assertEquals('1.2.3', $manager->getAppVersion('test'));
	}

	public function testGetVersionNoDirs() {
		$directoryManager = $this->getDirectoryManager(array());
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\InfoManager($directoryManager, $appConfig);
		$this->assertEquals('0.0.0', $manager->getAppVersion('test'));
	}

	public function testGetVersionNotInstalled() {
		$path = \OC::$server->getTempManager()->getTemporaryFolder();
		$dir = new Directory($path, '', false);

		$directoryManager = $this->getDirectoryManager(array($dir));
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\InfoManager($directoryManager, $appConfig);
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

		$directoryManager = $this->getDirectoryManager(array($dir));
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\InfoManager($directoryManager, $appConfig);
		$info = $manager->getAppInfo('test');
		$this->assertEquals('0.0.0', $info->getInstalledVersion());
		$this->assertEquals('1.2.3', $info->getVersion());
		$this->assertEquals('Files', $info->getName());
	}

	public function testGetAppInfoNotInstalled() {
		$path = \OC::$server->getTempManager()->getTemporaryFolder();
		$dir = new Directory($path, '', false);

		$directoryManager = $this->getDirectoryManager(array($dir));
		$appConfig = $this->getAppConfig();
		$manager = new \OC\App\InfoManager($directoryManager, $appConfig);
		$this->assertNull($manager->getAppInfo('test'));
	}
}
