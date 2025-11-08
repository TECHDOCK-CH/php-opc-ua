<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Integration;

use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Types\DataValue;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\Variant;
use TechDock\OpcUa\Core\Types\WriteValue;
use PHPUnit\Framework\Attributes\Group;
use Throwable;

#[Group('integration')]
final class SessionIntegrationTest extends IntegrationTestCase
{
    public function testBrowseObjectsFolderIncludesOpcPlcNode(): void
    {
        $client = $this->createClient();
        $client->connect();

        $session = $client->createSession();

        try {
            $session->create();
            $session->activate();

            $browseResult = $session->browse(BrowseDescription::create(NodeId::numeric(0, 85))); // Objects folder

            $names = [];
            foreach ($browseResult->references as $reference) {
                $names[] = $reference->displayName->text ?? $reference->browseName->name ?? '';
            }

            self::assertContains('OpcPlc', $names, 'Objects folder should expose the OpcPlc simulation namespace.');
        } finally {
            $session->close();
            $client->disconnect();
        }
    }

    public function testReadStandardAndTelemetryValues(): void
    {
        $client = $this->createClient();
        $client->connect();
        $session = $client->createSession();

        try {
            $session->create();
            $session->activate();

            $results = $session->read([
                NodeId::numeric(0, 2258),               // Server_ServerStatus_CurrentTime
                NodeId::string(3, 'RandomUnsignedInt32'), // Telemetry random signal
            ]);

            self::assertCount(2, $results);
            self::assertInstanceOf(DateTime::class, $results[0]->value->value);
            self::assertIsInt($results[1]->value->value);
        } finally {
            $session->close();
            $client->disconnect();
        }
    }

    public function testWriteSimulatorConfigurationValue(): void
    {
        $client = $this->createClient();
        $client->connect();
        $session = $client->createSession();

        $targetNode = NodeId::string(3, 'SlowNumberOfUpdates');

        try {
            $session->create();
            $session->activate();

            $original = $session->read([$targetNode])[0]->value->value;
            self::assertIsInt($original);

            $newValue = ($original === 5) ? 6 : 5;

            $session->write([
                WriteValue::forValue(
                    nodeId: $targetNode,
                    value: DataValue::fromVariant(Variant::int32($newValue)),
                ),
            ]);

            $updated = $session->read([$targetNode])[0]->value->value;
            self::assertSame($newValue, $updated, 'Simulator configuration should reflect the written value.');
        } finally {
            try {
                if (isset($original)) {
                    $session->write([
                        WriteValue::forValue(
                            nodeId: $targetNode,
                            value: DataValue::fromVariant(Variant::int32($original)),
                        ),
                    ]);
                }
            } catch (Throwable $e) {
                // Best-effort restore; do not fail test teardown.
            }

            $session->close();
            $client->disconnect();
        }
    }

    public function testCallResetStepUpMethod(): void
    {
        $client = $this->createClient();
        $client->connect();
        $session = $client->createSession();

        try {
            $session->create();
            $session->activate();

            $outputs = $session->callMethod(
                objectId: NodeId::string(3, 'Methods'),
                methodId: NodeId::string(3, 'ResetStepUp'),
            );

            self::assertSame([], $outputs, 'ResetStepUp method should not return output arguments.');
        } finally {
            $session->close();
            $client->disconnect();
        }
    }
}
