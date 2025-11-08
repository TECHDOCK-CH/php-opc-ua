<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * LocalizedText contains text in a specific locale
 *
 * Encoding mask bits:
 * - 0x01: Locale is specified
 * - 0x02: Text is specified
 */
final readonly class LocalizedText implements IEncodeable
{
    public function __construct(
        public ?string $locale,
        public ?string $text,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encodingMask = 0;

        if ($this->locale !== null) {
            $encodingMask |= 0x01;
        }

        if ($this->text !== null) {
            $encodingMask |= 0x02;
        }

        $encoder->writeByte($encodingMask);

        if ($this->locale !== null) {
            $encoder->writeString($this->locale);
        }

        if ($this->text !== null) {
            $encoder->writeString($this->text);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $encodingMask = $decoder->readByte();

        $locale = null;
        if (($encodingMask & 0x01) !== 0) {
            $locale = $decoder->readString();
        }

        $text = null;
        if (($encodingMask & 0x02) !== 0) {
            $text = $decoder->readString();
        }

        return new self($locale, $text);
    }

    /**
     * Get string representation
     */
    public function toString(): string
    {
        if ($this->locale !== null && $this->text !== null) {
            return "{$this->locale}: {$this->text}";
        }

        if ($this->text !== null) {
            return $this->text;
        }

        if ($this->locale !== null) {
            return "[{$this->locale}]";
        }

        return '';
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Check if two LocalizedText values are equal
     */
    public function equals(self $other): bool
    {
        return $this->locale === $other->locale
            && $this->text === $other->text;
    }
}
