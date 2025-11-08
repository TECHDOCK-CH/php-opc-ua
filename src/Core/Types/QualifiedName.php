<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * QualifiedName consists of a namespace index and a name
 * Used for browse names and other textual identifiers
 */
final readonly class QualifiedName implements IEncodeable
{
    public function __construct(
        public int $namespaceIndex,
        public string $name,
    ) {
        if ($namespaceIndex < 0 || $namespaceIndex > 65535) {
            throw new InvalidArgumentException("Namespace index must be between 0 and 65535, got {$namespaceIndex}");
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt16($this->namespaceIndex);
        $encoder->writeString($this->name);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $namespaceIndex = $decoder->readUInt16();
        $name = $decoder->readString() ?? '';

        return new self($namespaceIndex, $name);
    }

    public function toString(): string
    {
        if ($this->namespaceIndex === 0) {
            return $this->name;
        }

        return "{$this->namespaceIndex}:{$this->name}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->namespaceIndex === $other->namespaceIndex
            && $this->name === $other->name;
    }
}
