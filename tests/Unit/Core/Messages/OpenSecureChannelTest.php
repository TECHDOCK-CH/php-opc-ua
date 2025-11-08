<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Messages\OpenSecureChannelRequest;
use TechDock\OpcUa\Core\Messages\OpenSecureChannelResponse;
use TechDock\OpcUa\Core\Messages\ResponseHeader;
use TechDock\OpcUa\Core\Security\ChannelSecurityToken;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityTokenRequestType;
use TechDock\OpcUa\Core\Types\DateTime;
use PHPUnit\Framework\TestCase;

final class OpenSecureChannelTest extends TestCase
{
    public function testIssueRequest(): void
    {
        $request = OpenSecureChannelRequest::issue(
            securityMode: MessageSecurityMode::None,
        );

        $this->assertSame(SecurityTokenRequestType::Issue, $request->requestType);
        $this->assertSame(MessageSecurityMode::None, $request->securityMode);
        $this->assertSame(600000, $request->requestedLifetime);
    }

    public function testRenewRequest(): void
    {
        $request = OpenSecureChannelRequest::renew();

        $this->assertSame(SecurityTokenRequestType::Renew, $request->requestType);
    }

    public function testRequestEncodeDecode(): void
    {
        $request = OpenSecureChannelRequest::issue(
            securityMode: MessageSecurityMode::SignAndEncrypt,
            clientNonce: 'test-nonce',
            requestedLifetime: 300000,
        );

        $encoder = new BinaryEncoder();
        $request->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = OpenSecureChannelRequest::decode($decoder);

        $this->assertSame($request->clientProtocolVersion, $decoded->clientProtocolVersion);
        $this->assertSame($request->requestType, $decoded->requestType);
        $this->assertSame($request->securityMode, $decoded->securityMode);
        $this->assertSame($request->clientNonce, $decoded->clientNonce);
        $this->assertSame($request->requestedLifetime, $decoded->requestedLifetime);
    }

    public function testResponseEncodeDecode(): void
    {
        $response = new OpenSecureChannelResponse(
            responseHeader: ResponseHeader::good(1),
            serverProtocolVersion: 0,
            securityToken: new ChannelSecurityToken(
                channelId: 123,
                tokenId: 456,
                createdAt: DateTime::now(),
                revisedLifetime: 600000,
            ),
            serverNonce: 'server-nonce',
        );

        $encoder = new BinaryEncoder();
        $response->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = OpenSecureChannelResponse::decode($decoder);

        $this->assertSame($response->serverProtocolVersion, $decoded->serverProtocolVersion);
        $this->assertSame($response->securityToken->channelId, $decoded->securityToken->channelId);
        $this->assertSame($response->securityToken->tokenId, $decoded->securityToken->tokenId);
        $this->assertSame($response->serverNonce, $decoded->serverNonce);
    }
}
