<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Encoding;

/**
 * Interface for types that can be encoded/decoded to OPC UA binary format
 */
interface IEncodeable
{
    /**
     * Encode this object to binary format
     *
     * @param BinaryEncoder $encoder The encoder to write to
     */
    public function encode(BinaryEncoder $encoder): void;

    /**
     * Decode an object from binary format
     *
     * @param BinaryDecoder $decoder The decoder to read from
     * @return static The decoded object
     */
    public static function decode(BinaryDecoder $decoder): static;
}
