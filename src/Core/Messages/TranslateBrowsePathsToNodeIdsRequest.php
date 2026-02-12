<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\BrowsePath;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * TranslateBrowsePathsToNodeIdsRequest - Translate browse paths to NodeIds
 *
 * Resolves path-based references (like "Objects/Server/ServerStatus") to NodeIds.
 */
final readonly class TranslateBrowsePathsToNodeIdsRequest implements IEncodeable, ServiceRequest
{
    private const int TYPE_ID = 553;

    /**
     * @param RequestHeader $requestHeader Request header
     * @param BrowsePath[] $browsePaths Paths to translate
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public array $browsePaths,
    ) {
        foreach ($browsePaths as $path) {
            if (!$path instanceof BrowsePath) {
                throw new InvalidArgumentException('Browse paths must be BrowsePath instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);

        $encoder->writeInt32(count($this->browsePaths));
        foreach ($this->browsePaths as $path) {
            $path->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);

        $count = $decoder->readInt32();
        $browsePaths = [];
        for ($i = 0; $i < $count; $i++) {
            $browsePaths[] = BrowsePath::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            browsePaths: $browsePaths,
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
     * Create request for translating paths
     *
     * @param BrowsePath[] $browsePaths
     */
    public static function create(
        array $browsePaths,
        ?RequestHeader $requestHeader = null,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            browsePaths: $browsePaths,
        );
    }
}
