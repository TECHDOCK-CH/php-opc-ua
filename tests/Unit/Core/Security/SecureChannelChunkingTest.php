<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Security;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecureChannel;
use TechDock\OpcUa\Core\Security\SymmetricSecurityHeader;
use TechDock\OpcUa\Core\Transport\MessageHeader;
use TechDock\OpcUa\Core\Transport\TcpConnectionInterface;

final class SecureChannelChunkingTest extends TestCase
{
    public function testSplitsRequestsIntoMultipleChunks(): void
    {
        $connection = new CaptureTcpConnection();
        $channel = new SecureChannel($connection, MessageSecurityMode::None);

        $this->setPrivateProperty($channel, 'sendBufferSize', 40);
        $this->setPrivateProperty($channel, 'maxMessageSize', 0);
        $this->setPrivateProperty($channel, 'maxChunkCount', 0);

        $securityHeader = new SymmetricSecurityHeader(secureChannelId: 1, tokenId: 1);
        $headerEncoder = new BinaryEncoder();
        $securityHeader->encode($headerEncoder);
        $securityHeaderBytes = $headerEncoder->getBytes();

        $method = new ReflectionMethod($channel, 'sendSymmetricRequestChunks');
        $method->setAccessible(true);
        $method->invoke($channel, $securityHeaderBytes, str_repeat('A', 20), 1);

        self::assertCount(2, $connection->sent);

        $firstHeader = $this->decodeHeader($connection->sent[0]);
        $secondHeader = $this->decodeHeader($connection->sent[1]);

        self::assertTrue($firstHeader->isIntermediate());
        self::assertTrue($secondHeader->isFinal());
    }

    private function decodeHeader(string $data): MessageHeader
    {
        $decoder = new BinaryDecoder($data);
        return MessageHeader::decode($decoder);
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}

final class CaptureTcpConnection implements TcpConnectionInterface
{
    /** @var string[] */
    public array $sent = [];

    public function connect(): void
    {
    }

    public function send(string $data): void
    {
        $this->sent[] = $data;
    }

    public function receive(int $length): string
    {
        throw new RuntimeException('Not implemented');
    }

    public function receiveHeader(): MessageHeader
    {
        throw new RuntimeException('Not implemented');
    }

    public function receiveMessage(): string
    {
        throw new RuntimeException('Not implemented');
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function close(): void
    {
    }

    public function getEndpointUrl(): string
    {
        return 'opc.tcp://test';
    }
}
