<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * BrowseRequest - Browse the references of one or more nodes
 */
final readonly class BrowseRequest implements IEncodeable, ServiceRequest
{
    private const int TYPE_ID = 527;

    /**
     * @param BrowseDescription[] $nodesToBrowse
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public ?ViewDescription $view,
        public int $requestedMaxReferencesPerNode,
        public array $nodesToBrowse,
    ) {
    }

    /**
     * Create a browse request for a single node
     */
    public static function forNode(
        BrowseDescription $browseDescription,
        int $requestedMaxReferencesPerNode = 0, // 0 = no limit
        ?RequestHeader $requestHeader = null,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            view: null,
            requestedMaxReferencesPerNode: $requestedMaxReferencesPerNode,
            nodesToBrowse: [$browseDescription],
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);

        // ViewDescription (optional)
        if ($this->view === null) {
            // Encode null ViewDescription
            NodeId::numeric(0, 0)->encode($encoder); // ViewId
            $encoder->writeUInt64(0); // Timestamp
            $encoder->writeUInt32(0); // ViewVersion
        } else {
            $this->view->encode($encoder);
        }

        $encoder->writeUInt32($this->requestedMaxReferencesPerNode);

        // Array of BrowseDescriptions
        $encoder->writeUInt32(count($this->nodesToBrowse));
        foreach ($this->nodesToBrowse as $browseDesc) {
            $browseDesc->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $view = ViewDescription::decode($decoder);
        $requestedMaxReferencesPerNode = $decoder->readUInt32();

        $count = $decoder->readUInt32();
        $nodesToBrowse = [];
        for ($i = 0; $i < $count; $i++) {
            $nodesToBrowse[] = BrowseDescription::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            view: $view,
            requestedMaxReferencesPerNode: $requestedMaxReferencesPerNode,
            nodesToBrowse: $nodesToBrowse,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
