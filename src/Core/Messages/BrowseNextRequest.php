<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * BrowseNext - Continue a Browse operation with continuation points
 *
 * Service ID: 530
 */
final readonly class BrowseNextRequest implements IEncodeable, ServiceRequest
{
    private const int TYPE_ID = 530;

    /**
     * @param string[] $continuationPoints Array of continuation point byte strings
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public bool $releaseContinuationPoints,
        public array $continuationPoints,
    ) {
    }

    /**
     * Create a BrowseNext request to continue browsing
     *
     * @param string[] $continuationPoints
     */
    public static function create(
        array $continuationPoints,
        bool $releaseContinuationPoints = false,
        ?RequestHeader $requestHeader = null,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            releaseContinuationPoints: $releaseContinuationPoints,
            continuationPoints: $continuationPoints,
        );
    }

    /**
     * Create a BrowseNext request to release continuation points
     *
     * @param string[] $continuationPoints
     */
    public static function release(
        array $continuationPoints,
        ?RequestHeader $requestHeader = null,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            releaseContinuationPoints: true,
            continuationPoints: $continuationPoints,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeBoolean($this->releaseContinuationPoints);

        // Array of continuation points
        $encoder->writeUInt32(count($this->continuationPoints));
        foreach ($this->continuationPoints as $continuationPoint) {
            $encoder->writeByteString($continuationPoint);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $releaseContinuationPoints = $decoder->readBoolean();

        $count = $decoder->readUInt32();
        $continuationPoints = [];
        for ($i = 0; $i < $count; $i++) {
            $cp = $decoder->readByteString();
            if ($cp !== null) {
                $continuationPoints[] = $cp;
            }
        }

        return new self(
            requestHeader: $requestHeader,
            releaseContinuationPoints: $releaseContinuationPoints,
            continuationPoints: $continuationPoints,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
