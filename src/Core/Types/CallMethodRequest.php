<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * CallMethodRequest - request to invoke a single method.
 */
final readonly class CallMethodRequest implements IEncodeable
{
    /**
     * @param Variant[] $inputArguments
     */
    public function __construct(
        public NodeId $objectId,
        public NodeId $methodId,
        public array $inputArguments,
    ) {
    }

    /**
     * Create a method call request.
     *
     * @param Variant[] $inputArguments
     */
    public static function create(
        NodeId $objectId,
        NodeId $methodId,
        array $inputArguments = [],
    ): self {
        foreach ($inputArguments as $arg) {
            if (!$arg instanceof Variant) {
                throw new InvalidArgumentException('inputArguments must only contain Variant instances.');
            }
        }

        return new self(
            objectId: $objectId,
            methodId: $methodId,
            inputArguments: array_values($inputArguments),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->objectId->encode($encoder);
        $this->methodId->encode($encoder);

        $encoder->writeInt32(count($this->inputArguments));
        foreach ($this->inputArguments as $arg) {
            $arg->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $objectId = NodeId::decode($decoder);
        $methodId = NodeId::decode($decoder);

        $count = $decoder->readInt32();
        $inputArguments = [];
        for ($i = 0; $i < $count; $i++) {
            $inputArguments[] = Variant::decode($decoder);
        }

        return new self(
            objectId: $objectId,
            methodId: $methodId,
            inputArguments: $inputArguments,
        );
    }
}
