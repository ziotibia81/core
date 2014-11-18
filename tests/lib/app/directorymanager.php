<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\App;

class MockDirectoryManager extends \OC\App\DirectoryManager {
	private $dirs;

	public function __construct(array $dirs) {
		$this->dirs = $dirs;
	}

	public function getAppDirectories() {
		return $this->dirs;
	}
}

class DirectoryManager extends \PHPUnit_Framework_TestCase {
	private function getDirectory($apps) {
		$dir = $this->getMockBuilder('\OC\App\Directory')
			->disableOriginalConstructor()
			->getMock();
		$dir->expects($this->any())
			->method('listApps')
			->will($this->returnValue($apps));
		$dir->expects($this->any())
			->method('hasApp')
			->will($this->returnCallback(function ($app) use ($apps) {
				return array_search($app, $apps) !== false;
			}));
		return $dir;
	}

	private function getConfig($values) {
		$config = $this->getMockBuilder('\OCP\IConfig')
			->disableOriginalConstructor()
			->getMock();
		$config->expects($this->any())
			->method('getSystemValue')
			->will($this->returnCallback(function ($key, $default) use ($values) {
				return isset($values[$key]) ? $values[$key] : $default;
			}));
		return $config;
	}

	public function testGetDirectories() {
		$config = $this->getConfig(array(
			'apps_paths' =>
				array(
					array(
						'path' => '/srv/http/owncloud/apps',
						'url' => '/apps',
						'writable' => false,
					),
					array(
						'path' => '/srv/http/owncloud/apps2',
						'url' => '/apps2',
						'writable' => true,
					)
				)
		));
		$manager = new \OC\App\DirectoryManager($config);
		$dirs = $manager->getAppDirectories();
		$this->assertCount(2, $dirs);
		$this->assertEquals('/srv/http/owncloud/apps', $dirs[0]->getPath());
		$this->assertEquals('/apps', $dirs[0]->getUrl());
		$this->assertEquals(false, $dirs[0]->isWritable());
		$this->assertEquals('/srv/http/owncloud/apps2', $dirs[1]->getPath());
		$this->assertEquals('/apps2', $dirs[1]->getUrl());
		$this->assertEquals(true, $dirs[1]->isWritable());
	}

	public function testGetDirectoriesEmptyConfig() {
		$config = $this->getConfig(array());
		$manager = new \OC\App\DirectoryManager($config);
		$dirs = $manager->getAppDirectories();
		$this->assertCount(0, $dirs);
	}

	public function testListApps() {
		$manager = new MockDirectoryManager(array(
			$this->getDirectory(array('test')),
			$this->getDirectory(array('foo', 'bar')),
			$this->getDirectory(array())
		));
		$this->assertEquals(array('bar', 'foo', 'test'), $manager->listApps());
	}

	public function testGetDirectoryForApp() {
		$dir = $this->getDirectory(array('foo', 'bar'));
		$manager = new MockDirectoryManager(array(
			$this->getDirectory(array('test')),
			$dir,
			$this->getDirectory(array())
		));
		$this->assertEquals($dir, $manager->getDirectoryForApp('bar'));
	}
}
