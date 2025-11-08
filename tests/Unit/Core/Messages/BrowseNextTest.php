<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Messages\BrowseNextRequest;
use TechDock\OpcUa\Core\Messages\BrowseNextResponse;
use TechDock\OpcUa\Core\Messages\BrowseResult;
use TechDock\OpcUa\Core\Messages\RequestHeader;
use TechDock\OpcUa\Core\Messages\ResponseHeader;
use TechDock\OpcUa\Core\Types\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrowseNextRequest::class)]
#[CoversClass(BrowseNextResponse::class)]
final class BrowseNextTest extends TestCase
{
    public function testBrowseNextRequestEncoding(): void
    {
        $continuationPoints = ['cp1', 'cp2', 'cp3'];
        $request = BrowseNextRequest::create($continuationPoints);

        $encoder = new BinaryEncoder();
        $request->encode($encoder);
        $encoded = $encoder->getBytes();

        $decoder = new BinaryDecoder($encoded);
        $decoded = BrowseNextRequest::decode($decoder);

        $this->assertEquals($request->releaseContinuationPoints, $decoded->releaseContinuationPoints);
        $this->assertCount(3, $decoded->continuationPoints);
        $this->assertEquals('cp1', $decoded->continuationPoints[0]);
        $this->assertEquals('cp2', $decoded->continuationPoints[1]);
        $this->assertEquals('cp3', $decoded->continuationPoints[2]);
    }

    public function testBrowseNextRequestWithRelease(): void
    {
        $continuationPoints = ['cp1'];
        $request = BrowseNextRequest::release($continuationPoints);

        $this->assertTrue($request->releaseContinuationPoints);
        $this->assertEquals($continuationPoints, $request->continuationPoints);
    }

    public function testBrowseNextRequestTypeId(): void
    {
        $request = BrowseNextRequest::create(['test']);
        $typeId = $request->getTypeId();

        $this->assertEquals(0, $typeId->namespaceIndex);
        $this->assertEquals(530, $typeId->identifier);
    }

    public function testBrowseNextResponseEncoding(): void
    {
        $statusCode = StatusCode::good();
        $result1 = new BrowseResult($statusCode, 'cp1', []);
        $result2 = new BrowseResult($statusCode, null, []);

        $response = new BrowseNextResponse(
            responseHeader: ResponseHeader::good(1),
            results: [$result1, $result2],
            diagnosticInfos: []
        );

        $encoder = new BinaryEncoder();
        $response->encode($encoder);
        $encoded = $encoder->getBytes();

        $decoder = new BinaryDecoder($encoded);
        $decoded = BrowseNextResponse::decode($decoder);

        $this->assertCount(2, $decoded->results);
        $this->assertEquals('cp1', $decoded->results[0]->continuationPoint);
        $this->assertNull($decoded->results[1]->continuationPoint);
    }

    public function testBrowseNextRequestWithEmptyContinuationPoints(): void
    {
        $request = BrowseNextRequest::create([]);

        $this->assertEmpty($request->continuationPoints);
        $this->assertFalse($request->releaseContinuationPoints);
    }

    public function testBrowseNextRequestCustomRequestHeader(): void
    {
        $requestHeader = RequestHeader::create();
        $request = BrowseNextRequest::create(
            ['test'],
            requestHeader: $requestHeader
        );

        $this->assertSame($requestHeader, $request->requestHeader);
    }

    public function testBrowseNextRoundTrip(): void
    {
        $originalRequest = BrowseNextRequest::create(
            continuationPoints: ['continuation1', 'continuation2'],
            releaseContinuationPoints: false
        );

        $encoder = new BinaryEncoder();
        $originalRequest->encode($encoder);
        $encoded = $encoder->getBytes();

        $decoder = new BinaryDecoder($encoded);
        $decodedRequest = BrowseNextRequest::decode($decoder);

        $this->assertFalse($decodedRequest->releaseContinuationPoints);
        $this->assertCount(2, $decodedRequest->continuationPoints);
        $this->assertEquals('continuation1', $decodedRequest->continuationPoints[0]);
        $this->assertEquals('continuation2', $decodedRequest->continuationPoints[1]);
    }

    public function testBrowseNextReleaseRoundTrip(): void
    {
        $originalRequest = BrowseNextRequest::release(['cp1', 'cp2']);

        $encoder = new BinaryEncoder();
        $originalRequest->encode($encoder);
        $encoded = $encoder->getBytes();

        $decoder = new BinaryDecoder($encoded);
        $decodedRequest = BrowseNextRequest::decode($decoder);

        $this->assertTrue($decodedRequest->releaseContinuationPoints);
        $this->assertCount(2, $decodedRequest->continuationPoints);
    }
}
