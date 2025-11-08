<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * RegisterNodesResponse - Response with registered node aliases
 */
final readonly class RegisterNodesResponse implements IEncodeable, ServiceResponse
{
    private const int TYPE_ID = 561;

    /**
     * @param ResponseHeader $responseHeader Response header
     * @param NodeId[] $registeredNodeIds Alias NodeIds to use for access
     */
    public function __construct(
        public ResponseHeader $responseHeader,
        public array $registeredNodeIds,
    ) {
        foreach ($registeredNodeIds as $nodeId) {
            if (!$nodeId instanceof NodeId) {
                throw new InvalidArgumentException('Registered nodes must be NodeId instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);

        $encoder->writeInt32(count($this->registeredNodeIds));
        foreach ($this->registeredNodeIds as $nodeId) {
            $nodeId->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);

        $count = $decoder->readInt32();
        $registeredNodeIds = [];
        for ($i = 0; $i < $count; $i++) {
            $registeredNodeIds[] = NodeId::decode($decoder);
        }

        return new self(
            responseHeader: $responseHeader,
            registeredNodeIds: $registeredNodeIds,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
