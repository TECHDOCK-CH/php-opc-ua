<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * GetEndpoints service request
 */
final class GetEndpointsRequest implements ServiceRequest
{
    private const int TYPE_ID = 428;

    /**
     * @param string[] $localeIds
     * @param string[] $profileUris
     */
    public function __construct(
        public readonly RequestHeader $requestHeader,
        public readonly string $endpointUrl,
        public readonly array $localeIds = [],
        public readonly array $profileUris = [],
    ) {
    }

    public static function create(string $endpointUrl): self
    {
        return new self(
            requestHeader: new RequestHeader(
                authenticationToken: NodeId::numeric(0, 0),
                timestamp: DateTime::now(),
                requestHandle: 0,
                returnDiagnostics: 0,
                auditEntryId: null,
                timeoutHint: 15000,
                additionalHeader: ExtensionObject::empty(NodeId::numeric(0, 0)),
            ),
            endpointUrl: $endpointUrl,
            localeIds: [],
            profileUris: [],
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // TypeId is handled by SecureChannel layer, not here
        // Encode request header
        $this->requestHeader->encode($encoder);

        // Encode endpoint URL
        $encoder->writeString($this->endpointUrl);

        // Encode locale IDs array
        $encoder->writeInt32(count($this->localeIds));
        foreach ($this->localeIds as $localeId) {
            $encoder->writeString($localeId);
        }

        // Encode profile URIs array
        $encoder->writeInt32(count($this->profileUris));
        foreach ($this->profileUris as $profileUri) {
            $encoder->writeString($profileUri);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        // TypeId is handled by SecureChannel layer, not here
        // Decode request header
        $requestHeader = RequestHeader::decode($decoder);

        // Decode endpoint URL
        $endpointUrl = $decoder->readString();

        // Decode locale IDs
        $localeIdCount = $decoder->readInt32();
        $localeIds = [];
        for ($i = 0; $i < $localeIdCount; $i++) {
            $localeId = $decoder->readString();
            if ($localeId !== null) {
                $localeIds[] = $localeId;
            }
        }

        // Decode profile URIs
        $profileUriCount = $decoder->readInt32();
        $profileUris = [];
        for ($i = 0; $i < $profileUriCount; $i++) {
            $profileUri = $decoder->readString();
            if ($profileUri !== null) {
                $profileUris[] = $profileUri;
            }
        }

        if ($endpointUrl === null) {
            throw new RuntimeException('Endpoint URL cannot be null');
        }

        return new self(
            requestHeader: $requestHeader,
            endpointUrl: $endpointUrl,
            localeIds: $localeIds,
            profileUris: $profileUris,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
