<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Messages\EndpointDescription;
use TechDock\OpcUa\Core\Messages\GetEndpointsResponse;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityPolicy;
use TechDock\OpcUa\Core\Types\ApplicationDescription;
use TechDock\OpcUa\Core\Types\ApplicationType;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\DiagnosticInfo;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\LocalizedText;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\StatusCode;
use TechDock\OpcUa\Core\Types\UserTokenPolicy;
use TechDock\OpcUa\Core\Types\UserTokenType;
use PHPUnit\Framework\TestCase;

final class GetEndpointsResponseDecodeTest extends TestCase
{
    public function testDecodesEndpointWithSecurityModeBeforePolicy(): void
    {
        $applicationDescription = new ApplicationDescription(
            applicationUri: 'urn:example:opc',
            productUri: 'http://example.com/product',
            applicationName: new LocalizedText('en-US', 'Example OPC UA Server'),
            applicationType: ApplicationType::Server,
            gatewayServerUri: null,
            discoveryProfileUri: null,
            discoveryUrls: ['opc.tcp://example:4840'],
        );

        $encoder = new BinaryEncoder();
        DateTime::now()->encode($encoder);
        $encoder->writeUInt32(42); // requestHandle
        StatusCode::good()->encode($encoder);
        DiagnosticInfo::empty()->encode($encoder);
        $encoder->writeInt32(0); // string table length
        ExtensionObject::empty(NodeId::numeric(0, 0))->encode($encoder);
        $encoder->writeInt32(1); // endpoint count

        $encoder->writeString('opc.tcp://example:4840');
        $applicationDescription->encode($encoder);
        $encoder->writeByteString(null); // serverCertificate
        $encoder->writeInt32(MessageSecurityMode::SignAndEncrypt->value);
        $encoder->writeString(SecurityPolicy::Basic256Sha256->value);
        $encoder->writeInt32(1); // userIdentityTokens count

        $token = new UserTokenPolicy(
            policyId: 'anonymous',
            tokenType: UserTokenType::Anonymous,
            issuedTokenType: null,
            issuerEndpointUrl: null,
            securityPolicyUri: SecurityPolicy::None->value,
        );
        $token->encode($encoder);

        $encoder->writeString('http://opcfoundation.org/UA-Profile/Transport/uatcp-uasc-uabinary');
        $encoder->writeByte(1); // securityLevel

        $binary = $encoder->getBytes();
        $decoder = new BinaryDecoder($binary);

        $response = GetEndpointsResponse::decode($decoder);

        self::assertSame(1, count($response->endpoints));
        $endpoint = $response->endpoints[0];

        self::assertSame('opc.tcp://example:4840', $endpoint->endpointUrl);
        self::assertSame(SecurityPolicy::Basic256Sha256, $endpoint->securityPolicy);
        self::assertSame(MessageSecurityMode::SignAndEncrypt, $endpoint->securityMode);
        self::assertSame(1, count($endpoint->userIdentityTokens));
        self::assertSame('anonymous', $endpoint->userIdentityTokens[0]->policyId);
    }

    public function testEncodesSecurityModeBeforeSecurityPolicy(): void
    {
        $applicationDescription = new ApplicationDescription(
            applicationUri: 'urn:example:opc',
            productUri: 'http://example.com/product',
            applicationName: new LocalizedText('en-US', 'Example OPC UA Server'),
            applicationType: ApplicationType::Server,
            gatewayServerUri: null,
            discoveryProfileUri: null,
            discoveryUrls: [],
        );

        $endpoint = new EndpointDescription(
            endpointUrl: 'opc.tcp://example:4840',
            server: $applicationDescription,
            serverCertificate: null,
            securityPolicy: SecurityPolicy::Basic256Sha256,
            securityMode: MessageSecurityMode::SignAndEncrypt,
            userIdentityTokens: [
                new UserTokenPolicy(
                    policyId: 'anonymous',
                    tokenType: UserTokenType::Anonymous,
                ),
            ],
            transportProfileUri: 'http://opcfoundation.org/UA-Profile/Transport/uatcp-uasc-uabinary',
            securityLevel: 1,
        );

        $encoder = new BinaryEncoder();
        $endpoint->encode($encoder);

        $decoder = new BinaryDecoder($encoder->getBytes());

        self::assertSame('opc.tcp://example:4840', $decoder->readString());
        ApplicationDescription::decode($decoder);
        self::assertNull($decoder->readByteString());
        self::assertSame(MessageSecurityMode::SignAndEncrypt->value, $decoder->readInt32());
        self::assertSame(SecurityPolicy::Basic256Sha256->value, $decoder->readString());
    }
}
