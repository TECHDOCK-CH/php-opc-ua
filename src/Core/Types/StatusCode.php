<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * OPC UA Status Code (32-bit unsigned integer)
 *
 * Bits 0-15: Status code
 * Bits 16-27: Sub-code
 * Bits 28-29: Reserved
 * Bits 30-31: Severity (00=Good, 01=Uncertain, 10/11=Bad)
 */
final readonly class StatusCode implements IEncodeable
{
    // Common status codes
    public const int GOOD = 0x00000000;
    public const int UNCERTAIN = 0x40000000;
    public const int BAD = 0x80000000;

    // Specific status codes
    public const int BAD_UNEXPECTED_ERROR = 0x80010000;
    public const int BAD_INTERNAL_ERROR = 0x80020000;
    public const int BAD_OUT_OF_MEMORY = 0x80030000;
    public const int BAD_RESOURCE_UNAVAILABLE = 0x80040000;
    public const int BAD_COMMUNICATION_ERROR = 0x80050000;
    public const int BAD_ENCODING_ERROR = 0x80060000;
    public const int BAD_DECODING_ERROR = 0x80070000;
    public const int BAD_ENCODING_LIMITS_EXCEEDED = 0x80080000;
    public const int BAD_REQUEST_TOO_LARGE = 0x800B8000;
    public const int BAD_RESPONSE_TOO_LARGE = 0x800B9000;
    public const int BAD_UNKNOWN_RESPONSE = 0x800BA000;
    public const int BAD_TIMEOUT = 0x800A0000;
    public const int BAD_SERVICE_UNSUPPORTED = 0x800B0000;
    public const int BAD_SHUTDOWN = 0x800C0000;
    public const int BAD_SERVER_NOT_CONNECTED = 0x800D0000;
    public const int BAD_SERVER_HALTED = 0x800E0000;
    public const int BAD_NOTHING_TO_DO = 0x800F0000;
    public const int BAD_TOO_MANY_OPERATIONS = 0x80100000;
    public const int BAD_TOO_MANY_MONITOR_ITEMS = 0x80DB0000;
    public const int BAD_DATA_TYPE_ID_UNKNOWN = 0x80110000;
    public const int BAD_CERTIFICATE_INVALID = 0x80120000;
    public const int BAD_SECURITY_CHECK_FAILED = 0x80130000;
    public const int BAD_CERTIFICATE_TIME_INVALID = 0x80140000;
    public const int BAD_CERTIFICATE_ISSUER_TIME_INVALID = 0x80150000;
    public const int BAD_CERTIFICATE_HOSTNAME_INVALID = 0x80160000;
    public const int BAD_CERTIFICATE_URI_INVALID = 0x80170000;
    public const int BAD_CERTIFICATE_USE_NOT_ALLOWED = 0x80180000;
    public const int BAD_CERTIFICATE_ISSUER_USE_NOT_ALLOWED = 0x80190000;
    public const int BAD_CERTIFICATE_UNTRUSTED = 0x801A0000;
    public const int BAD_CERTIFICATE_REVOCATION_UNKNOWN = 0x801B0000;
    public const int BAD_CERTIFICATE_ISSUER_REVOCATION_UNKNOWN = 0x801C0000;
    public const int BAD_CERTIFICATE_REVOKED = 0x801D0000;
    public const int BAD_CERTIFICATE_ISSUER_REVOKED = 0x801E0000;
    public const int BAD_USER_ACCESS_DENIED = 0x801F0000;
    public const int BAD_IDENTITY_TOKEN_INVALID = 0x80200000;
    public const int BAD_IDENTITY_TOKEN_REJECTED = 0x80210000;
    public const int BAD_SECURE_CHANNEL_ID_INVALID = 0x80220000;
    public const int BAD_INVALID_TIMESTAMP = 0x80230000;
    public const int BAD_NONCE_INVALID = 0x80240000;
    public const int BAD_SESSION_ID_INVALID = 0x80250000;
    public const int BAD_SESSION_CLOSED = 0x80260000;
    public const int BAD_SESSION_NOT_ACTIVATED = 0x80270000;
    public const int BAD_SUBSCRIPTION_ID_INVALID = 0x80280000;
    public const int BAD_REQUEST_HEADER_INVALID = 0x802A0000;
    public const int BAD_TIMESTAMPS_TO_RETURN_INVALID = 0x802B0000;
    public const int BAD_REQUEST_CANCELLED_BY_CLIENT = 0x802C0000;
    public const int BAD_TOO_MANY_ARGUMENTS = 0x80E50000;
    public const int BAD_NODE_ID_INVALID = 0x80330000;
    public const int BAD_NODE_ID_UNKNOWN = 0x80340000;
    public const int BAD_ATTRIBUTE_ID_INVALID = 0x80350000;
    public const int BAD_INDEX_RANGE_INVALID = 0x80360000;
    public const int BAD_INDEX_RANGE_NO_DATA = 0x80370000;
    public const int BAD_DATA_ENCODING_INVALID = 0x80380000;
    public const int BAD_DATA_ENCODING_UNSUPPORTED = 0x80390000;
    public const int BAD_NOT_READABLE = 0x803A0000;
    public const int BAD_NOT_WRITABLE = 0x803B0000;
    public const int BAD_OUT_OF_RANGE = 0x803C0000;
    public const int BAD_NOT_SUPPORTED = 0x803D0000;
    public const int BAD_NOT_FOUND = 0x803E0000;
    public const int BAD_OBJECT_DELETED = 0x803F0000;
    public const int BAD_NOT_IMPLEMENTED = 0x80400000;
    public const int BAD_MONITORING_MODE_INVALID = 0x80410000;
    public const int BAD_MONITORING_ITEM_ID_INVALID = 0x80420000;
    public const int BAD_MONITORING_PARAMETER_INVALID = 0x80430000;
    public const int BAD_MONITOR_ITEM_FILTER_UNSUPPORTED = 0x80440000;
    public const int BAD_FILTER_NOT_ALLOWED = 0x80450000;
    public const int BAD_STRUCTURE_MISSING = 0x80460000;
    public const int BAD_EVENT_FILTER_INVALID = 0x80470000;
    public const int BAD_CONTENT_FILTER_INVALID = 0x80480000;
    public const int BAD_FILTER_OPERATOR_INVALID = 0x80C10000;
    public const int BAD_FILTER_OPERATOR_UNSUPPORTED = 0x80C20000;
    public const int BAD_FILTER_OPERAND_COUNT_MISMATCH = 0x80C30000;
    public const int BAD_FILTER_OPERAND_INVALID = 0x80490000;
    public const int BAD_FILTER_ELEMENT_INVALID = 0x80C40000;
    public const int BAD_FILTER_LITERAL_INVALID = 0x80C50000;
    public const int BAD_CONTINUATION_POINT_INVALID = 0x804A0000;
    public const int BAD_NO_CONTINUATION_POINTS = 0x804B0000;
    public const int BAD_REFERENCE_TYPE_ID_INVALID = 0x804C0000;
    public const int BAD_BROWSE_DIRECTION_INVALID = 0x804D0000;
    public const int BAD_NODE_NOT_IN_VIEW = 0x804E0000;
    public const int BAD_SERVER_URI_INVALID = 0x804F0000;
    public const int BAD_SERVER_NAME_MISSING = 0x80500000;
    public const int BAD_DISCOVERY_URL_MISSING = 0x80510000;
    public const int BAD_SEMPAHORE_FILE_MISSING = 0x80520000;
    public const int BAD_REQUEST_TYPE_INVALID = 0x80530000;
    public const int BAD_SECURITY_MODE_REJECTED = 0x80540000;
    public const int BAD_SECURITY_POLICY_REJECTED = 0x80550000;
    public const int BAD_TOO_MANY_SESSIONS = 0x80560000;
    public const int BAD_USER_SIGNATURE_INVALID = 0x80570000;
    public const int BAD_APPLICATION_SIGNATURE_INVALID = 0x80580000;
    public const int BAD_NO_VALID_CERTIFICATES = 0x80590000;
    public const int BAD_IDENTITY_CHANGE_NOT_SUPPORTED = 0x80C60000;
    public const int BAD_REQUEST_CANCELLED_BY_REQUEST = 0x805A0000;
    public const int BAD_PARENT_NODE_ID_INVALID = 0x805B0000;
    public const int BAD_REFERENCE_NOT_ALLOWED = 0x805C0000;
    public const int BAD_NODE_ID_REJECTED = 0x805D0000;
    public const int BAD_NODE_ID_EXISTS = 0x805E0000;
    public const int BAD_NODE_CLASS_INVALID = 0x805F0000;
    public const int BAD_BROWSE_NAME_INVALID = 0x80600000;
    public const int BAD_BROWSE_NAME_DUPLICATED = 0x80610000;
    public const int BAD_NODE_ATTRIBUTES_INVALID = 0x80620000;
    public const int BAD_TYPE_DEFINITION_INVALID = 0x80630000;
    public const int BAD_SOURCE_NODE_ID_INVALID = 0x80640000;
    public const int BAD_TARGET_NODE_ID_INVALID = 0x80650000;
    public const int BAD_DUPLICATE_REFERENCE_NOT_ALLOWED = 0x80660000;
    public const int BAD_INVALID_SELF_REFERENCE = 0x80670000;
    public const int BAD_REFERENCE_LOCAL_ONLY = 0x80680000;
    public const int BAD_NO_DELETE_RIGHTS = 0x80690000;
    public const int UNCERTAIN_REFERENCE_OUT_OF_SERVER = 0x406A0000;
    public const int BAD_TOO_MANY_MATCHES = 0x806B0000;
    public const int BAD_QUERY_TOO_COMPLEX = 0x806C0000;
    public const int BAD_NO_MATCH = 0x806D0000;
    public const int BAD_MAX_AGE_INVALID = 0x806E0000;
    public const int BAD_SECURITY_MODE_INSUFFICIENT = 0x80E60000;
    public const int BAD_HISTORY_OPERATION_INVALID = 0x806F0000;
    public const int BAD_HISTORY_OPERATION_UNSUPPORTED = 0x80700000;
    public const int BAD_INVALID_TIMEOUT_HINT = 0x80710000;
    public const int BAD_WRITE_NOT_SUPPORTED = 0x80730000;
    public const int BAD_TYPE_MISMATCH = 0x80740000;
    public const int BAD_METHOD_INVALID = 0x80750000;
    public const int BAD_ARGUMENT_MISSING = 0x80760000;
    public const int BAD_TOO_MANY_SUBSCRIPTIONS = 0x80770000;
    public const int BAD_TOO_MANY_PUBLISHED_DATA_ITEMS = 0x80780000;
    public const int BAD_NO_SUBSCRIPTION = 0x80790000;
    public const int BAD_SEQUENCE_NUMBER_UNKNOWN = 0x807A0000;
    public const int BAD_MESSAGE_NOT_AVAILABLE = 0x807B0000;
    public const int BAD_INSUFFICIENT_CLIENT_PROFILE = 0x807C0000;
    public const int BAD_STATE_NOT_ACTIVE = 0x80BF0000;
    public const int BAD_TCP_SERVER_TOO_BUSY = 0x807D0000;
    public const int BAD_TCP_MESSAGE_TYPE_INVALID = 0x807E0000;
    public const int BAD_TCP_SECURE_CHANNEL_UNKNOWN = 0x807F0000;
    public const int BAD_TCP_MESSAGE_TOO_LARGE = 0x80800000;
    public const int BAD_TCP_NOT_ENOUGH_RESOURCES = 0x80810000;
    public const int BAD_TCP_INTERNAL_ERROR = 0x80820000;
    public const int BAD_TCP_ENDPOINT_URL_INVALID = 0x80830000;
    public const int BAD_REQUEST_INTERRUPTED = 0x80840000;
    public const int BAD_REQUEST_TIMEOUT = 0x80850000;
    public const int BAD_SECURE_CHANNEL_CLOSED = 0x80860000;
    public const int BAD_SECURE_CHANNEL_TOKEN_UNKNOWN = 0x80870000;
    public const int BAD_SEQUENCE_NUMBER_INVALID = 0x80880000;
    public const int BAD_PROTOCOL_VERSION_UNSUPPORTED = 0x80BE0000;
    public const int BAD_CONFIG_MISMATCH = 0x80890000;
    public const int BAD_NOT_CONNECTED = 0x808A0000;
    public const int BAD_DEVICE_FAILURE = 0x808B0000;
    public const int BAD_SENSOR_FAILURE = 0x808C0000;
    public const int BAD_OUT_OF_SERVICE = 0x808D0000;
    public const int BAD_DEADBAND_FILTER_INVALID = 0x808E0000;

    public function __construct(
        public int $value,
    ) {
    }

    /**
     * Create a Good status code
     */
    public static function good(): self
    {
        return new self(self::GOOD);
    }

    /**
     * Create a Bad status code
     */
    public static function bad(int $code = self::BAD): self
    {
        return new self($code);
    }

    /**
     * Create an Uncertain status code
     */
    public static function uncertain(int $code = self::UNCERTAIN): self
    {
        return new self($code);
    }

    /**
     * Check if status is Good
     */
    public function isGood(): bool
    {
        return ($this->value & 0xC0000000) === 0;
    }

    /**
     * Check if status is Bad
     */
    public function isBad(): bool
    {
        return ($this->value & 0x80000000) !== 0;
    }

    /**
     * Check if status is Uncertain
     */
    public function isUncertain(): bool
    {
        return ($this->value & 0xC0000000) === 0x40000000;
    }

    /**
     * Get severity level (0=Good, 1=Uncertain, 2/3=Bad)
     */
    public function getSeverity(): int
    {
        return ($this->value >> 30) & 0x03;
    }

    /**
     * Get the status code (bits 0-15)
     */
    public function getCode(): int
    {
        return $this->value & 0xFFFF0000;
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->value);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        return new self($decoder->readUInt32());
    }

    public function toString(): string
    {
        return sprintf('0x%08X', $this->value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
