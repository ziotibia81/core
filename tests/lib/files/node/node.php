<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Node;

class Node extends \PHPUnit_Framework_TestCase {
	private $user;

	public function setUp() {
		$this->user = new \OC\User\User('', new \OC_User_Dummy);
	}

	public function testStat() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$stat = array(
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50,
			'permissions' => 0
		);
		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', $stat);
		$this->assertEquals($stat, $node->stat());
	}

	public function testGetId() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$stat = array(
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50
		);
		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', $stat);
		$this->assertEquals(1, $node->getId());
	}

	public function testGetSize() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$stat = array(
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50
		);
		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', $stat);
		$this->assertEquals(100, $node->getSize());
	}

	public function testGetEtag() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$stat = array(
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50
		);
		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', $stat);
		$this->assertEquals('qwerty', $node->getEtag());
	}

	public function testGetMTime() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$stat = array(
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50
		);
		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', $stat);
		$this->assertEquals(50, $node->getMTime());
	}

	public function testGetStorage() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array());
		$this->assertEquals($storage, $node->getStorage());
	}

	public function testGetPath() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array());
		$this->assertEquals('/bar/foo', $node->getPath());
	}

	public function testGetInternalPath() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array());
		$this->assertEquals('foo', $node->getInternalPath());
	}

	public function testGetPermissions() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array());
		$this->assertEquals('foo', $node->getInternalPath());
	}

	public function testGetName() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array());
		$this->assertEquals('foo', $node->getName());
	}

	public function testTouchSetMTime() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$userFolder = $this->getMock('\OC\Files\Node\Folder', array(), array(), '', false);

		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		$root->expects($this->any())
			->method('getUserFolder')
			->will($this->returnValue($userFolder));

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->once())
			->method('touch')
			->with($this->equalTo('foo'), $this->equalTo(100));

		$scanner = $this->getMockBuilder('\OC\Files\Cache\Scanner')
			->disableOriginalConstructor()
			->getMock();
		$scanner->expects($this->once())
			->method('scan')
			->with('foo');

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->expects($this->once())
			->method('get')
			->with('foo')
			->will($this->returnValue(array('fileid' => 1, 'mtime' => 100)));

		$storage->expects($this->any())
			->method('getScanner')
			->will($this->returnValue($scanner));
		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_ALL));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\Node($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->touch(100);
		$this->assertEquals(100, $node->getMTime());
	}

	public function testTouchHooks() {
		$test = $this;
		$hooksRun = 0;
		/**
		 * @param \OC\Files\Node\File $node
		 */
		$preListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$test->assertEquals(1, $node->getId());
			$test->assertEquals(50, $node->getMTime());
			$hooksRun++;
		};

		/**
		 * @param \OC\Files\Node\File $node
		 */
		$postListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$test->assertEquals(1, $node->getId());
			$test->assertEquals(100, $node->getMTime());
			$hooksRun++;
		};

		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);
		$root->listen('\OC\Files', 'preTouch', $preListener);
		$root->listen('\OC\Files', 'postTouch', $postListener);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_ALL));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$scanner = $this->getMockBuilder('\OC\Files\Cache\Scanner')
			->disableOriginalConstructor()
			->getMock();
		$scanner->expects($this->once())
			->method('scan')
			->with('foo');

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->expects($this->once())
			->method('get')
			->with('foo')
			->will($this->returnValue(array('fileid' => 1, 'mtime' => 100)));

		$storage->expects($this->any())
			->method('getScanner')
			->will($this->returnValue($scanner));
		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$node = new \OC\Files\Node\Node($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mtime' => 50));
		$node->touch(100);
		$this->assertEquals(2, $hooksRun);
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testTouchNotPermitted() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->never())
			->method('touch');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_READ));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\Node($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->touch(100);
	}
}
