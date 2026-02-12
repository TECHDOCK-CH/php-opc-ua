<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * UnregisterNodesRequest - Unregister previously registered nodes
 *
 * Frees server resources for registered nodes that are no longer needed.
 */
final readonly class UnregisterNodesRequest implements IEncodeable, ServiceRequest
{
    private const int TYPE_ID = 564;

    /**
     * @param RequestHeader $requestHeader Request header
     * @param NodeId[] $nodesToUnregister Registered nodes to unregister
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public array $nodesToUnregister,
    ) {
        foreach ($nodesToUnregister as $nodeId) {
            if (!$nodeId instanceof NodeId) {
                throw new InvalidArgumentException('Nodes must be NodeId instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);

        $encoder->writeInt32(count($this->nodesToUnregister));
        foreach ($this->nodesToUnregister as $nodeId) {
            $nodeId->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);

        $count = $decoder->readInt32();
        $nodesToUnregister = [];
        for ($i = 0; $i < $count; $i++) {
            $nodesToUnregister[] = NodeId::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            nodesToUnregister: $nodesToUnregister,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }

    public function getRequestHeader(): RequestHeader
    {
        return $this->requestHeader;
    }

    /**
     * Create request to unregister nodes
     *
     * @param NodeId[] $nodesToUnregister
     */
    public static function create(
        array $nodesToUnregister,
        ?RequestHeader $requestHeader = null,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            nodesToUnregister: $nodesToUnregister,
        );
    }
}
