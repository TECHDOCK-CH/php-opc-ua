<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\ApplicationDescription;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * FindServersResponse - Response with discovered servers
 */
final readonly class FindServersResponse implements IEncodeable, ServiceResponse
{
    private const int TYPE_ID = 423;

    /**
     * @param ResponseHeader $responseHeader Response header
     * @param ApplicationDescription[] $servers List of discovered servers
     */
    public function __construct(
        public ResponseHeader $responseHeader,
        public array $servers,
    ) {
        foreach ($servers as $server) {
            if (!$server instanceof ApplicationDescription) {
                throw new InvalidArgumentException('Servers must be ApplicationDescription instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);

        $encoder->writeInt32(count($this->servers));
        foreach ($this->servers as $server) {
            $server->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);

        $serverCount = $decoder->readInt32();
        $servers = [];
        for ($i = 0; $i < $serverCount; $i++) {
            $servers[] = ApplicationDescription::decode($decoder);
        }

        return new self(
            responseHeader: $responseHeader,
            servers: $servers,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
