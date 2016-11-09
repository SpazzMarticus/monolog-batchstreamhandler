<?php

namespace SpazzMarticus\Monolog\Handler;

use Monolog\TestCase;
use Monolog\Logger;

class BatchStreamHandlerTest extends TestCase
{

    public function testWrite()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new BatchStreamHandler($handle);
        $handler->pushHeadLine('head1');
        $handler->pushHeadLines(['head2', 'head3']);
        $handler->pushFootLine('foot1');
        $handler->pushFootLines(['foot2', 'foot3']);
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handleBatch([
            $this->getRecord(Logger::WARNING, 'test'),
            $this->getRecord(Logger::WARNING, 'test2'),
            $this->getRecord(Logger::WARNING, 'test3')
        ]);
        fseek($handle, 0);
        $this->assertEquals("head1\nhead2\nhead3\ntesttest2test3foot1\nfoot2\nfoot3\n", stream_get_contents($handle));
    }

    /**
     * @expectedException \Exception
     */
    public function testExceptionWhenCallingHandle()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new BatchStreamHandler($handle);
        $handler->handle($this->getRecord());
    }
    /**
     * Tests adapted from StreamHandlerTest
     */

    /**
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::close
     */
    public function testCloseKeepsExternalHandlersOpen()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new BatchStreamHandler($handle);
        $this->assertTrue(is_resource($handle));
        $handler->close();
        $this->assertTrue(is_resource($handle));
    }

    /**
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::close
     */
    public function testClose()
    {
        $handler = new BatchStreamHandler('php://memory');
        $handler->handleBatch([$this->getRecord(Logger::WARNING, 'test')]);
        $stream = $handler->getStream();
        $this->assertTrue(is_resource($stream));
        $handler->close();
        $this->assertFalse(is_resource($stream));
    }
    
    /**
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::write
     */
    public function testWriteCreatesTheStreamResource()
    {
        $handler = new BatchStreamHandler('php://memory');
        $handler->handleBatch([$this->getRecord()]);
    }

    /**
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::__construct
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::write
     */
    public function testWriteLocking()
    {
        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'monolog_locked_log';
        $handler = new BatchStreamHandler($temp, Logger::DEBUG, true, null, true);
        $handler->handleBatch([$this->getRecord()]);
    }

    /**
     * @expectedException LogicException
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::__construct
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::write
     */
    public function testWriteMissingResource()
    {
        $handler = new BatchStreamHandler(null);
        $handler->handleBatch([$this->getRecord()]);
    }

    public function invalidArgumentProvider()
    {
        return [
            [1],
            [[]],
            [['bogus://url']],
        ];
    }

    /**
     * @dataProvider invalidArgumentProvider
     * @expectedException InvalidArgumentException
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::__construct
     */
    public function testWriteInvalidArgument($invalidArgument)
    {
        $handler = new BatchStreamHandler($invalidArgument);
    }

    /**
     * @expectedException UnexpectedValueException
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::__construct
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::write
     */
    public function testWriteInvalidResource()
    {
        $handler = new BatchStreamHandler('bogus://url');
        $handler->handleBatch([$this->getRecord()]);
    }

    /**
     * @expectedException UnexpectedValueException
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::__construct
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::write
     */
    public function testWriteNonExistingResource()
    {
        $handler = new BatchStreamHandler('ftp://foo/bar/baz/' . rand(0, 10000));
        $handler->handleBatch([$this->getRecord()]);
    }

    /**
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::__construct
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::write
     */
    public function testWriteNonExistingPath()
    {
        $handler = new BatchStreamHandler(sys_get_temp_dir() . '/bar/' . rand(0, 10000) . DIRECTORY_SEPARATOR . rand(0, 10000));
        $handler->handleBatch([$this->getRecord()]);
    }

    /**
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::__construct
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::write
     */
    public function testWriteNonExistingFileResource()
    {
        $handler = new BatchStreamHandler('file://' . sys_get_temp_dir() . '/bar/' . rand(0, 10000) . DIRECTORY_SEPARATOR . rand(0, 10000));
        $handler->handleBatch([$this->getRecord()]);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /There is no existing directory at/
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::__construct
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::write
     */
    public function testWriteNonExistingAndNotCreatablePath()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Permissions checks can not run on windows');
        }
        $handler = new BatchStreamHandler('/foo/bar/' . rand(0, 10000) . DIRECTORY_SEPARATOR . rand(0, 10000));
        $handler->handleBatch([$this->getRecord()]);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /There is no existing directory at/
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::__construct
     * @covers SpazzMarticus\Monolog\Handler\BatchStreamHandler::write
     */
    public function testWriteNonExistingAndNotCreatableFileResource()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Permissions checks can not run on windows');
        }
        $handler = new BatchStreamHandler('file:///foo/bar/' . rand(0, 10000) . DIRECTORY_SEPARATOR . rand(0, 10000));
        $handler->handleBatch([$this->getRecord()]);
    }
}
