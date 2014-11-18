<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\App;

class Directory extends \PHPUnit_Framework_TestCase {
	private $path;

	public function setUp() {
		$this->path = \OC::$server->getTempManager()->getTemporaryFolder();
	}

	public function testHasApp() {
		$directory = new \OC\App\Directory($this->path, '', false);
		mkdir($this->path . '/test');
		mkdir($this->path . '/test/appinfo');

		$this->assertFalse($directory->hasApp('test'));

		touch($this->path . '/test/appinfo/app.php');
		$this->assertTrue($directory->hasApp('test'));
	}

	public function testListApps() {
		$directory = new \OC\App\Directory($this->path, '', false);
		mkdir($this->path . '/test');
		mkdir($this->path . '/test/appinfo');
		touch($this->path . '/test/appinfo/app.php');
		mkdir($this->path . '/foo');
		mkdir($this->path . '/foo/appinfo');
		touch($this->path . '/foo/appinfo/app.php');
		mkdir($this->path . '/bar');
		mkdir($this->path . '/bar/appinfo');

		$this->assertEquals(array('foo', 'test'), $directory->listApps());
	}
}
