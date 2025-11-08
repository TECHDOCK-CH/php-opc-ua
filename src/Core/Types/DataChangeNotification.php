<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * DataChangeNotification - notification containing data changes for monitored items.
 */
final readonly class DataChangeNotification implements IEncodeable
{
    private const int TYPE_ID = 811;

    /**
     * @param MonitoredItemNotification[] $monitoredItems
     * @param DiagnosticInfo[] $diagnosticInfos
     */
    public function __construct(
        public array $monitoredItems,
        public array $diagnosticInfos,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeInt32(count($this->monitoredItems));
        foreach ($this->monitoredItems as $item) {
            $item->encode($encoder);
        }

        $encoder->writeInt32(count($this->diagnosticInfos));
        foreach ($this->diagnosticInfos as $diagnostic) {
            $diagnostic->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $count = $decoder->readInt32();
        $monitoredItems = [];
        for ($i = 0; $i < $count; $i++) {
            $monitoredItems[] = MonitoredItemNotification::decode($decoder);
        }

        $diagnosticCount = $decoder->readInt32();
        $diagnosticInfos = [];
        for ($i = 0; $i < $diagnosticCount; $i++) {
            $diagnosticInfos[] = DiagnosticInfo::decode($decoder);
        }

        return new self(
            monitoredItems: $monitoredItems,
            diagnosticInfos: $diagnosticInfos,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
