<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\ServerOnNetwork;

/**
 * FindServersOnNetworkResponse - Response with servers discovered on network
 */
final readonly class FindServersOnNetworkResponse implements IEncodeable, ServiceResponse
{
    private const int TYPE_ID = 12193;

    /**
     * @param ResponseHeader $responseHeader Response header
     * @param DateTime $lastCounterResetTime Time when the server's record ID counter was last reset
     * @param ServerOnNetwork[] $servers List of servers discovered on the network
     */
    public function __construct(
        public ResponseHeader $responseHeader,
        public DateTime $lastCounterResetTime,
        public array $servers,
    ) {
        foreach ($servers as $server) {
            if (!$server instanceof ServerOnNetwork) {
                throw new InvalidArgumentException('Servers must be ServerOnNetwork instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);
        $this->lastCounterResetTime->encode($encoder);

        $encoder->writeInt32(count($this->servers));
        foreach ($this->servers as $server) {
            $server->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);
        $lastCounterResetTime = DateTime::decode($decoder);

        $serverCount = $decoder->readInt32();
        $servers = [];
        for ($i = 0; $i < $serverCount; $i++) {
            $servers[] = ServerOnNetwork::decode($decoder);
        }

        return new self(
            responseHeader: $responseHeader,
            lastCounterResetTime: $lastCounterResetTime,
            servers: $servers,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
