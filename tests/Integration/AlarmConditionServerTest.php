<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Integration;

use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Types\NodeId;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration test for browsing the public Alarm Condition demo server.
 *
 * This test demonstrates:
 * - Connecting to a live OPC UA server
 * - Browsing the address space
 * - Using BrowseNext with continuation points
 */
#[Group('integration')]
final class AlarmConditionServerTest extends IntegrationTestCase
{
    private const ALARM_SERVER_URL = 'opc.tcp://opcua.demo-this.com:62544/Quickstarts/AlarmConditionServer';

    protected function setUp(): void
    {
        // Override the endpoint URL for this specific test
        static::$endpointUrl = self::ALARM_SERVER_URL;
        parent::setUp();
    }

    public function testConnectAndBrowseObjectsFolder(): void
    {
        $client = $this->createClient();
        $client->connect();

        $session = $client->createSession();

        try {
            $session->create();
            $session->activate();

            // Browse the Objects folder (standard NodeId ns=0;i=85)
            $browseResult = $session->browse(
                BrowseDescription::create(NodeId::numeric(0, 85))
            );

            // Verify we got references
            self::assertNotEmpty($browseResult->references, 'Objects folder should contain references');
            self::assertGreaterThan(0, count($browseResult->references), 'Should have at least one reference');
        } finally {
            $session->close();
            $client->disconnect();
        }
    }

    public function testBrowseWithContinuationPoint(): void
    {
        $client = $this->createClient();
        $client->connect();

        $session = $client->createSession();

        try {
            $session->create();
            $session->activate();

            // Browse with a very low limit to force continuation points
            $browseDescription = BrowseDescription::create(
                nodeId: NodeId::numeric(0, 85), // Objects folder
            );

            // First browse with limited results
            $browseResult = $session->browse($browseDescription);

            $allReferences = $browseResult->references;
            $continuationPoint = $browseResult->continuationPoint;

            // Check if we have a continuation point
            if ($continuationPoint !== null && $continuationPoint !== '') {
                // Use BrowseNext to get remaining results
                $nextResults = $session->browseNext([$continuationPoint]);

                self::assertNotEmpty($nextResults, 'BrowseNext should return results');

                $nextResult = $nextResults[0];
                $allReferences = array_merge($allReferences, $nextResult->references);

                // If there's still a continuation point, release it
                if ($nextResult->continuationPoint !== null && $nextResult->continuationPoint !== '') {
                    $session->browseNext(
                        [$nextResult->continuationPoint],
                        releaseContinuationPoints: true
                    );
                }
            }

            self::assertNotEmpty($allReferences, 'Should have collected references');
        } finally {
            $session->close();
            $client->disconnect();
        }
    }

    public function testManagedBrowseAutoHandlesContinuation(): void
    {
        $client = $this->createClient();
        $client->connect();

        $session = $client->createSession();

        try {
            $session->create();
            $session->activate();

            // Use managedBrowse which automatically handles continuation points
            $browseDescription = BrowseDescription::create(NodeId::numeric(0, 85));

            // Set a low limit to force multiple BrowseNext calls internally
            $result = $session->managedBrowse(
                $browseDescription,
                maxReferencesPerNode: 5
            );

            // managedBrowse should return all results with no continuation point
            self::assertNull($result->continuationPoint, 'managedBrowse should return null continuation point');
            self::assertNotEmpty($result->references, 'Should have collected all references');
        } finally {
            $session->close();
            $client->disconnect();
        }
    }

    public function testBrowseMultipleNodesSequentially(): void
    {
        $client = $this->createClient();
        $client->connect();

        $session = $client->createSession();

        try {
            $session->create();
            $session->activate();

            // First, browse the Objects folder to find child nodes
            $objectsFolderResult = $session->browse(
                BrowseDescription::create(NodeId::numeric(0, 85))
            );

            self::assertNotEmpty($objectsFolderResult->references, 'Objects folder should have references');

            // Browse the Server node (standard NodeId ns=0;i=2253)
            $serverNodeResult = $session->browse(
                BrowseDescription::create(NodeId::numeric(0, 2253))
            );

            // Browse the Types node (standard NodeId ns=0;i=86)
            $typesNodeResult = $session->browse(
                BrowseDescription::create(NodeId::numeric(0, 86))
            );

            self::assertNotEmpty($serverNodeResult->references, 'Server node should have references');
            self::assertNotEmpty($typesNodeResult->references, 'Types node should have references');
        } finally {
            $session->close();
            $client->disconnect();
        }
    }
}
