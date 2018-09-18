<?php

namespace Enqueue\Tests\Client\Driver;

use Enqueue\Client\Driver\GenericDriver;
use Enqueue\Client\Driver\StompDriver;
use Enqueue\Client\DriverInterface;
use Enqueue\Client\Message;
use Enqueue\Client\MessagePriority;
use Enqueue\Client\RouteCollection;
use Enqueue\Stomp\StompContext;
use Enqueue\Stomp\StompDestination;
use Enqueue\Stomp\StompMessage;
use Enqueue\Stomp\StompProducer;
use Enqueue\Test\ClassExtensionTrait;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrProducer;
use Interop\Queue\PsrQueue;
use Interop\Queue\PsrTopic;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StompDriverTest extends TestCase
{
    use ClassExtensionTrait;
    use GenericDriverTestsTrait;

    public function testShouldImplementsDriverInterface()
    {
        $this->assertClassImplements(DriverInterface::class, StompDriver::class);
    }

    public function testShouldBeSubClassOfGenericDriver()
    {
        $this->assertClassExtends(GenericDriver::class, StompDriver::class);
    }

    public function testSetupBrokerShouldOnlyLogMessageThatStompDoesNotSupportBrokerSetup()
    {
        $driver = new StompDriver(
            $this->createContextMock(),
            $this->createDummyConfig(),
            new RouteCollection([])
        );

        $logger = $this->createLoggerMock();
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('[StompDriver] Stomp protocol does not support broker configuration')
        ;

        $driver->setupBroker($logger);
    }

    public function testShouldCreateDurableQueue()
    {
        $context = $this->createContextMock();
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($this->createQueue('aName'))
        ;

        $driver = $this->createDriver(
            $context,
            $this->createDummyConfig(),
            new RouteCollection([])
        );

        /** @var StompDestination $queue */
        $queue = $driver->createQueue('aName');

        $this->assertTrue($queue->isDurable());
        $this->assertFalse($queue->isAutoDelete());
        $this->assertFalse($queue->isExclusive());
    }

    public function testShouldSetPersistedTrueOnCreateTransportMessage()
    {
        $clientMessage = new Message();

        $context = $this->createContextMock();
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->willReturn($this->createMessage())
        ;

        $driver = $this->createDriver(
            $context,
            $this->createDummyConfig(),
            new RouteCollection([])
        );

        /** @var StompMessage $transportMessage */
        $transportMessage = $driver->createTransportMessage($clientMessage);

        $this->assertTrue($transportMessage->isPersistent());
    }

    protected function createDriver(...$args): DriverInterface
    {
        return new StompDriver(...$args);
    }

    /**
     * @return StompContext
     */
    protected function createContextMock(): PsrContext
    {
        return $this->createMock(StompContext::class);
    }

    /**
     * @return StompProducer
     */
    protected function createProducerMock(): PsrProducer
    {
        return $this->createMock(StompProducer::class);
    }

    /**
     * @return StompDestination
     */
    protected function createQueue(string $name): PsrQueue
    {
        return new StompDestination();
    }

    /**
     * @return StompDestination
     */
    protected function createTopic(string $name): PsrTopic
    {
        return new StompDestination();
    }

    /**
     * @return StompMessage
     */
    protected function createMessage(): PsrMessage
    {
        return new StompMessage();
    }

    protected function assertTransportMessage(PsrMessage $transportMessage): void
    {
        $this->assertSame('body', $transportMessage->getBody());
        $this->assertEquals([
            'hkey' => 'hval',
            'message_id' => 'theMessageId',
            'timestamp' => 1000,
            'reply-to' => 'theReplyTo',
            'persistent' => true,
            'correlation_id' => 'theCorrelationId',
        ], $transportMessage->getHeaders());
        $this->assertEquals([
            'pkey' => 'pval',
            'X-Enqueue-Content-Type' => 'ContentType',
            'X-Enqueue-Priority' => MessagePriority::HIGH,
            'X-Enqueue-Expire' => 123,
            'X-Enqueue-Delay' => 345,
        ], $transportMessage->getProperties());
        $this->assertSame('theMessageId', $transportMessage->getMessageId());
        $this->assertSame(1000, $transportMessage->getTimestamp());
        $this->assertSame('theReplyTo', $transportMessage->getReplyTo());
        $this->assertSame('theCorrelationId', $transportMessage->getCorrelationId());
    }

    protected function createLoggerMock(): LoggerInterface
    {
        return $this->createMock(LoggerInterface::class);
    }
}
