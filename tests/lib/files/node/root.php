<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Node;

use OC\Files\Cache\Cache;
use OC\Files\NotPermittedException;
use OC\Files\Mount\Manager;

class Root extends \PHPUnit_Framework_TestCase {
	private $user;

	public function setUp() {
		$this->user = new \OC\User\User('', new \OC_User_Dummy);
	}

	public function testMount() {
		$manager = new Manager();
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$root->mount($storage, '');
		$mount = $manager->get('/');
		$this->assertEquals($storage, $mount->getStorage());
		$this->assertEquals('/', $mount->getMountPoint());
	}

	public function testGet() {
		$manager = new Manager();
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$watcher = $this->getMock('\OC\Files\Cache\Watcher', array(), array($storage));
		$cache->expects($this->any())
			->method('getStatus')
			->will($this->returnValue(Cache::COMPLETE));
		$cache->expects($this->once())
			->method('get')
			->with('bar/foo')
			->will($this->returnValue(array('fileid' => 10, 'path' => 'bar/foo', 'name', 'mimetype' => 'text/plain')));
		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));
		$storage->expects($this->any())
			->method('getWatcher')
			->will($this->returnValue($watcher));
		$storage->expects($this->any())
			->method('file_exists')
			->with('bar/foo')
			->will($this->returnValue(true));

		$root->mount($storage, '');
		$node = $root->get('/bar/foo');
		$this->assertEquals(10, $node->getId());
		$this->assertInstanceOf('\OC\Files\Node\File', $node);
	}

	/**
	 * @expectedException \OC\Files\NotFoundException
	 */
	public function testGetNotFound() {
		$manager = new Manager();
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$watcher = $this->getMock('\OC\Files\Cache\Watcher', array(), array($storage));
		$cache->expects($this->any())
			->method('getStatus')
			->will($this->returnValue(Cache::COMPLETE));
		$storage->expects($this->any())
			->method('file_exists')
			->with('bar/foo')
			->will($this->returnValue(true));
		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));
		$storage->expects($this->any())
			->method('getWatcher')
			->will($this->returnValue($watcher));

		$root->mount($storage, '');
		$root->get('/bar/foo');
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testGetInvalidPath() {
		$manager = new Manager();
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 */
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$root->get('/../foo');
	}

	/**
	 * @expectedException \OC\Files\NotFoundException
	 */
	public function testGetNoStorages() {
		$manager = new Manager();
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 */
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$root->get('/bar/foo');
	}
}
