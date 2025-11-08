<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ContentFilterElement - A single element in a content filter
 *
 * Represents one operation in a filter expression tree.
 * Operands can be LiteralOperand, SimpleAttributeOperand, AttributeOperand, or ElementOperand.
 */
final readonly class ContentFilterElement implements IEncodeable
{
    /**
     * @param FilterOperator $filterOperator The operator to apply
     * @param array<IEncodeable> $filterOperands Operands for the operator
     */
    public function __construct(
        public FilterOperator $filterOperator,
        public array $filterOperands,
    ) {
        foreach ($filterOperands as $operand) {
            if (!$operand instanceof IEncodeable) {
                throw new InvalidArgumentException('Filter operands must implement IEncodeable');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->filterOperator->value);

        // Encode operands as ExtensionObjects
        $encoder->writeInt32(count($this->filterOperands));
        foreach ($this->filterOperands as $operand) {
            // Wrap operand in ExtensionObject
            $this->encodeOperandAsExtensionObject($encoder, $operand);
        }
    }

    private function encodeOperandAsExtensionObject(BinaryEncoder $encoder, IEncodeable $operand): void
    {
        // Determine the TypeId for this operand
        $typeId = match (get_class($operand)) {
            LiteralOperand::class => NodeId::numeric(0, 595), // LiteralOperand
            SimpleAttributeOperand::class => NodeId::numeric(0, 601), // SimpleAttributeOperand
            AttributeOperand::class => NodeId::numeric(0, 598), // AttributeOperand
            ElementOperand::class => NodeId::numeric(0, 592), // ElementOperand
            default => throw new RuntimeException('Unknown operand type: ' . get_class($operand)),
        };

        // Encode as ExtensionObject with binary body
        $typeId->encode($encoder);
        $encoder->writeByte(1); // Encoding: ByteString body

        // Encode the operand to a temporary encoder
        $bodyEncoder = new BinaryEncoder();
        $operand->encode($bodyEncoder);
        $bodyBytes = $bodyEncoder->getBytes();

        // Write body as ByteString
        $encoder->writeInt32(strlen($bodyBytes));
        $encoder->writeBytes($bodyBytes);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $filterOperator = FilterOperator::from($decoder->readUInt32());

        // Decode operands
        $operandCount = $decoder->readInt32();
        $filterOperands = [];

        for ($i = 0; $i < $operandCount; $i++) {
            $filterOperands[] = self::decodeOperandFromExtensionObject($decoder);
        }

        return new self(
            filterOperator: $filterOperator,
            filterOperands: $filterOperands,
        );
    }

    private static function decodeOperandFromExtensionObject(BinaryDecoder $decoder): IEncodeable
    {
        $typeId = NodeId::decode($decoder);
        $encoding = $decoder->readByte();

        if ($encoding === 0) {
            // No body
            throw new RuntimeException('Extension object has no body');
        }

        if ($encoding !== 1) {
            throw new RuntimeException("Unsupported extension object encoding: {$encoding}");
        }

        // Read body
        $bodyLength = $decoder->readInt32();
        $bodyBytes = $decoder->readBytes($bodyLength);
        $bodyDecoder = new BinaryDecoder($bodyBytes);

        // Decode based on TypeId
        $operandClass = match ($typeId->toString()) {
            'ns=0;i=595' => LiteralOperand::class,
            'ns=0;i=601' => SimpleAttributeOperand::class,
            'ns=0;i=598' => AttributeOperand::class,
            'ns=0;i=592' => ElementOperand::class,
            default => throw new RuntimeException("Unknown operand TypeId: {$typeId}"),
        };

        return $operandClass::decode($bodyDecoder);
    }

    /**
     * Create an Equals comparison
     */
    public static function equals(IEncodeable $left, IEncodeable $right): self
    {
        return new self(FilterOperator::Equals, [$left, $right]);
    }

    /**
     * Create a GreaterThan comparison
     */
    public static function greaterThan(IEncodeable $left, IEncodeable $right): self
    {
        return new self(FilterOperator::GreaterThan, [$left, $right]);
    }

    /**
     * Create a LessThan comparison
     */
    public static function lessThan(IEncodeable $left, IEncodeable $right): self
    {
        return new self(FilterOperator::LessThan, [$left, $right]);
    }

    /**
     * Create a GreaterThanOrEqual comparison
     */
    public static function greaterThanOrEqual(IEncodeable $left, IEncodeable $right): self
    {
        return new self(FilterOperator::GreaterThanOrEqual, [$left, $right]);
    }

    /**
     * Create a LessThanOrEqual comparison
     */
    public static function lessThanOrEqual(IEncodeable $left, IEncodeable $right): self
    {
        return new self(FilterOperator::LessThanOrEqual, [$left, $right]);
    }

    /**
     * Create an AND operation
     */
    public static function and(ElementOperand ...$elements): self
    {
        return new self(FilterOperator::And, $elements);
    }

    /**
     * Create an OR operation
     */
    public static function or(ElementOperand ...$elements): self
    {
        return new self(FilterOperator::Or, $elements);
    }
}
