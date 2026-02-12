<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\AnonymousIdentityToken;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\SignatureData;
use TechDock\OpcUa\Core\Types\SignedSoftwareCertificate;
use TechDock\OpcUa\Core\Types\UserNameIdentityToken;
use TechDock\OpcUa\Core\Types\X509IdentityToken;

/**
 * ActivateSessionRequest - Activates a session
 */
final readonly class ActivateSessionRequest implements IEncodeable, ServiceRequest
{
    private const int TYPE_ID = 467;

    public function __construct(
        public RequestHeader $requestHeader,
        public SignatureData $clientSignature,
        /** @var SignedSoftwareCertificate[] */
        public array $clientSoftwareCertificates,
        /** @var string[] */
        public array $localeIds,
        public ExtensionObject $userIdentityToken,
        public SignatureData $userTokenSignature,
    ) {
    }

    /**
     * Create an ActivateSessionRequest with anonymous authentication
     *
     * @param string[] $localeIds
     */
    public static function anonymous(
        RequestHeader $requestHeader,
        string $policyId,
        array $localeIds = ['en'],
    ): self {
        // Anonymous user identity token (NodeId for AnonymousIdentityToken = 321)
        $userIdentityToken = ExtensionObject::fromEncodeable(
            NodeId::numeric(0, 321),
            new AnonymousIdentityToken($policyId),
        );

        return new self(
            requestHeader: $requestHeader,
            clientSignature: new SignatureData(null, null),
            clientSoftwareCertificates: [],
            localeIds: $localeIds,
            userIdentityToken: $userIdentityToken,
            userTokenSignature: new SignatureData(null, null),
        );
    }

    /**
     * Create an ActivateSessionRequest with a specific user identity
     *
     * @param string[] $localeIds
     */
    public static function withIdentity(
        RequestHeader $requestHeader,
        AnonymousIdentityToken|UserNameIdentityToken|X509IdentityToken $identityToken,
        array $localeIds = ['en'],
    ): self {
        // Determine TypeId based on token type
        $typeId = match (true) {
            $identityToken instanceof AnonymousIdentityToken => NodeId::numeric(0, 321),
            $identityToken instanceof UserNameIdentityToken => NodeId::numeric(0, 324),
            $identityToken instanceof X509IdentityToken => NodeId::numeric(0, 327),
        };

        $userIdentityToken = ExtensionObject::fromEncodeable($typeId, $identityToken);

        return new self(
            requestHeader: $requestHeader,
            clientSignature: new SignatureData(null, null),
            clientSoftwareCertificates: [],
            localeIds: $localeIds,
            userIdentityToken: $userIdentityToken,
            userTokenSignature: new SignatureData(null, null),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $this->clientSignature->encode($encoder);

        // Client software certificates
        $encoder->writeInt32(count($this->clientSoftwareCertificates));
        foreach ($this->clientSoftwareCertificates as $cert) {
            $cert->encode($encoder);
        }

        // Locale IDs
        $encoder->writeInt32(count($this->localeIds));
        foreach ($this->localeIds as $localeId) {
            $encoder->writeString($localeId);
        }

        $this->userIdentityToken->encode($encoder);
        $this->userTokenSignature->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $clientSignature = SignatureData::decode($decoder);

        // Client software certificates
        $certCount = $decoder->readInt32();
        $clientSoftwareCertificates = [];
        for ($i = 0; $i < $certCount; $i++) {
            $clientSoftwareCertificates[] = SignedSoftwareCertificate::decode($decoder);
        }

        // Locale IDs
        $localeCount = $decoder->readInt32();
        $localeIds = [];
        for ($i = 0; $i < $localeCount; $i++) {
            $localeId = $decoder->readString();
            if ($localeId !== null) {
                $localeIds[] = $localeId;
            }
        }

        $userIdentityToken = ExtensionObject::decode($decoder);
        $userTokenSignature = SignatureData::decode($decoder);

        return new self(
            requestHeader: $requestHeader,
            clientSignature: $clientSignature,
            clientSoftwareCertificates: $clientSoftwareCertificates,
            localeIds: $localeIds,
            userIdentityToken: $userIdentityToken,
            userTokenSignature: $userTokenSignature,
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
