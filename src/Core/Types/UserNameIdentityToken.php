<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use Exception;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Security\RsaCrypto;
use TechDock\OpcUa\Core\Security\RsaPadding;
use TechDock\OpcUa\Core\Security\SecurityPolicy;

/**
 * Username/Password identity token
 *
 * OPC UA Part 4 - Section 7.36.4
 */
final class UserNameIdentityToken implements IEncodeable
{
    private const ENCRYPTION_ALGORITHM_RSA_OAEP = 'http://www.w3.org/2001/04/xmlenc#rsa-oaep';
    private const ENCRYPTION_ALGORITHM_RSA_15 = 'http://www.w3.org/2001/04/xmlenc#rsa-1_5';

    public function __construct(
        public readonly string $policyId,
        public readonly string $userName,
        /** @var string|null Encrypted password bytes (after calling encrypt()) */
        public ?string $password = null,
        public ?string $encryptionAlgorithm = null,
        /** @var string|null Unencrypted password bytes (before calling encrypt()) */
        private ?string $decryptedPassword = null,
    ) {
    }

    /**
     * Create a token with unencrypted password
     *
     * Call encrypt() before sending to server
     */
    public static function create(
        string $policyId,
        string $userName,
        string $password
    ): self {
        return new self(
            policyId: $policyId,
            userName: $userName,
            password: null,
            encryptionAlgorithm: null,
            decryptedPassword: $password,
        );
    }

    /**
     * Encrypt the password using server certificate
     *
     * OPC UA Part 4 - Section 7.36.4:
     * "The password shall be encrypted using the EncryptionAlgorithm specified in the UserTokenPolicy.
     * The password shall be converted to a UTF-8 ByteString, appended with the serverNonce and
     * then encrypted with the Server's public key."
     *
     * @param string $serverCertificate PEM-encoded X.509 certificate
     * @param string|null $serverNonce Server nonce from CreateSessionResponse
     * @param SecurityPolicy $securityPolicy Security policy to use for encryption
     * @throws RuntimeException
     */
    public function encrypt(
        string $serverCertificate,
        ?string $serverNonce,
        SecurityPolicy $securityPolicy
    ): void {
        if ($this->decryptedPassword === null) {
            throw new RuntimeException('Cannot encrypt: decrypted password not set');
        }

        // For SecurityPolicy::None, don't encrypt
        if ($securityPolicy === SecurityPolicy::None) {
            $this->password = $this->decryptedPassword;
            $this->encryptionAlgorithm = null;
            return;
        }

        // Create length-prefixed data structure per OPC UA Part 4 Section 7.36.2.2
        // Legacy Encrypted Token Secret Format: [UInt32 length][password bytes][server nonce]
        // where length = sizeof(password) + sizeof(nonce), excluding the 4-byte length field itself
        $passwordBytes = $this->decryptedPassword;
        $nonceBytes = $serverNonce ?? '';
        $totalLength = strlen($passwordBytes) + strlen($nonceBytes);

        $encoder = new BinaryEncoder();
        $encoder->writeInt32($totalLength);  // 4-byte length prefix (little-endian)
        $dataToEncrypt = $encoder->getBytes() . $passwordBytes . $nonceBytes;

        // Determine padding and encryption algorithm based on security policy
        [$padding, $algorithmUri] = $this->getEncryptionParameters($securityPolicy);

        // Encrypt the data
        try {
            $this->password = RsaCrypto::encrypt(
                $dataToEncrypt,
                $serverCertificate,
                $padding
            );
            $this->encryptionAlgorithm = $algorithmUri;
        } catch (Exception $e) {
            throw new RuntimeException(
                "Failed to encrypt password: {$e->getMessage()}",
                0,
                $e
            );
        } finally {
            // Zero out the plaintext password for security
            if ($dataToEncrypt !== $this->decryptedPassword) {
                sodium_memzero($dataToEncrypt);
            }
        }
    }

    /**
     * Decrypt the password using private key (server-side operation)
     *
     * @param string $privateKey PEM-encoded private key
     * @param string|null $serverNonce Server nonce that was used during encryption
     * @param string|null $password Password for encrypted private key
     * @return string Decrypted password
     * @throws RuntimeException
     */
    public function decrypt(
        string $privateKey,
        ?string $serverNonce,
        ?string $password = null
    ): string {
        if ($this->password === null) {
            throw new RuntimeException('Cannot decrypt: password not set');
        }

        // If no encryption was used, return password as-is
        if ($this->encryptionAlgorithm === null || $this->encryptionAlgorithm === '') {
            return $this->password;
        }

        // Determine padding based on encryption algorithm
        $padding = $this->encryptionAlgorithm === self::ENCRYPTION_ALGORITHM_RSA_OAEP
            ? RsaPadding::OAEP
            : RsaPadding::PKCS1;

        // Decrypt
        try {
            $decrypted = RsaCrypto::decrypt(
                $this->password,
                $privateKey,
                $padding,
                $password
            );
        } catch (Exception $e) {
            throw new RuntimeException(
                "Failed to decrypt password: {$e->getMessage()}",
                0,
                $e
            );
        }

        // Parse length-prefixed format per OPC UA Part 4 Section 7.36.2.2
        // Format: [UInt32 length][password bytes][server nonce]
        if (strlen($decrypted) < 4) {
            throw new RuntimeException('Decrypted data is too short to contain length prefix');
        }

        $decoder = new BinaryDecoder($decrypted);
        $declaredLength = $decoder->readInt32();
        $actualLength = strlen($decrypted) - 4; // Subtract length field itself

        if ($declaredLength !== $actualLength) {
            throw new RuntimeException(
                "Password length mismatch: declared=$declaredLength, actual=$actualLength"
            );
        }

        // Extract password and nonce from the remaining bytes
        $dataBytes = $decoder->readBytes($declaredLength);

        // Remove server nonce from the end if present
        if ($serverNonce !== null && $serverNonce !== '') {
            $nonceLength = strlen($serverNonce);
            $passwordLength = strlen($dataBytes) - $nonceLength;

            if ($passwordLength < 0) {
                throw new RuntimeException('Decrypted data is shorter than server nonce');
            }

            // Verify nonce matches
            $extractedNonce = substr($dataBytes, $passwordLength);
            if (!hash_equals($serverNonce, $extractedNonce)) {
                throw new RuntimeException('Server nonce verification failed');
            }

            return substr($dataBytes, 0, $passwordLength);
        }

        return $dataBytes;
    }

    /**
     * Get encryption parameters for a security policy
     *
     * @return array{RsaPadding, string|null} [padding, algorithmUri]
     */
    private function getEncryptionParameters(SecurityPolicy $securityPolicy): array
    {
        return match ($securityPolicy) {
            SecurityPolicy::Basic256Sha256,
            SecurityPolicy::Aes128Sha256RsaOaep,
            SecurityPolicy::Aes256Sha256RsaPss => [
                RsaPadding::OAEP,
                self::ENCRYPTION_ALGORITHM_RSA_OAEP
            ],
            SecurityPolicy::Basic128Rsa15,
            SecurityPolicy::Basic256 => [
                RsaPadding::PKCS1,
                self::ENCRYPTION_ALGORITHM_RSA_15
            ],
            SecurityPolicy::None => [
                RsaPadding::PKCS1,
                null
            ],
        };
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, 324); // UserNameIdentityToken
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // TypeId is encoded by caller (ExtensionObject)

        // PolicyId
        $encoder->writeString($this->policyId);

        // UserName
        $encoder->writeString($this->userName);

        // Password (ByteString - encrypted or plaintext depending on context)
        if ($this->password !== null) {
            $encoder->writeByteString($this->password);
        } else {
            $encoder->writeByteString(null);
        }

        // EncryptionAlgorithm
        $encoder->writeString($this->encryptionAlgorithm);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        // PolicyId
        $policyId = $decoder->readString();

        // UserName
        $userName = $decoder->readString();

        // Password
        $password = $decoder->readByteString();

        // EncryptionAlgorithm
        $encryptionAlgorithm = $decoder->readString();

        return new self(
            policyId: $policyId ?? '',
            userName: $userName ?? '',
            password: $password,
            encryptionAlgorithm: $encryptionAlgorithm,
        );
    }

    /**
     * Clear sensitive data from memory
     */
    public function __destruct()
    {
        if ($this->decryptedPassword !== null) {
            sodium_memzero($this->decryptedPassword);
        }
        if ($this->password !== null) {
            sodium_memzero($this->password);
        }
    }
}
