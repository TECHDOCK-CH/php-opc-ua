<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ServerOnNetwork - Describes a server discovered on the network
 *
 * Contains information about servers discovered via multicast or local discovery.
 */
final readonly class ServerOnNetwork implements IEncodeable
{
    /**
     * @param int $recordId Unique identifier for this server record
     * @param string $serverName Server name (may be hostname or descriptive name)
     * @param string $discoveryUrl URL used to connect to the server's discovery endpoint
     * @param string[] $serverCapabilities Server capabilities (e.g., ['DA', 'HD', 'AC'])
     */
    public function __construct(
        public int $recordId,
        public string $serverName,
        public string $discoveryUrl,
        public array $serverCapabilities,
    ) {
        foreach ($serverCapabilities as $capability) {
            if (!is_string($capability)) {
                throw new InvalidArgumentException('Server capabilities must be strings');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->recordId);
        $encoder->writeString($this->serverName);
        $encoder->writeString($this->discoveryUrl);

        $encoder->writeInt32(count($this->serverCapabilities));
        foreach ($this->serverCapabilities as $capability) {
            $encoder->writeString($capability);
        }
    }

    public static function decode(BinaryDecoder $decoder): self
    {
        $recordId = $decoder->readUInt32();
        $serverName = $decoder->readString();
        $discoveryUrl = $decoder->readString();

        $capabilityCount = $decoder->readInt32();
        $serverCapabilities = [];
        for ($i = 0; $i < $capabilityCount; $i++) {
            $capability = $decoder->readString();
            if ($capability !== null) {
                $serverCapabilities[] = $capability;
            }
        }

        if ($serverName === null) {
            throw new RuntimeException('Server name cannot be null');
        }

        if ($discoveryUrl === null) {
            throw new RuntimeException('Discovery URL cannot be null');
        }

        return new self(
            recordId: $recordId,
            serverName: $serverName,
            discoveryUrl: $discoveryUrl,
            serverCapabilities: $serverCapabilities,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, 12189);
    }
}
