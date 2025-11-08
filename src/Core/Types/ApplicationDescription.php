<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * Application types
 */
enum ApplicationType: int
{
    case Server = 0;
    case Client = 1;
    case ClientAndServer = 2;
    case DiscoveryServer = 3;
}

/**
 * Describes an OPC UA application
 */
final class ApplicationDescription
{
    /**
     * @param string $applicationUri Unique identifier for the application
     * @param string $productUri Unique identifier for the product
     * @param LocalizedText $applicationName Human-readable name
     * @param ApplicationType $applicationType The type of application
     * @param string|null $gatewayServerUri Gateway server URI (if applicable)
     * @param string|null $discoveryProfileUri Discovery profile URI (if applicable)
     * @param string[] $discoveryUrls URLs for discovery
     */
    public function __construct(
        public readonly string $applicationUri,
        public readonly string $productUri,
        public readonly LocalizedText $applicationName,
        public readonly ApplicationType $applicationType,
        public readonly ?string $gatewayServerUri,
        public readonly ?string $discoveryProfileUri,
        public readonly array $discoveryUrls,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeString($this->applicationUri);
        $encoder->writeString($this->productUri);
        $this->applicationName->encode($encoder);
        $encoder->writeInt32($this->applicationType->value);
        $encoder->writeString($this->gatewayServerUri);
        $encoder->writeString($this->discoveryProfileUri);

        $encoder->writeInt32(count($this->discoveryUrls));
        foreach ($this->discoveryUrls as $url) {
            $encoder->writeString($url);
        }
    }

    public static function decode(BinaryDecoder $decoder): self
    {
        $applicationUri = $decoder->readString();
        $productUri = $decoder->readString();
        $applicationName = LocalizedText::decode($decoder);
        $applicationType = ApplicationType::from($decoder->readInt32());
        $gatewayServerUri = $decoder->readString();
        $discoveryProfileUri = $decoder->readString();

        $urlCount = $decoder->readInt32();
        $discoveryUrls = [];
        for ($i = 0; $i < $urlCount; $i++) {
            $url = $decoder->readString();
            if ($url !== null) {
                $discoveryUrls[] = $url;
            }
        }

        if ($applicationUri === null) {
            throw new RuntimeException('Application URI cannot be null');
        }

        if ($productUri === null) {
            throw new RuntimeException('Product URI cannot be null');
        }

        return new self(
            applicationUri: $applicationUri,
            productUri: $productUri,
            applicationName: $applicationName,
            applicationType: $applicationType,
            gatewayServerUri: $gatewayServerUri,
            discoveryProfileUri: $discoveryProfileUri,
            discoveryUrls: $discoveryUrls,
        );
    }
}
