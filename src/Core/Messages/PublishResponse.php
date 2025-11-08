<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\DiagnosticInfo;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\NotificationMessage;
use TechDock\OpcUa\Core\Types\StatusCode;

/**
 * PublishResponse - contains notification messages from subscriptions.
 */
final readonly class PublishResponse implements ServiceResponse
{
    private const int TYPE_ID = 829;

    /**
     * @param int[] $availableSequenceNumbers
     * @param StatusCode[] $results
     * @param DiagnosticInfo[] $diagnosticInfos
     */
    public function __construct(
        public ResponseHeader $responseHeader,
        public int $subscriptionId,
        public array $availableSequenceNumbers,
        public bool $moreNotifications,
        public NotificationMessage $notificationMessage,
        public array $results,
        public array $diagnosticInfos,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);
        $encoder->writeUInt32($this->subscriptionId);

        $encoder->writeInt32(count($this->availableSequenceNumbers));
        foreach ($this->availableSequenceNumbers as $seqNum) {
            $encoder->writeUInt32($seqNum);
        }

        $encoder->writeBoolean($this->moreNotifications);
        $this->notificationMessage->encode($encoder);

        $encoder->writeInt32(count($this->results));
        foreach ($this->results as $result) {
            $result->encode($encoder);
        }

        $encoder->writeInt32(count($this->diagnosticInfos));
        foreach ($this->diagnosticInfos as $diagnostic) {
            $diagnostic->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);
        $subscriptionId = $decoder->readUInt32();

        $seqCount = $decoder->readInt32();
        $availableSequenceNumbers = [];
        for ($i = 0; $i < $seqCount; $i++) {
            $availableSequenceNumbers[] = $decoder->readUInt32();
        }

        $moreNotifications = $decoder->readBoolean();
        $notificationMessage = NotificationMessage::decode($decoder);

        $resultCount = $decoder->readInt32();
        $results = [];
        for ($i = 0; $i < $resultCount; $i++) {
            $results[] = StatusCode::decode($decoder);
        }

        $diagnosticCount = $decoder->readInt32();
        $diagnosticInfos = [];
        for ($i = 0; $i < $diagnosticCount; $i++) {
            $diagnosticInfos[] = DiagnosticInfo::decode($decoder);
        }

        return new self(
            responseHeader: $responseHeader,
            subscriptionId: $subscriptionId,
            availableSequenceNumbers: $availableSequenceNumbers,
            moreNotifications: $moreNotifications,
            notificationMessage: $notificationMessage,
            results: $results,
            diagnosticInfos: $diagnosticInfos,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
