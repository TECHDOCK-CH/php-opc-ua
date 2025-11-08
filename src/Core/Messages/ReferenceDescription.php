<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\ExpandedNodeId;
use TechDock\OpcUa\Core\Types\LocalizedText;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\QualifiedName;

/**
 * ReferenceDescription - Description of a reference
 */
final readonly class ReferenceDescription implements IEncodeable
{
    public function __construct(
        public NodeId $referenceTypeId,
        public bool $isForward,
        public ExpandedNodeId $nodeId,
        public QualifiedName $browseName,
        public LocalizedText $displayName,
        public int $nodeClass,
        public ?ExpandedNodeId $typeDefinition,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->referenceTypeId->encode($encoder);
        $encoder->writeBoolean($this->isForward);
        $this->nodeId->encode($encoder);
        $this->browseName->encode($encoder);
        $this->displayName->encode($encoder);
        $encoder->writeUInt32($this->nodeClass);

        if ($this->typeDefinition === null) {
            ExpandedNodeId::fromNodeId(NodeId::numeric(0, 0))->encode($encoder);
        } else {
            $this->typeDefinition->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $referenceTypeId = NodeId::decode($decoder);
        $isForward = $decoder->readBoolean();
        $nodeId = ExpandedNodeId::decode($decoder);
        $browseName = QualifiedName::decode($decoder);
        $displayName = LocalizedText::decode($decoder);
        $nodeClass = $decoder->readUInt32();
        $typeDefinition = ExpandedNodeId::decode($decoder);

        return new self(
            referenceTypeId: $referenceTypeId,
            isForward: $isForward,
            nodeId: $nodeId,
            browseName: $browseName,
            displayName: $displayName,
            nodeClass: $nodeClass,
            typeDefinition: $typeDefinition,
        );
    }
}
