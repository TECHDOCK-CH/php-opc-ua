<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ServerStatusDataType - Server status information
 *
 * Contains information about the current status of the Server.
 */
final readonly class ServerStatusDataType implements IEncodeable
{
    public function __construct(
        public DateTime $startTime,
        public DateTime $currentTime,
        public ServerState $state,
        public BuildInfo $buildInfo,
        public int $secondsTillShutdown,
        public LocalizedText $shutdownReason,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->startTime->encode($encoder);
        $this->currentTime->encode($encoder);
        $encoder->writeInt32($this->state->value);
        $this->buildInfo->encode($encoder);
        $encoder->writeUInt32($this->secondsTillShutdown);
        $this->shutdownReason->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $startTime = DateTime::decode($decoder);
        $currentTime = DateTime::decode($decoder);
        $stateValue = $decoder->readInt32();
        $state = ServerState::from($stateValue);
        $buildInfo = BuildInfo::decode($decoder);
        $secondsTillShutdown = $decoder->readUInt32();
        $shutdownReason = LocalizedText::decode($decoder);

        return new self(
            startTime: $startTime,
            currentTime: $currentTime,
            state: $state,
            buildInfo: $buildInfo,
            secondsTillShutdown: $secondsTillShutdown,
            shutdownReason: $shutdownReason,
        );
    }

    /**
     * Get the TypeId for ServerStatusDataType
     */
    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, 862); // ServerStatusDataType NodeId
    }

    /**
     * Get string representation
     */
    public function toString(): string
    {
        return "ServerStatus(State: {$this->state->name}, {$this->buildInfo})";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
