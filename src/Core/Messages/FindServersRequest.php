<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * FindServersRequest - Discover OPC UA servers
 *
 * Returns a list of ApplicationDescriptions for available OPC UA servers.
 */
final readonly class FindServersRequest implements IEncodeable, ServiceRequest
{
    private const int TYPE_ID = 420;

    /**
     * @param RequestHeader $requestHeader Request header
     * @param string $endpointUrl Network address that the client used to reach the discovery endpoint
     * @param string[] $localeIds List of locales to use (e.g., ['en-US', 'de-DE'])
     * @param string[] $serverUris List of server URIs to filter results (empty = all servers)
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public string $endpointUrl,
        public array $localeIds,
        public array $serverUris,
    ) {
        foreach ($localeIds as $locale) {
            if (!is_string($locale)) {
                throw new InvalidArgumentException('Locale IDs must be strings');
            }
        }
        foreach ($serverUris as $uri) {
            if (!is_string($uri)) {
                throw new InvalidArgumentException('Server URIs must be strings');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeString($this->endpointUrl);

        $encoder->writeInt32(count($this->localeIds));
        foreach ($this->localeIds as $locale) {
            $encoder->writeString($locale);
        }

        $encoder->writeInt32(count($this->serverUris));
        foreach ($this->serverUris as $uri) {
            $encoder->writeString($uri);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $endpointUrl = $decoder->readString();

        $localeCount = $decoder->readInt32();
        $localeIds = [];
        for ($i = 0; $i < $localeCount; $i++) {
            $locale = $decoder->readString();
            if ($locale !== null) {
                $localeIds[] = $locale;
            }
        }

        $uriCount = $decoder->readInt32();
        $serverUris = [];
        for ($i = 0; $i < $uriCount; $i++) {
            $uri = $decoder->readString();
            if ($uri !== null) {
                $serverUris[] = $uri;
            }
        }

        if ($endpointUrl === null) {
            throw new RuntimeException('Endpoint URL cannot be null');
        }

        return new self(
            requestHeader: $requestHeader,
            endpointUrl: $endpointUrl,
            localeIds: $localeIds,
            serverUris: $serverUris,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }

    /**
     * Create request for finding servers
     *
     * @param string $endpointUrl Discovery endpoint URL
     * @param string[] $localeIds Preferred locales (empty = server default)
     * @param string[] $serverUris Filter by server URIs (empty = all servers)
     */
    public static function create(
        string $endpointUrl,
        array $localeIds = [],
        array $serverUris = [],
        ?RequestHeader $requestHeader = null,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            endpointUrl: $endpointUrl,
            localeIds: $localeIds,
            serverUris: $serverUris,
        );
    }
}
