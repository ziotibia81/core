<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Node;

use OC\Files\NotFoundException;
use OC\Files\NotPermittedException;

class File extends \PHPUnit_Framework_TestCase {
	private $user;

	public function setUp() {
		$this->user = new \OC\User\User('', new \OC_User_Dummy);
	}

	public function testDelete() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->exactly(2))
			->method('emit')
			->will($this->returnValue(true));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->once())
			->method('unlink')
			->with('foo');

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->expects($this->once())
			->method('remove')
			->with('foo');

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

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->delete();
	}

	public function testDeleteHooks() {
		$test = $this;
		$hooksRun = 0;
		/**
		 * @param \OC\Files\Node\File $node
		 */
		$preListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertInstanceOf('\OC\Files\Node\File', $node);
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$test->assertEquals(1, $node->getId());
			$hooksRun++;
		};

		/**
		 * @param \OC\Files\Node\File $node
		 */
		$postListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertInstanceOf('\OC\Files\Node\NonExistingFile', $node);
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$test->assertEquals(1, $node->getId());
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
		$root->listen('\OC\Files', 'preDelete', $preListener);
		$root->listen('\OC\Files', 'postDelete', $postListener);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->once())
			->method('unlink')
			->with($this->equalTo('foo'));

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->expects($this->once())
			->method('remove')
			->with('foo');

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

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->delete();
		$this->assertEquals(2, $hooksRun);
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testDeleteNotPermitted() {
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
			->method('unlink');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_READ));

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->expects($this->never())
			->method('delete');

		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->delete();
	}

	public function testGetContent() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$hook = function ($file) {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$root->listen('\OC\Files', 'preWrite', $hook);
		$root->listen('\OC\Files', 'postWrite', $hook);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->once())
			->method('file_get_contents')
			->with('foo')
			->will($this->returnValue('bar'));

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_READ));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$this->assertEquals('bar', $node->getContent());
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testGetContentNotPermitted() {
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
			->method('file_get_contents');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(0));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->getContent();
	}

	public function testPutContent() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$userFolder = $this->getMock('\OC\Files\Node\Folder', array(), array(), '', false);

		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		$root->expects($this->any())
			->method('getUserFolder')
			->will($this->returnValue($userFolder));

		$userFolder->expects($this->any())
			->method('getRelativePath')
			->with('/bar/foo')
			->will($this->returnValue('foo'));

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->once())
			->method('file_put_contents')
			->with('foo', 'bar');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_UPDATE));

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
			->will($this->returnValue(array('fileid' => 1)));

		$storage->expects($this->any())
			->method('getScanner')
			->will($this->returnValue($scanner));
		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->putContent('bar');
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testPutContentNotPermitted() {
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
			->method('file_put_contents');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_READ));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->putContent('bar');
	}

	public function testGetMimeType() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));

		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain'));
		$this->assertEquals('text/plain', $node->getMimeType());
	}

	public function testFOpenRead() {
		$stream = fopen('php://memory', 'w+');
		fwrite($stream, 'bar');
		rewind($stream);

		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$hook = function ($file) {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$root->listen('\OC\Files', 'preWrite', $hook);
		$root->listen('\OC\Files', 'postWrite', $hook);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->once())
			->method('fopen')
			->with('foo', 'r')
			->will($this->returnValue($stream));

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_READ));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$fh = $node->fopen('r');
		$this->assertEquals($stream, $fh);
		$this->assertEquals('bar', fread($fh, 3));
	}

	public function testFOpenWrite() {
		$stream = fopen('php://memory', 'w+');

		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$hooksCalled = 0;
		$hook = function ($file) use (&$hooksCalled) {
			$hooksCalled++;
		};

		$root->listen('\OC\Files', 'preWrite', $hook);
		$root->listen('\OC\Files', 'postWrite', $hook);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->once())
			->method('fopen')
			->with('foo', 'w')
			->will($this->returnValue($stream));

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_UPDATE | \OCP\PERMISSION_READ));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$fh = $node->fopen('w');
		$this->assertEquals($stream, $fh);
		fwrite($fh, 'bar');
		rewind($fh);
		$this->assertEquals('bar', fread($stream, 3));
		$this->assertEquals(2, $hooksCalled);
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testFOpenReadNotPermitted() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$hook = function ($file) {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$root->listen('\OC\Files', 'preWrite', $hook);
		$root->listen('\OC\Files', 'postWrite', $hook);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->never())
			->method('fopen');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(0));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->fopen('r');
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testFOpenReadWriteNoReadPermissions() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$hook = function () {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$root->listen('\OC\Files', 'preWrite', $hook);
		$root->listen('\OC\Files', 'postWrite', $hook);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->never())
			->method('fopen');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_UPDATE));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->fopen('w');
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testFOpenReadWriteNoWritePermissions() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);

		$hook = function () {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$root->listen('\OC\Files', 'preWrite', $hook);
		$root->listen('\OC\Files', 'postWrite', $hook);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->never())
			->method('fopen');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_READ));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->fopen('w');
	}

	public function testCopySameStorage() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$mount = $this->getMock('\OC\Files\Mount\Mount', array(), array($storage, '/'));
		$mount->expects($this->once())
			->method('getStorage')
			->will($this->returnValue($storage));
		$mount->expects($this->once())
			->method('getInternalPath')
			->with('/bar/asd')
			->will($this->returnValue('asd'));

		$root->expects($this->once())
			->method('getMount')
			->with('/bar/asd')
			->will($this->returnValue($mount));

		$storage->expects($this->once())
			->method('copy')
			->with('foo', 'asd');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain'));
		$parentNode = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar', array('fileid' => 2, 'mimetype' => 'httpd/directory', 'permissions' => \OCP\PERMISSION_CREATE));
		$newNode = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/asd', array('fileid' => 3, 'mimetype' => 'text/plain'));

		$root->expects($this->exactly(2))
			->method('get')
			->will($this->returnValueMap(array(
				array('/bar/asd', $newNode),
				array('/bar', $parentNode)
			)));

		$target = $node->copy('/bar/asd');
		$this->assertInstanceOf('\OC\Files\Node\File', $target);
		$this->assertEquals(3, $target->getId());
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testCopyNotPermitted() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$root->expects($this->never())
			->method('getMount');

		$storage->expects($this->never())
			->method('copy');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain'));
		$parentNode = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar', array('fileid' => 2, 'mimetype' => 'httpd/directory', 'permissions' => 0));

		$root->expects($this->once())
			->method('get')
			->will($this->returnValueMap(array(
				array('/bar', $parentNode)
			)));

		$node->copy('/bar/asd');
	}

	/**
	 * @expectedException \OC\Files\NotFoundException
	 */
	public function testCopyNoParent() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$root->expects($this->never())
			->method('getMount');

		$storage->expects($this->never())
			->method('copy');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain'));

		$root->expects($this->once())
			->method('get')
			->with('/bar/asd')
			->will($this->throwException(new NotFoundException()));

		$node->copy('/bar/asd/foo');
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testCopyParentIsFile() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$root->expects($this->never())
			->method('getMount');

		$storage->expects($this->never())
			->method('copy');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain'));
		$parentNode = new \OC\Files\Node\File($root, $storage, 'foo', '/bar', array('fileid' => 2, 'mimetype' => 'text/plain', 'permissions' => \OCP\PERMISSION_ALL));

		$root->expects($this->once())
			->method('get')
			->will($this->returnValueMap(array(
				array('/bar', $parentNode)
			)));

		$node->copy('/bar/asd');
	}

	public function testCopyDifferentStorage() {
		$source = fopen('static://bar/foo', 'w');
		fwrite($source, 'qwerty');

		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage1
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage2
		 */
		$storage1 = $this->getMock('\OC\Files\Storage\Storage');
		$storage2 = $this->getMock('\OC\Files\Storage\Storage');
		$mount = $this->getMock('\OC\Files\Mount\Mount', array(), array($storage2, '/'));
		$mount->expects($this->once())
			->method('getStorage')
			->will($this->returnValue($storage2));
		$mount->expects($this->once())
			->method('getInternalPath')
			->with('/asd/foo')
			->will($this->returnValue('foo'));

		$root->expects($this->once())
			->method('getMount')
			->with('/asd/foo')
			->will($this->returnValue($mount));

		$storage1->expects($this->once())
			->method('fopen')
			->with('foo', 'r')
			->will($this->returnValue(fopen('static://bar/foo', 'r')));

		$storage1->expects($this->never())
			->method('copy');

		$storage2->expects($this->once())
			->method('fopen')
			->with('foo', 'w')
			->will($this->returnValue(fopen('static://asd/foo', 'w')));

		$node = new \OC\Files\Node\File($root, $storage1, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain', 'permissions' => \OCP\PERMISSION_ALL));
		$parentNode = new \OC\Files\Node\Folder($root, $storage2, '', '/asd', array('fileid' => 2, 'mimetype' => 'httpd/directory', 'permissions' => \OCP\PERMISSION_CREATE));
		$newNode = new \OC\Files\Node\File($root, $storage2, 'foo', '/asd/foo', array('fileid' => 3, 'mimetype' => 'text/plain', 'permissions' => \OCP\PERMISSION_ALL));

		$root->expects($this->exactly(2))
			->method('get')
			->will($this->returnValueMap(array(
				array('/asd/foo', $newNode),
				array('/asd', $parentNode)
			)));

		$target = $node->copy('/asd/foo');
		$this->assertInstanceOf('\OC\Files\Node\File', $target);
		$this->assertEquals(3, $target->getId());
		$targetStream = fopen('static://asd/foo', 'r');
		$this->assertEquals('qwerty', fread($targetStream, 6));
	}

	public function testMoveSameStorage() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$mount = $this->getMock('\OC\Files\Mount\Mount', array(), array($storage, '/'));
		$mount->expects($this->once())
			->method('getStorage')
			->will($this->returnValue($storage));
		$mount->expects($this->once())
			->method('getInternalPath')
			->with('/bar/asd')
			->will($this->returnValue('asd'));

		$root->expects($this->once())
			->method('getMount')
			->with('/bar/asd')
			->will($this->returnValue($mount));

		$storage->expects($this->once())
			->method('rename')
			->with('foo', 'asd');

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->expects($this->once())
			->method('move')
			->with('foo', 'asd');

		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$storage->expects($this->any())
			->method('move')
			->with('');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain'));
		$parentNode = new \OC\Files\Node\Folder($root, $storage, 'bar', '/bar', array('fileid' => 2, 'mimetype' => 'httpd/directory', 'permissions' => \OCP\PERMISSION_CREATE));
		$rootNode = new \OC\Files\Node\Folder($root, $storage, '', '/', array('fileid' => 3, 'mimetype' => 'httpd/directory', 'permissions' => \OCP\PERMISSION_CREATE));

		$root->expects($this->any())
			->method('get')
			->will($this->returnValueMap(array(
				array('/bar', $parentNode),
				array('/', $rootNode)
			)));

		$target = $node->move('/bar/asd');
		$this->assertInstanceOf('\OC\Files\Node\File', $target);
		$this->assertEquals(1, $target->getId());
		$this->assertEquals('/bar/asd', $node->getPath());
		$this->assertEquals('asd', $node->getInternalPath());
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testMoveNotPermitted() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$storage->expects($this->never())
			->method('rename');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain'));
		$parentNode = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar', array('fileid' => 2, 'mimetype' => 'httpd/directory', 'permissions' => 0));

		$root->expects($this->once())
			->method('get')
			->with('/bar')
			->will($this->returnValue($parentNode));

		$node->move('/bar/asd');
	}

	/**
	 * @expectedException \OC\Files\NotFoundException
	 */
	public function testMoveNoParent() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$storage->expects($this->never())
			->method('rename');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain'));
		$parentNode = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar', array('fileid' => 2, 'mimetype' => 'httpd/directory', 'permissions' => 0));

		$root->expects($this->once())
			->method('get')
			->with('/bar')
			->will($this->throwException(new NotFoundException()));

		$node->move('/bar/asd');
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testMoveParentIsFile() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$storage->expects($this->never())
			->method('rename');

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain'));
		$parentNode = new \OC\Files\Node\File($root, $storage, 'foo', '/bar', array('fileid' => 2, 'mimetype' => 'text/plain', 'permissions' => \OCP\PERMISSION_ALL));

		$root->expects($this->once())
			->method('get')
			->with('/bar')
			->will($this->returnValue($parentNode));

		$node->move('/bar/asd');
	}

	public function testMoveDifferentStorage() {
		$source = fopen('static://bar/foo', 'w');
		fwrite($source, 'qwerty');

		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage1
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage2
		 */
		$storage1 = $this->getMock('\OC\Files\Storage\Storage');
		$storage2 = $this->getMock('\OC\Files\Storage\Storage');
		$mount = $this->getMock('\OC\Files\Mount\Mount', array(), array($storage2, '/'));
		$mount->expects($this->once())
			->method('getStorage')
			->will($this->returnValue($storage2));
		$mount->expects($this->once())
			->method('getInternalPath')
			->with('/asd/foo')
			->will($this->returnValue('foo'));

		$root->expects($this->once())
			->method('getMount')
			->with('/asd/foo')
			->will($this->returnValue($mount));

		$storage1->expects($this->once())
			->method('fopen')
			->with('foo', 'r')
			->will($this->returnValue(fopen('static://bar/foo', 'r')));

		$storage1->expects($this->never())
			->method('copy');

		$storage1->expects($this->once())
			->method('unlink')
			->with('foo');

		$storage2->expects($this->once())
			->method('fopen')
			->with('foo', 'w')
			->will($this->returnValue(fopen('static://asd/foo', 'w')));

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->expects($this->once())
			->method('remove')
			->with('foo');

		$storage1->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$node = new \OC\Files\Node\File($root, $storage1, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain', 'permissions' => \OCP\PERMISSION_ALL));
		$parentNode = new \OC\Files\Node\Folder($root, $storage2, '', '/asd', array('fileid' => 2, 'mimetype' => 'httpd/directory', 'permissions' => \OCP\PERMISSION_CREATE));
		$targetNode = new \OC\Files\Node\File($root, $storage2, 'foo', '/asd/foo', array('fileid' => 3, 'mimetype' => 'text/plain'));
		$rootNode = new \OC\Files\Node\Folder($root, $storage2, '', '/', array('fileid' => 2, 'mimetype' => 'httpd/directory', 'permissions' => \OCP\PERMISSION_CREATE));

		$root->expects($this->any())
			->method('get')
			->will($this->returnValueMap(array(
				array('/', $rootNode),
				array('/asd', $parentNode),
				array('/asd/foo', $targetNode)
			)));

		$target = $node->move('/asd/foo');
		$this->assertInstanceOf('\OC\Files\Node\File', $target);
		$this->assertEquals(3, $target->getId());
		$targetStream = fopen('static://asd/foo', 'r');
		$this->assertEquals('qwerty', fread($targetStream, 6));
		$this->assertEquals($storage2, $node->getStorage());
	}

	public function testPutContentStream() {
		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$userFolder = $this->getMock('\OC\Files\Node\Folder', array(), array(), '', false);

		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		$root->expects($this->any())
			->method('getUserFolder')
			->will($this->returnValue($userFolder));

		$userFolder->expects($this->any())
			->method('getRelativePath')
			->with('/bar/foo')
			->will($this->returnValue('foo'));

		$scanner = $this->getMockBuilder('\OC\Files\Cache\Scanner')
			->disableOriginalConstructor()
			->getMock();

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();


		$storage->expects($this->any())
			->method('getScanner')
			->will($this->returnValue($scanner));
		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$storage->expects($this->never())
			->method('file_put_contents');

		$targetStream = fopen('static://target', 'w');
		$sourceStream = fopen('static://source', 'w+');

		fwrite($sourceStream, 'qwerty');
		rewind($sourceStream);

		$storage->expects($this->once())
			->method('fopen')
			->with('foo')
			->will($this->returnValue($targetStream));

		$node = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'mimetype' => 'text/plain', 'permissions' => \OCP\PERMISSION_ALL));

		$node->putContent($sourceStream);
		$this->assertEquals('qwerty', stream_get_contents(fopen('static://target', 'r')));
	}
}
