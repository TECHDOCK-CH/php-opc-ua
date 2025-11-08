<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * DiagnosticInfo provides detailed diagnostic information
 *
 * Encoding mask bits:
 * - 0x01: SymbolicId present
 * - 0x02: NamespaceUri present
 * - 0x04: LocalizedText present
 * - 0x08: Locale present
 * - 0x10: AdditionalInfo present
 * - 0x20: InnerStatusCode present
 * - 0x40: InnerDiagnosticInfo present
 */
final readonly class DiagnosticInfo implements IEncodeable
{
    public function __construct(
        public ?int $symbolicId = null,
        public ?int $namespaceUri = null,
        public ?int $localizedText = null,
        public ?int $locale = null,
        public ?string $additionalInfo = null,
        public ?StatusCode $innerStatusCode = null,
        public ?DiagnosticInfo $innerDiagnosticInfo = null,
    ) {
    }

    /**
     * Create empty DiagnosticInfo
     */
    public static function empty(): self
    {
        return new self();
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Build encoding mask
        $mask = 0;

        if ($this->symbolicId !== null) {
            $mask |= 0x01;
        }
        if ($this->namespaceUri !== null) {
            $mask |= 0x02;
        }
        if ($this->localizedText !== null) {
            $mask |= 0x04;
        }
        if ($this->locale !== null) {
            $mask |= 0x08;
        }
        if ($this->additionalInfo !== null) {
            $mask |= 0x10;
        }
        if ($this->innerStatusCode !== null) {
            $mask |= 0x20;
        }
        if ($this->innerDiagnosticInfo !== null) {
            $mask |= 0x40;
        }

        $encoder->writeByte($mask);

        if ($this->symbolicId !== null) {
            $encoder->writeInt32($this->symbolicId);
        }
        if ($this->namespaceUri !== null) {
            $encoder->writeInt32($this->namespaceUri);
        }
        if ($this->localizedText !== null) {
            $encoder->writeInt32($this->localizedText);
        }
        if ($this->locale !== null) {
            $encoder->writeInt32($this->locale);
        }
        if ($this->additionalInfo !== null) {
            $encoder->writeString($this->additionalInfo);
        }
        if ($this->innerStatusCode !== null) {
            $this->innerStatusCode->encode($encoder);
        }
        if ($this->innerDiagnosticInfo !== null) {
            $this->innerDiagnosticInfo->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $mask = $decoder->readByte();

        $symbolicId = null;
        if (($mask & 0x01) !== 0) {
            $symbolicId = $decoder->readInt32();
        }

        $namespaceUri = null;
        if (($mask & 0x02) !== 0) {
            $namespaceUri = $decoder->readInt32();
        }

        $localizedText = null;
        if (($mask & 0x04) !== 0) {
            $localizedText = $decoder->readInt32();
        }

        $locale = null;
        if (($mask & 0x08) !== 0) {
            $locale = $decoder->readInt32();
        }

        $additionalInfo = null;
        if (($mask & 0x10) !== 0) {
            $additionalInfo = $decoder->readString();
        }

        $innerStatusCode = null;
        if (($mask & 0x20) !== 0) {
            $innerStatusCode = StatusCode::decode($decoder);
        }

        $innerDiagnosticInfo = null;
        if (($mask & 0x40) !== 0) {
            $innerDiagnosticInfo = self::decode($decoder);
        }

        return new self(
            symbolicId: $symbolicId,
            namespaceUri: $namespaceUri,
            localizedText: $localizedText,
            locale: $locale,
            additionalInfo: $additionalInfo,
            innerStatusCode: $innerStatusCode,
            innerDiagnosticInfo: $innerDiagnosticInfo,
        );
    }

    public function toString(): string
    {
        if ($this->additionalInfo !== null) {
            return "DiagnosticInfo({$this->additionalInfo})";
        }

        return 'DiagnosticInfo(empty)';
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
