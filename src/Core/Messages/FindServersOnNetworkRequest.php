<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * FindServersOnNetworkRequest - Discover servers on the local network
 *
 * Used for multicast discovery of OPC UA servers on the local network.
 */
final readonly class FindServersOnNetworkRequest implements IEncodeable, ServiceRequest
{
    private const int TYPE_ID = 12190;

    /**
     * @param RequestHeader $requestHeader Request header
     * @param int $startingRecordId Starting record ID for paging (0 = from beginning)
     * @param int $maxRecordsToReturn Maximum records to return (0 = no limit)
     * @param string[] $serverCapabilityFilter Filter by capabilities (e.g., ['DA', 'HD'])
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public int $startingRecordId,
        public int $maxRecordsToReturn,
        public array $serverCapabilityFilter,
    ) {
        if ($startingRecordId < 0) {
            throw new InvalidArgumentException('Starting record ID must be non-negative');
        }
        if ($maxRecordsToReturn < 0) {
            throw new InvalidArgumentException('Max records to return must be non-negative');
        }
        foreach ($serverCapabilityFilter as $capability) {
            if (!is_string($capability)) {
                throw new InvalidArgumentException('Server capability filter must contain strings');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeUInt32($this->startingRecordId);
        $encoder->writeUInt32($this->maxRecordsToReturn);

        $encoder->writeInt32(count($this->serverCapabilityFilter));
        foreach ($this->serverCapabilityFilter as $capability) {
            $encoder->writeString($capability);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $startingRecordId = $decoder->readUInt32();
        $maxRecordsToReturn = $decoder->readUInt32();

        $filterCount = $decoder->readInt32();
        $serverCapabilityFilter = [];
        for ($i = 0; $i < $filterCount; $i++) {
            $capability = $decoder->readString();
            if ($capability !== null) {
                $serverCapabilityFilter[] = $capability;
            }
        }

        return new self(
            requestHeader: $requestHeader,
            startingRecordId: $startingRecordId,
            maxRecordsToReturn: $maxRecordsToReturn,
            serverCapabilityFilter: $serverCapabilityFilter,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }

    /**
     * Create request for finding servers on network
     *
     * @param int $startingRecordId Starting record ID for paging (0 = from beginning)
     * @param int $maxRecordsToReturn Maximum records to return (0 = no limit)
     * @param string[] $serverCapabilityFilter Filter by capabilities (empty = all servers)
     */
    public static function create(
        int $startingRecordId = 0,
        int $maxRecordsToReturn = 0,
        array $serverCapabilityFilter = [],
        ?RequestHeader $requestHeader = null,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            startingRecordId: $startingRecordId,
            maxRecordsToReturn: $maxRecordsToReturn,
            serverCapabilityFilter: $serverCapabilityFilter,
        );
    }
}
