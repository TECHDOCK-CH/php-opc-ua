<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * RegisterNodesRequest - Register nodes for repeated access
 *
 * Allows server to create aliases for faster node access.
 * Useful when reading the same nodes repeatedly.
 */
final readonly class RegisterNodesRequest implements IEncodeable, ServiceRequest
{
    private const int TYPE_ID = 558;

    /**
     * @param RequestHeader $requestHeader Request header
     * @param NodeId[] $nodesToRegister Nodes to register
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public array $nodesToRegister,
    ) {
        foreach ($nodesToRegister as $nodeId) {
            if (!$nodeId instanceof NodeId) {
                throw new InvalidArgumentException('Nodes must be NodeId instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);

        $encoder->writeInt32(count($this->nodesToRegister));
        foreach ($this->nodesToRegister as $nodeId) {
            $nodeId->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);

        $count = $decoder->readInt32();
        $nodesToRegister = [];
        for ($i = 0; $i < $count; $i++) {
            $nodesToRegister[] = NodeId::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            nodesToRegister: $nodesToRegister,
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
     * Create request to register nodes
     *
     * @param NodeId[] $nodesToRegister
     */
    public static function create(
        array $nodesToRegister,
        ?RequestHeader $requestHeader = null,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            nodesToRegister: $nodesToRegister,
        );
    }
}
