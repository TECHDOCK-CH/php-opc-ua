<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\CallMethodRequest;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * CallRequest - invokes one or more methods.
 */
final readonly class CallRequest implements ServiceRequest
{
    private const int TYPE_ID = 712;

    /**
     * @param CallMethodRequest[] $methodsToCall
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public array $methodsToCall,
    ) {
    }

    /**
     * Create a call request with defaults.
     *
     * @param CallMethodRequest[] $methodsToCall
     */
    public static function create(
        array $methodsToCall,
        ?RequestHeader $requestHeader = null,
    ): self {
        if ($methodsToCall === []) {
            throw new InvalidArgumentException('CallRequest requires at least one CallMethodRequest.');
        }

        foreach ($methodsToCall as $method) {
            if (!$method instanceof CallMethodRequest) {
                throw new InvalidArgumentException('methodsToCall must only contain CallMethodRequest instances.');
            }
        }

        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            methodsToCall: array_values($methodsToCall),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);

        $encoder->writeInt32(count($this->methodsToCall));
        foreach ($this->methodsToCall as $method) {
            $method->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);

        $count = $decoder->readInt32();
        $methodsToCall = [];
        for ($i = 0; $i < $count; $i++) {
            $methodsToCall[] = CallMethodRequest::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            methodsToCall: $methodsToCall,
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
}
