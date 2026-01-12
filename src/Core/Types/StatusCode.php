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
    public const int GOOD = 0x00000000;
    public const int UNCERTAIN = 0x40000000;
    public const int BAD = 0x80000000;
    public const int BAD_UNEXPECTED_ERROR = 0x80010000;
    public const int BAD_INTERNAL_ERROR = 0x80020000;
    public const int BAD_OUT_OF_MEMORY = 0x80030000;
    public const int BAD_RESOURCE_UNAVAILABLE = 0x80040000;
    public const int BAD_COMMUNICATION_ERROR = 0x80050000;
    public const int BAD_ENCODING_ERROR = 0x80060000;
    public const int BAD_DECODING_ERROR = 0x80070000;
    public const int BAD_ENCODING_LIMITS_EXCEEDED = 0x80080000;
    public const int BAD_REQUEST_TOO_LARGE = 0x80B80000;
    public const int BAD_RESPONSE_TOO_LARGE = 0x80B90000;
    public const int BAD_UNKNOWN_RESPONSE = 0x80090000;
    public const int BAD_TIMEOUT = 0x800A0000;
    public const int BAD_SERVICE_UNSUPPORTED = 0x800B0000;
    public const int BAD_SHUTDOWN = 0x800C0000;
    public const int BAD_SERVER_NOT_CONNECTED = 0x800D0000;
    public const int BAD_SERVER_HALTED = 0x800E0000;
    public const int BAD_NOTHING_TO_DO = 0x800F0000;
    public const int BAD_TOO_MANY_OPERATIONS = 0x80100000;
    public const int BAD_TOO_MANY_MONITORED_ITEMS = 0x80DB0000;
    public const int BAD_DATA_TYPE_ID_UNKNOWN = 0x80110000;
    public const int BAD_CERTIFICATE_INVALID = 0x80120000;
    public const int BAD_SECURITY_CHECKS_FAILED = 0x80130000;
    public const int BAD_CERTIFICATE_POLICY_CHECK_FAILED = 0x81140000;
    public const int BAD_CERTIFICATE_TIME_INVALID = 0x80140000;
    public const int BAD_CERTIFICATE_ISSUER_TIME_INVALID = 0x80150000;
    public const int BAD_CERTIFICATE_HOST_NAME_INVALID = 0x80160000;
    public const int BAD_CERTIFICATE_URI_INVALID = 0x80170000;
    public const int BAD_CERTIFICATE_USE_NOT_ALLOWED = 0x80180000;
    public const int BAD_CERTIFICATE_ISSUER_USE_NOT_ALLOWED = 0x80190000;
    public const int BAD_CERTIFICATE_UNTRUSTED = 0x801A0000;
    public const int BAD_CERTIFICATE_REVOCATION_UNKNOWN = 0x801B0000;
    public const int BAD_CERTIFICATE_ISSUER_REVOCATION_UNKNOWN = 0x801C0000;
    public const int BAD_CERTIFICATE_REVOKED = 0x801D0000;
    public const int BAD_CERTIFICATE_ISSUER_REVOKED = 0x801E0000;
    public const int BAD_CERTIFICATE_CHAIN_INCOMPLETE = 0x810D0000;
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
    public const int BAD_LICENSE_EXPIRED = 0x810E0000;
    public const int BAD_LICENSE_LIMITS_EXCEEDED = 0x810F0000;
    public const int BAD_LICENSE_NOT_AVAILABLE = 0x81100000;
    public const int BAD_SERVER_TOO_BUSY = 0x80EE0000;
    public const int GOOD_PASSWORD_CHANGE_REQUIRED = 0x00EF0000;
    public const int GOOD_SUBSCRIPTION_TRANSFERRED = 0x002D0000;
    public const int GOOD_COMPLETES_ASYNCHRONOUSLY = 0x002E0000;
    public const int GOOD_OVERLOAD = 0x002F0000;
    public const int GOOD_CLAMPED = 0x00300000;
    public const int BAD_NO_COMMUNICATION = 0x80310000;
    public const int BAD_WAITING_FOR_INITIAL_DATA = 0x80320000;
    public const int BAD_NODE_ID_INVALID = 0x80330000;
    public const int BAD_NODE_ID_UNKNOWN = 0x80340000;
    public const int BAD_ATTRIBUTE_ID_INVALID = 0x80350000;
    public const int BAD_INDEX_RANGE_INVALID = 0x80360000;
    public const int BAD_INDEX_RANGE_NO_DATA = 0x80370000;
    public const int BAD_INDEX_RANGE_DATA_MISMATCH = 0x80EA0000;
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
    public const int BAD_MONITORED_ITEM_ID_INVALID = 0x80420000;
    public const int BAD_MONITORED_ITEM_FILTER_INVALID = 0x80430000;
    public const int BAD_MONITORED_ITEM_FILTER_UNSUPPORTED = 0x80440000;
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
    public const int BAD_NUMERIC_OVERFLOW = 0x81120000;
    public const int BAD_LOCALE_NOT_SUPPORTED = 0x80ED0000;
    public const int BAD_NO_VALUE = 0x80F00000;
    public const int BAD_SERVER_URI_INVALID = 0x804F0000;
    public const int BAD_SERVER_NAME_MISSING = 0x80500000;
    public const int BAD_DISCOVERY_URL_MISSING = 0x80510000;
    public const int BAD_SEMAPHORE_FILE_MISSING = 0x80520000;
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
    public const int UNCERTAIN_REFERENCE_NOT_DELETED = 0x40BC0000;
    public const int BAD_SERVER_INDEX_INVALID = 0x806A0000;
    public const int BAD_VIEW_ID_UNKNOWN = 0x806B0000;
    public const int BAD_VIEW_TIMESTAMP_INVALID = 0x80C90000;
    public const int BAD_VIEW_PARAMETER_MISMATCH = 0x80CA0000;
    public const int BAD_VIEW_VERSION_INVALID = 0x80CB0000;
    public const int UNCERTAIN_NOT_ALL_NODES_AVAILABLE = 0x40C00000;
    public const int GOOD_RESULTS_MAY_BE_INCOMPLETE = 0x00BA0000;
    public const int BAD_NOT_TYPE_DEFINITION = 0x80C80000;
    public const int UNCERTAIN_REFERENCE_OUT_OF_SERVER = 0x406C0000;
    public const int BAD_TOO_MANY_MATCHES = 0x806D0000;
    public const int BAD_QUERY_TOO_COMPLEX = 0x806E0000;
    public const int BAD_NO_MATCH = 0x806F0000;
    public const int BAD_MAX_AGE_INVALID = 0x80700000;
    public const int BAD_SECURITY_MODE_INSUFFICIENT = 0x80E60000;
    public const int BAD_HISTORY_OPERATION_INVALID = 0x80710000;
    public const int BAD_HISTORY_OPERATION_UNSUPPORTED = 0x80720000;
    public const int BAD_INVALID_TIMESTAMP_ARGUMENT = 0x80BD0000;
    public const int BAD_WRITE_NOT_SUPPORTED = 0x80730000;
    public const int BAD_TYPE_MISMATCH = 0x80740000;
    public const int BAD_METHOD_INVALID = 0x80750000;
    public const int BAD_ARGUMENTS_MISSING = 0x80760000;
    public const int BAD_NOT_EXECUTABLE = 0x81110000;
    public const int BAD_TOO_MANY_SUBSCRIPTIONS = 0x80770000;
    public const int BAD_TOO_MANY_PUBLISH_REQUESTS = 0x80780000;
    public const int BAD_NO_SUBSCRIPTION = 0x80790000;
    public const int BAD_SEQUENCE_NUMBER_UNKNOWN = 0x807A0000;
    public const int GOOD_RETRANSMISSION_QUEUE_NOT_SUPPORTED = 0x00DF0000;
    public const int BAD_MESSAGE_NOT_AVAILABLE = 0x807B0000;
    public const int BAD_INSUFFICIENT_CLIENT_PROFILE = 0x807C0000;
    public const int BAD_STATE_NOT_ACTIVE = 0x80BF0000;
    public const int BAD_ALREADY_EXISTS = 0x81150000;
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
    public const int BAD_CONFIGURATION_ERROR = 0x80890000;
    public const int BAD_NOT_CONNECTED = 0x808A0000;
    public const int BAD_DEVICE_FAILURE = 0x808B0000;
    public const int BAD_SENSOR_FAILURE = 0x808C0000;
    public const int BAD_OUT_OF_SERVICE = 0x808D0000;
    public const int BAD_DEADBAND_FILTER_INVALID = 0x808E0000;
    public const int UNCERTAIN_NO_COMMUNICATION_LAST_USABLE_VALUE = 0x408F0000;
    public const int UNCERTAIN_LAST_USABLE_VALUE = 0x40900000;
    public const int UNCERTAIN_SUBSTITUTE_VALUE = 0x40910000;
    public const int UNCERTAIN_INITIAL_VALUE = 0x40920000;
    public const int UNCERTAIN_SENSOR_NOT_ACCURATE = 0x40930000;
    public const int UNCERTAIN_ENGINEERING_UNITS_EXCEEDED = 0x40940000;
    public const int UNCERTAIN_SUB_NORMAL = 0x40950000;
    public const int GOOD_LOCAL_OVERRIDE = 0x00960000;
    public const int GOOD_SUB_NORMAL = 0x00EB0000;
    public const int BAD_REFRESH_IN_PROGRESS = 0x80970000;
    public const int BAD_CONDITION_ALREADY_DISABLED = 0x80980000;
    public const int BAD_CONDITION_ALREADY_ENABLED = 0x80CC0000;
    public const int BAD_CONDITION_DISABLED = 0x80990000;
    public const int BAD_EVENT_ID_UNKNOWN = 0x809A0000;
    public const int BAD_EVENT_NOT_ACKNOWLEDGEABLE = 0x80BB0000;
    public const int BAD_DIALOG_NOT_ACTIVE = 0x80CD0000;
    public const int BAD_DIALOG_RESPONSE_INVALID = 0x80CE0000;
    public const int BAD_CONDITION_BRANCH_ALREADY_ACKED = 0x80CF0000;
    public const int BAD_CONDITION_BRANCH_ALREADY_CONFIRMED = 0x80D00000;
    public const int BAD_CONDITION_ALREADY_SHELVED = 0x80D10000;
    public const int BAD_CONDITION_NOT_SHELVED = 0x80D20000;
    public const int BAD_SHELVING_TIME_OUT_OF_RANGE = 0x80D30000;
    public const int BAD_NO_DATA = 0x809B0000;
    public const int BAD_BOUND_NOT_FOUND = 0x80D70000;
    public const int BAD_BOUND_NOT_SUPPORTED = 0x80D80000;
    public const int BAD_DATA_LOST = 0x809D0000;
    public const int BAD_DATA_UNAVAILABLE = 0x809E0000;
    public const int BAD_ENTRY_EXISTS = 0x809F0000;
    public const int BAD_NO_ENTRY_EXISTS = 0x80A00000;
    public const int BAD_TIMESTAMP_NOT_SUPPORTED = 0x80A10000;
    public const int GOOD_ENTRY_INSERTED = 0x00A20000;
    public const int GOOD_ENTRY_REPLACED = 0x00A30000;
    public const int UNCERTAIN_DATA_SUB_NORMAL = 0x40A40000;
    public const int GOOD_NO_DATA = 0x00A50000;
    public const int GOOD_MORE_DATA = 0x00A60000;
    public const int BAD_AGGREGATE_LIST_MISMATCH = 0x80D40000;
    public const int BAD_AGGREGATE_NOT_SUPPORTED = 0x80D50000;
    public const int BAD_AGGREGATE_INVALID_INPUTS = 0x80D60000;
    public const int BAD_AGGREGATE_CONFIGURATION_REJECTED = 0x80DA0000;
    public const int GOOD_DATA_IGNORED = 0x00D90000;
    public const int BAD_REQUEST_NOT_ALLOWED = 0x80E40000;
    public const int BAD_REQUEST_NOT_COMPLETE = 0x81130000;
    public const int BAD_TRANSACTION_PENDING = 0x80E80000;
    public const int BAD_TRANSACTION_FAILED = 0x80F10000;
    public const int BAD_TICKET_REQUIRED = 0x811F0000;
    public const int BAD_TICKET_INVALID = 0x81200000;
    public const int BAD_LOCKED = 0x80E90000;
    public const int BAD_REQUIRES_LOCK = 0x80EC0000;
    public const int GOOD_EDITED = 0x00DC0000;
    public const int GOOD_POST_ACTION_FAILED = 0x00DD0000;
    public const int UNCERTAIN_DOMINANT_VALUE_CHANGED = 0x40DE0000;
    public const int GOOD_DEPENDENT_VALUE_CHANGED = 0x00E00000;
    public const int BAD_DOMINANT_VALUE_CHANGED = 0x80E10000;
    public const int UNCERTAIN_DEPENDENT_VALUE_CHANGED = 0x40E20000;
    public const int BAD_DEPENDENT_VALUE_CHANGED = 0x80E30000;
    public const int GOOD_EDITED_DEPENDENT_VALUE_CHANGED = 0x01160000;
    public const int GOOD_EDITED_DOMINANT_VALUE_CHANGED = 0x01170000;
    public const int GOOD_EDITED_DOMINANT_VALUE_CHANGED_DEPENDENT_VALUE_CHANGED = 0x01180000;
    public const int BAD_EDITED_OUT_OF_RANGE = 0x81190000;
    public const int BAD_INITIAL_VALUE_OUT_OF_RANGE = 0x811A0000;
    public const int BAD_OUT_OF_RANGE_DOMINANT_VALUE_CHANGED = 0x811B0000;
    public const int BAD_EDITED_OUT_OF_RANGE_DOMINANT_VALUE_CHANGED = 0x811C0000;
    public const int BAD_OUT_OF_RANGE_DOMINANT_VALUE_CHANGED_DEPENDENT_VALUE_CHANGED = 0x811D0000;
    public const int BAD_EDITED_OUT_OF_RANGE_DOMINANT_VALUE_CHANGED_DEPENDENT_VALUE_CHANGED = 0x811E0000;
    public const int GOOD_COMMUNICATION_EVENT = 0x00A70000;
    public const int GOOD_SHUTDOWN_EVENT = 0x00A80000;
    public const int GOOD_CALL_AGAIN = 0x00A90000;
    public const int GOOD_NON_CRITICAL_TIMEOUT = 0x00AA0000;
    public const int BAD_INVALID_ARGUMENT = 0x80AB0000;
    public const int BAD_CONNECTION_REJECTED = 0x80AC0000;
    public const int BAD_DISCONNECT = 0x80AD0000;
    public const int BAD_CONNECTION_CLOSED = 0x80AE0000;
    public const int BAD_INVALID_STATE = 0x80AF0000;
    public const int BAD_END_OF_STREAM = 0x80B00000;
    public const int BAD_NO_DATA_AVAILABLE = 0x80B10000;
    public const int BAD_WAITING_FOR_RESPONSE = 0x80B20000;
    public const int BAD_OPERATION_ABANDONED = 0x80B30000;
    public const int BAD_EXPECTED_STREAM_TO_BLOCK = 0x80B40000;
    public const int BAD_WOULD_BLOCK = 0x80B50000;
    public const int BAD_SYNTAX_ERROR = 0x80B60000;
    public const int BAD_MAX_CONNECTIONS_REACHED = 0x80B70000;
    public const int UNCERTAIN_TRANSDUCER_IN_MANUAL = 0x42080000;
    public const int UNCERTAIN_SIMULATED_VALUE = 0x42090000;
    public const int UNCERTAIN_SENSOR_CALIBRATION = 0x420A0000;
    public const int UNCERTAIN_CONFIGURATION_ERROR = 0x420F0000;
    public const int GOOD_CASCADE_INITIALIZATION_ACKNOWLEDGED = 0x04010000;
    public const int GOOD_CASCADE_INITIALIZATION_REQUEST = 0x04020000;
    public const int GOOD_CASCADE_NOT_INVITED = 0x04030000;
    public const int GOOD_CASCADE_NOT_SELECTED = 0x04040000;
    public const int GOOD_FAULT_STATE_ACTIVE = 0x04070000;
    public const int GOOD_INITIATE_FAULT_STATE = 0x04080000;
    public const int GOOD_CASCADE = 0x04090000;
    public const int BAD_DATA_SET_ID_INVALID = 0x80E70000;

    private const array METADATA = [
        0x00000000 => ['name' => 'Good', 'description' => 'The operation succeeded.'],
        0x40000000 => ['name' => 'Uncertain', 'description' => 'The operation was uncertain.'],
        0x80000000 => ['name' => 'Bad', 'description' => 'The operation failed.'],
        0x80010000 => ['name' => 'BadUnexpectedError', 'description' => 'An unexpected error occurred.'],
        0x80020000 => ['name' => 'BadInternalError', 'description' => 'An internal error occurred as a result of a programming or configuration error.'],
        0x80030000 => ['name' => 'BadOutOfMemory', 'description' => 'Not enough memory to complete the operation.'],
        0x80040000 => ['name' => 'BadResourceUnavailable', 'description' => 'An operating system resource is not available.'],
        0x80050000 => ['name' => 'BadCommunicationError', 'description' => 'A low level communication error occurred.'],
        0x80060000 => ['name' => 'BadEncodingError', 'description' => 'Encoding halted because of invalid data in the objects being serialized.'],
        0x80070000 => ['name' => 'BadDecodingError', 'description' => 'Decoding halted because of invalid data in the stream.'],
        0x80080000 => ['name' => 'BadEncodingLimitsExceeded', 'description' => 'The message encoding/decoding limits imposed by the stack have been exceeded.'],
        0x80B80000 => ['name' => 'BadRequestTooLarge', 'description' => 'The request message size exceeds limits set by the server.'],
        0x80B90000 => ['name' => 'BadResponseTooLarge', 'description' => 'The response message size exceeds limits set by the client or server.'],
        0x80090000 => ['name' => 'BadUnknownResponse', 'description' => 'An unrecognized response was received from the server.'],
        0x800A0000 => ['name' => 'BadTimeout', 'description' => 'The operation timed out.'],
        0x800B0000 => ['name' => 'BadServiceUnsupported', 'description' => 'The server does not support the requested service.'],
        0x800C0000 => ['name' => 'BadShutdown', 'description' => 'The operation was cancelled because the application is shutting down.'],
        0x800D0000 => ['name' => 'BadServerNotConnected', 'description' => 'The operation could not complete because the client is not connected to the server.'],
        0x800E0000 => ['name' => 'BadServerHalted', 'description' => 'The server has stopped and cannot process any requests.'],
        0x800F0000 => ['name' => 'BadNothingToDo', 'description' => 'No processing could be done because there was nothing to do.'],
        0x80100000 => ['name' => 'BadTooManyOperations', 'description' => 'The request could not be processed because it specified too many operations.'],
        0x80DB0000 => ['name' => 'BadTooManyMonitoredItems', 'description' => 'The request could not be processed because there are too many monitored items in the subscription.'],
        0x80110000 => ['name' => 'BadDataTypeIdUnknown', 'description' => 'The extension object cannot be (de)serialized because the data type id is not recognized.'],
        0x80120000 => ['name' => 'BadCertificateInvalid', 'description' => 'The certificate provided as a parameter is not valid.'],
        0x80130000 => ['name' => 'BadSecurityChecksFailed', 'description' => 'An error occurred verifying security.'],
        0x81140000 => ['name' => 'BadCertificatePolicyCheckFailed', 'description' => 'The certificate does not meet the requirements of the security policy.'],
        0x80140000 => ['name' => 'BadCertificateTimeInvalid', 'description' => 'The certificate has expired or is not yet valid.'],
        0x80150000 => ['name' => 'BadCertificateIssuerTimeInvalid', 'description' => 'An issuer certificate has expired or is not yet valid.'],
        0x80160000 => ['name' => 'BadCertificateHostNameInvalid', 'description' => 'The HostName used to connect to a server does not match a HostName in the certificate.'],
        0x80170000 => ['name' => 'BadCertificateUriInvalid', 'description' => 'The URI specified in the ApplicationDescription does not match the URI in the certificate.'],
        0x80180000 => ['name' => 'BadCertificateUseNotAllowed', 'description' => 'The certificate may not be used for the requested operation.'],
        0x80190000 => ['name' => 'BadCertificateIssuerUseNotAllowed', 'description' => 'The issuer certificate may not be used for the requested operation.'],
        0x801A0000 => ['name' => 'BadCertificateUntrusted', 'description' => 'The certificate is not trusted.'],
        0x801B0000 => ['name' => 'BadCertificateRevocationUnknown', 'description' => 'It was not possible to determine if the certificate has been revoked.'],
        0x801C0000 => ['name' => 'BadCertificateIssuerRevocationUnknown', 'description' => 'It was not possible to determine if the issuer certificate has been revoked.'],
        0x801D0000 => ['name' => 'BadCertificateRevoked', 'description' => 'The certificate has been revoked.'],
        0x801E0000 => ['name' => 'BadCertificateIssuerRevoked', 'description' => 'The issuer certificate has been revoked.'],
        0x810D0000 => ['name' => 'BadCertificateChainIncomplete', 'description' => 'The certificate chain is incomplete.'],
        0x801F0000 => ['name' => 'BadUserAccessDenied', 'description' => 'User does not have permission to perform the requested operation.'],
        0x80200000 => ['name' => 'BadIdentityTokenInvalid', 'description' => 'The user identity token is not valid.'],
        0x80210000 => ['name' => 'BadIdentityTokenRejected', 'description' => 'The user identity token is valid but the server has rejected it.'],
        0x80220000 => ['name' => 'BadSecureChannelIdInvalid', 'description' => 'The specified secure channel is no longer valid.'],
        0x80230000 => ['name' => 'BadInvalidTimestamp', 'description' => 'The timestamp is outside the range allowed by the server.'],
        0x80240000 => ['name' => 'BadNonceInvalid', 'description' => 'The nonce does appear to be not a random value or it is not the correct length.'],
        0x80250000 => ['name' => 'BadSessionIdInvalid', 'description' => 'The session id is not valid.'],
        0x80260000 => ['name' => 'BadSessionClosed', 'description' => 'The session was closed by the client.'],
        0x80270000 => ['name' => 'BadSessionNotActivated', 'description' => 'The session cannot be used because ActivateSession has not been called.'],
        0x80280000 => ['name' => 'BadSubscriptionIdInvalid', 'description' => 'The subscription id is not valid.'],
        0x802A0000 => ['name' => 'BadRequestHeaderInvalid', 'description' => 'The header for the request is missing or invalid.'],
        0x802B0000 => ['name' => 'BadTimestampsToReturnInvalid', 'description' => 'The timestamps to return parameter is invalid.'],
        0x802C0000 => ['name' => 'BadRequestCancelledByClient', 'description' => 'The request was cancelled by the client.'],
        0x80E50000 => ['name' => 'BadTooManyArguments', 'description' => 'Too many arguments were provided.'],
        0x810E0000 => ['name' => 'BadLicenseExpired', 'description' => 'The server requires a license to operate in general or to perform a service or operation, but existing license is expired.'],
        0x810F0000 => ['name' => 'BadLicenseLimitsExceeded', 'description' => 'The server has limits on number of allowed operations / objects, based on installed licenses, and these limits where exceeded.'],
        0x81100000 => ['name' => 'BadLicenseNotAvailable', 'description' => 'The server does not have a license which is required to operate in general or to perform a service or operation.'],
        0x80EE0000 => ['name' => 'BadServerTooBusy', 'description' => 'The Server does not have the resources to process the request at this time.'],
        0x00EF0000 => ['name' => 'GoodPasswordChangeRequired', 'description' => 'The log-on for the user succeeded but the user is required to change the password.'],
        0x002D0000 => ['name' => 'GoodSubscriptionTransferred', 'description' => 'The subscription was transferred to another session.'],
        0x002E0000 => ['name' => 'GoodCompletesAsynchronously', 'description' => 'The processing will complete asynchronously.'],
        0x002F0000 => ['name' => 'GoodOverload', 'description' => 'Sampling has slowed down due to resource limitations.'],
        0x00300000 => ['name' => 'GoodClamped', 'description' => 'The value written was accepted but was clamped.'],
        0x80310000 => ['name' => 'BadNoCommunication', 'description' => 'Communication with the data source is defined, but not established, and there is no last known value available.'],
        0x80320000 => ['name' => 'BadWaitingForInitialData', 'description' => 'Waiting for the server to obtain values from the underlying data source.'],
        0x80330000 => ['name' => 'BadNodeIdInvalid', 'description' => 'The syntax the node id is not valid or refers to a node that is not valid for the operation.'],
        0x80340000 => ['name' => 'BadNodeIdUnknown', 'description' => 'The node id refers to a node that does not exist in the server address space.'],
        0x80350000 => ['name' => 'BadAttributeIdInvalid', 'description' => 'The attribute is not supported for the specified Node.'],
        0x80360000 => ['name' => 'BadIndexRangeInvalid', 'description' => 'The syntax of the index range parameter is invalid.'],
        0x80370000 => ['name' => 'BadIndexRangeNoData', 'description' => 'No data exists within the range of indexes specified.'],
        0x80EA0000 => ['name' => 'BadIndexRangeDataMismatch', 'description' => 'The written data does not match the IndexRange specified.'],
        0x80380000 => ['name' => 'BadDataEncodingInvalid', 'description' => 'The data encoding is invalid.'],
        0x80390000 => ['name' => 'BadDataEncodingUnsupported', 'description' => 'The server does not support the requested data encoding for the node.'],
        0x803A0000 => ['name' => 'BadNotReadable', 'description' => 'The access level does not allow reading or subscribing to the Node.'],
        0x803B0000 => ['name' => 'BadNotWritable', 'description' => 'The access level does not allow writing to the Node.'],
        0x803C0000 => ['name' => 'BadOutOfRange', 'description' => 'The value was out of range.'],
        0x803D0000 => ['name' => 'BadNotSupported', 'description' => 'The requested operation is not supported.'],
        0x803E0000 => ['name' => 'BadNotFound', 'description' => 'A requested item was not found or a search operation ended without success.'],
        0x803F0000 => ['name' => 'BadObjectDeleted', 'description' => 'The object cannot be used because it has been deleted.'],
        0x80400000 => ['name' => 'BadNotImplemented', 'description' => 'Requested operation is not implemented.'],
        0x80410000 => ['name' => 'BadMonitoringModeInvalid', 'description' => 'The monitoring mode is invalid.'],
        0x80420000 => ['name' => 'BadMonitoredItemIdInvalid', 'description' => 'The monitoring item id does not refer to a valid monitored item.'],
        0x80430000 => ['name' => 'BadMonitoredItemFilterInvalid', 'description' => 'The monitored item filter parameter is not valid.'],
        0x80440000 => ['name' => 'BadMonitoredItemFilterUnsupported', 'description' => 'The server does not support the requested monitored item filter.'],
        0x80450000 => ['name' => 'BadFilterNotAllowed', 'description' => 'A monitoring filter cannot be used in combination with the attribute specified.'],
        0x80460000 => ['name' => 'BadStructureMissing', 'description' => 'A mandatory structured parameter was missing or null.'],
        0x80470000 => ['name' => 'BadEventFilterInvalid', 'description' => 'The event filter is not valid.'],
        0x80480000 => ['name' => 'BadContentFilterInvalid', 'description' => 'The content filter is not valid.'],
        0x80C10000 => ['name' => 'BadFilterOperatorInvalid', 'description' => 'An unrecognized operator was provided in a filter.'],
        0x80C20000 => ['name' => 'BadFilterOperatorUnsupported', 'description' => 'A valid operator was provided, but the server does not provide support for this filter operator.'],
        0x80C30000 => ['name' => 'BadFilterOperandCountMismatch', 'description' => 'The number of operands provided for the filter operator was less then expected for the operand provided.'],
        0x80490000 => ['name' => 'BadFilterOperandInvalid', 'description' => 'The operand used in a content filter is not valid.'],
        0x80C40000 => ['name' => 'BadFilterElementInvalid', 'description' => 'The referenced element is not a valid element in the content filter.'],
        0x80C50000 => ['name' => 'BadFilterLiteralInvalid', 'description' => 'The referenced literal is not a valid value.'],
        0x804A0000 => ['name' => 'BadContinuationPointInvalid', 'description' => 'The continuation point provide is longer valid.'],
        0x804B0000 => ['name' => 'BadNoContinuationPoints', 'description' => 'The operation could not be processed because all continuation points have been allocated.'],
        0x804C0000 => ['name' => 'BadReferenceTypeIdInvalid', 'description' => 'The reference type id does not refer to a valid reference type node.'],
        0x804D0000 => ['name' => 'BadBrowseDirectionInvalid', 'description' => 'The browse direction is not valid.'],
        0x804E0000 => ['name' => 'BadNodeNotInView', 'description' => 'The node is not part of the view.'],
        0x81120000 => ['name' => 'BadNumericOverflow', 'description' => 'The number was not accepted because of a numeric overflow.'],
        0x80ED0000 => ['name' => 'BadLocaleNotSupported', 'description' => 'The locale in the requested write operation is not supported.'],
        0x80F00000 => ['name' => 'BadNoValue', 'description' => 'The variable has no default value and no initial value.'],
        0x804F0000 => ['name' => 'BadServerUriInvalid', 'description' => 'The ServerUri is not a valid URI.'],
        0x80500000 => ['name' => 'BadServerNameMissing', 'description' => 'No ServerName was specified.'],
        0x80510000 => ['name' => 'BadDiscoveryUrlMissing', 'description' => 'No DiscoveryUrl was specified.'],
        0x80520000 => ['name' => 'BadSemaphoreFileMissing', 'description' => 'The semaphore file specified by the client is not valid.'],
        0x80530000 => ['name' => 'BadRequestTypeInvalid', 'description' => 'The security token request type is not valid.'],
        0x80540000 => ['name' => 'BadSecurityModeRejected', 'description' => 'The security mode does not meet the requirements set by the server.'],
        0x80550000 => ['name' => 'BadSecurityPolicyRejected', 'description' => 'The security policy does not meet the requirements set by the server.'],
        0x80560000 => ['name' => 'BadTooManySessions', 'description' => 'The server has reached its maximum number of sessions.'],
        0x80570000 => ['name' => 'BadUserSignatureInvalid', 'description' => 'The user token signature is missing or invalid.'],
        0x80580000 => ['name' => 'BadApplicationSignatureInvalid', 'description' => 'The signature generated with the client certificate is missing or invalid.'],
        0x80590000 => ['name' => 'BadNoValidCertificates', 'description' => 'The client did not provide at least one software certificate that is valid and meets the profile requirements for the server.'],
        0x80C60000 => ['name' => 'BadIdentityChangeNotSupported', 'description' => 'The server does not support changing the user identity assigned to the session.'],
        0x805A0000 => ['name' => 'BadRequestCancelledByRequest', 'description' => 'The request was cancelled by the client with the Cancel service.'],
        0x805B0000 => ['name' => 'BadParentNodeIdInvalid', 'description' => 'The parent node id does not to refer to a valid node.'],
        0x805C0000 => ['name' => 'BadReferenceNotAllowed', 'description' => 'The reference could not be created because it violates constraints imposed by the data model.'],
        0x805D0000 => ['name' => 'BadNodeIdRejected', 'description' => 'The requested node id was reject because it was either invalid or server does not allow node ids to be specified by the client.'],
        0x805E0000 => ['name' => 'BadNodeIdExists', 'description' => 'The requested node id is already used by another node.'],
        0x805F0000 => ['name' => 'BadNodeClassInvalid', 'description' => 'The node class is not valid.'],
        0x80600000 => ['name' => 'BadBrowseNameInvalid', 'description' => 'The browse name is invalid.'],
        0x80610000 => ['name' => 'BadBrowseNameDuplicated', 'description' => 'The browse name is not unique among nodes that share the same relationship with the parent.'],
        0x80620000 => ['name' => 'BadNodeAttributesInvalid', 'description' => 'The node attributes are not valid for the node class.'],
        0x80630000 => ['name' => 'BadTypeDefinitionInvalid', 'description' => 'The type definition node id does not reference an appropriate type node.'],
        0x80640000 => ['name' => 'BadSourceNodeIdInvalid', 'description' => 'The source node id does not reference a valid node.'],
        0x80650000 => ['name' => 'BadTargetNodeIdInvalid', 'description' => 'The target node id does not reference a valid node.'],
        0x80660000 => ['name' => 'BadDuplicateReferenceNotAllowed', 'description' => 'The reference type between the nodes is already defined.'],
        0x80670000 => ['name' => 'BadInvalidSelfReference', 'description' => 'The server does not allow this type of self reference on this node.'],
        0x80680000 => ['name' => 'BadReferenceLocalOnly', 'description' => 'The reference type is not valid for a reference to a remote server.'],
        0x80690000 => ['name' => 'BadNoDeleteRights', 'description' => 'The server will not allow the node to be deleted.'],
        0x40BC0000 => ['name' => 'UncertainReferenceNotDeleted', 'description' => 'The server was not able to delete all target references.'],
        0x806A0000 => ['name' => 'BadServerIndexInvalid', 'description' => 'The server index is not valid.'],
        0x806B0000 => ['name' => 'BadViewIdUnknown', 'description' => 'The view id does not refer to a valid view node.'],
        0x80C90000 => ['name' => 'BadViewTimestampInvalid', 'description' => 'The view timestamp is not available or not supported.'],
        0x80CA0000 => ['name' => 'BadViewParameterMismatch', 'description' => 'The view parameters are not consistent with each other.'],
        0x80CB0000 => ['name' => 'BadViewVersionInvalid', 'description' => 'The view version is not available or not supported.'],
        0x40C00000 => ['name' => 'UncertainNotAllNodesAvailable', 'description' => 'The list of references may not be complete because the underlying system is not available.'],
        0x00BA0000 => ['name' => 'GoodResultsMayBeIncomplete', 'description' => 'The server should have followed a reference to a node in a remote server but did not. The result set may be incomplete.'],
        0x80C80000 => ['name' => 'BadNotTypeDefinition', 'description' => 'The provided Nodeid was not a type definition nodeid.'],
        0x406C0000 => ['name' => 'UncertainReferenceOutOfServer', 'description' => 'One of the references to follow in the relative path references to a node in the address space in another server.'],
        0x806D0000 => ['name' => 'BadTooManyMatches', 'description' => 'The requested operation has too many matches to return.'],
        0x806E0000 => ['name' => 'BadQueryTooComplex', 'description' => 'The requested operation requires too many resources in the server.'],
        0x806F0000 => ['name' => 'BadNoMatch', 'description' => 'The requested operation has no match to return.'],
        0x80700000 => ['name' => 'BadMaxAgeInvalid', 'description' => 'The max age parameter is invalid.'],
        0x80E60000 => ['name' => 'BadSecurityModeInsufficient', 'description' => 'The operation is not permitted over the current secure channel.'],
        0x80710000 => ['name' => 'BadHistoryOperationInvalid', 'description' => 'The history details parameter is not valid.'],
        0x80720000 => ['name' => 'BadHistoryOperationUnsupported', 'description' => 'The server does not support the requested operation.'],
        0x80BD0000 => ['name' => 'BadInvalidTimestampArgument', 'description' => 'The defined timestamp to return was invalid.'],
        0x80730000 => ['name' => 'BadWriteNotSupported', 'description' => 'The server does not support writing the combination of value, status and timestamps provided.'],
        0x80740000 => ['name' => 'BadTypeMismatch', 'description' => 'The value supplied for the attribute is not of the same type as the attribute\'s value.'],
        0x80750000 => ['name' => 'BadMethodInvalid', 'description' => 'The method id does not refer to a method for the specified object.'],
        0x80760000 => ['name' => 'BadArgumentsMissing', 'description' => 'The client did not specify all of the input arguments for the method.'],
        0x81110000 => ['name' => 'BadNotExecutable', 'description' => 'The executable attribute does not allow the execution of the method.'],
        0x80770000 => ['name' => 'BadTooManySubscriptions', 'description' => 'The server has reached its maximum number of subscriptions.'],
        0x80780000 => ['name' => 'BadTooManyPublishRequests', 'description' => 'The server has reached the maximum number of queued publish requests.'],
        0x80790000 => ['name' => 'BadNoSubscription', 'description' => 'There is no subscription available for this session.'],
        0x807A0000 => ['name' => 'BadSequenceNumberUnknown', 'description' => 'The sequence number is unknown to the server.'],
        0x00DF0000 => ['name' => 'GoodRetransmissionQueueNotSupported', 'description' => 'The Server does not support retransmission queue and acknowledgement of sequence numbers is not available.'],
        0x807B0000 => ['name' => 'BadMessageNotAvailable', 'description' => 'The requested notification message is no longer available.'],
        0x807C0000 => ['name' => 'BadInsufficientClientProfile', 'description' => 'The client of the current session does not support one or more Profiles that are necessary for the subscription.'],
        0x80BF0000 => ['name' => 'BadStateNotActive', 'description' => 'The sub-state machine is not currently active.'],
        0x81150000 => ['name' => 'BadAlreadyExists', 'description' => 'An equivalent rule already exists.'],
        0x807D0000 => ['name' => 'BadTcpServerTooBusy', 'description' => 'The server cannot process the request because it is too busy.'],
        0x807E0000 => ['name' => 'BadTcpMessageTypeInvalid', 'description' => 'The type of the message specified in the header invalid.'],
        0x807F0000 => ['name' => 'BadTcpSecureChannelUnknown', 'description' => 'The SecureChannelId and/or TokenId are not currently in use.'],
        0x80800000 => ['name' => 'BadTcpMessageTooLarge', 'description' => 'The size of the message chunk specified in the header is too large.'],
        0x80810000 => ['name' => 'BadTcpNotEnoughResources', 'description' => 'There are not enough resources to process the request.'],
        0x80820000 => ['name' => 'BadTcpInternalError', 'description' => 'An internal error occurred.'],
        0x80830000 => ['name' => 'BadTcpEndpointUrlInvalid', 'description' => 'The server does not recognize the QueryString specified.'],
        0x80840000 => ['name' => 'BadRequestInterrupted', 'description' => 'The request could not be sent because of a network interruption.'],
        0x80850000 => ['name' => 'BadRequestTimeout', 'description' => 'Timeout occurred while processing the request.'],
        0x80860000 => ['name' => 'BadSecureChannelClosed', 'description' => 'The secure channel has been closed.'],
        0x80870000 => ['name' => 'BadSecureChannelTokenUnknown', 'description' => 'The token has expired or is not recognized.'],
        0x80880000 => ['name' => 'BadSequenceNumberInvalid', 'description' => 'The sequence number is not valid.'],
        0x80BE0000 => ['name' => 'BadProtocolVersionUnsupported', 'description' => 'The applications do not have compatible protocol versions.'],
        0x80890000 => ['name' => 'BadConfigurationError', 'description' => 'There is a problem with the configuration that affects the usefulness of the value.'],
        0x808A0000 => ['name' => 'BadNotConnected', 'description' => 'The variable should receive its value from another variable, but has never been configured to do so.'],
        0x808B0000 => ['name' => 'BadDeviceFailure', 'description' => 'There has been a failure in the device/data source that generates the value that has affected the value.'],
        0x808C0000 => ['name' => 'BadSensorFailure', 'description' => 'There has been a failure in the sensor from which the value is derived by the device/data source.'],
        0x808D0000 => ['name' => 'BadOutOfService', 'description' => 'The source of the data is not operational.'],
        0x808E0000 => ['name' => 'BadDeadbandFilterInvalid', 'description' => 'The deadband filter is not valid.'],
        0x408F0000 => ['name' => 'UncertainNoCommunicationLastUsableValue', 'description' => 'Communication to the data source has failed. The variable value is the last value that had a good quality.'],
        0x40900000 => ['name' => 'UncertainLastUsableValue', 'description' => 'Whatever was updating this value has stopped doing so.'],
        0x40910000 => ['name' => 'UncertainSubstituteValue', 'description' => 'The value is an operational value that was manually overwritten.'],
        0x40920000 => ['name' => 'UncertainInitialValue', 'description' => 'The value is an initial value for a variable that normally receives its value from another variable.'],
        0x40930000 => ['name' => 'UncertainSensorNotAccurate', 'description' => 'The value is at one of the sensor limits.'],
        0x40940000 => ['name' => 'UncertainEngineeringUnitsExceeded', 'description' => 'The value is outside of the range of values defined for this parameter.'],
        0x40950000 => ['name' => 'UncertainSubNormal', 'description' => 'The data value is derived from multiple sources and has less than the required number of Good sources.'],
        0x00960000 => ['name' => 'GoodLocalOverride', 'description' => 'The value has been overridden.'],
        0x00EB0000 => ['name' => 'GoodSubNormal', 'description' => 'The value is derived from multiple sources and has the required number of Good sources, but less than the full number of Good sources.'],
        0x80970000 => ['name' => 'BadRefreshInProgress', 'description' => 'This Condition refresh failed, a Condition refresh operation is already in progress.'],
        0x80980000 => ['name' => 'BadConditionAlreadyDisabled', 'description' => 'This condition has already been disabled.'],
        0x80CC0000 => ['name' => 'BadConditionAlreadyEnabled', 'description' => 'This condition has already been enabled.'],
        0x80990000 => ['name' => 'BadConditionDisabled', 'description' => 'Property not available, this condition is disabled.'],
        0x809A0000 => ['name' => 'BadEventIdUnknown', 'description' => 'The specified event id is not recognized.'],
        0x80BB0000 => ['name' => 'BadEventNotAcknowledgeable', 'description' => 'The event cannot be acknowledged.'],
        0x80CD0000 => ['name' => 'BadDialogNotActive', 'description' => 'The dialog condition is not active.'],
        0x80CE0000 => ['name' => 'BadDialogResponseInvalid', 'description' => 'The response is not valid for the dialog.'],
        0x80CF0000 => ['name' => 'BadConditionBranchAlreadyAcked', 'description' => 'The condition branch has already been acknowledged.'],
        0x80D00000 => ['name' => 'BadConditionBranchAlreadyConfirmed', 'description' => 'The condition branch has already been confirmed.'],
        0x80D10000 => ['name' => 'BadConditionAlreadyShelved', 'description' => 'The condition has already been shelved.'],
        0x80D20000 => ['name' => 'BadConditionNotShelved', 'description' => 'The condition is not currently shelved.'],
        0x80D30000 => ['name' => 'BadShelvingTimeOutOfRange', 'description' => 'The shelving time not within an acceptable range.'],
        0x809B0000 => ['name' => 'BadNoData', 'description' => 'No data exists for the requested time range or event filter.'],
        0x80D70000 => ['name' => 'BadBoundNotFound', 'description' => 'No data found to provide upper or lower bound value.'],
        0x80D80000 => ['name' => 'BadBoundNotSupported', 'description' => 'The server cannot retrieve a bound for the variable.'],
        0x809D0000 => ['name' => 'BadDataLost', 'description' => 'Data is missing due to collection started/stopped/lost.'],
        0x809E0000 => ['name' => 'BadDataUnavailable', 'description' => 'Expected data is unavailable for the requested time range due to an un-mounted volume, an off-line archive or tape, or similar reason for temporary unavailability.'],
        0x809F0000 => ['name' => 'BadEntryExists', 'description' => 'The data or event was not successfully inserted because a matching entry exists.'],
        0x80A00000 => ['name' => 'BadNoEntryExists', 'description' => 'The data or event was not successfully updated because no matching entry exists.'],
        0x80A10000 => ['name' => 'BadTimestampNotSupported', 'description' => 'The Client requested history using a TimestampsToReturn the Server does not support.'],
        0x00A20000 => ['name' => 'GoodEntryInserted', 'description' => 'The data or event was successfully inserted into the historical database.'],
        0x00A30000 => ['name' => 'GoodEntryReplaced', 'description' => 'The data or event field was successfully replaced in the historical database.'],
        0x40A40000 => ['name' => 'UncertainDataSubNormal', 'description' => 'The aggregate value is derived from multiple values and has less than the required number of Good values.'],
        0x00A50000 => ['name' => 'GoodNoData', 'description' => 'No data exists for the requested time range or event filter.'],
        0x00A60000 => ['name' => 'GoodMoreData', 'description' => 'More data is available in the time range beyond the number of values requested.'],
        0x80D40000 => ['name' => 'BadAggregateListMismatch', 'description' => 'The requested number of Aggregates does not match the requested number of NodeIds.'],
        0x80D50000 => ['name' => 'BadAggregateNotSupported', 'description' => 'The requested Aggregate is not support by the server.'],
        0x80D60000 => ['name' => 'BadAggregateInvalidInputs', 'description' => 'The aggregate value could not be derived due to invalid data inputs.'],
        0x80DA0000 => ['name' => 'BadAggregateConfigurationRejected', 'description' => 'The aggregate configuration is not valid for specified node.'],
        0x00D90000 => ['name' => 'GoodDataIgnored', 'description' => 'The request specifies fields which are not valid for the EventType or cannot be saved by the historian.'],
        0x80E40000 => ['name' => 'BadRequestNotAllowed', 'description' => 'The request was rejected by the server because it did not meet the criteria set by the server.'],
        0x81130000 => ['name' => 'BadRequestNotComplete', 'description' => 'The request has not been processed by the server yet.'],
        0x80E80000 => ['name' => 'BadTransactionPending', 'description' => 'The operation is not allowed because a transaction is in progress.'],
        0x80F10000 => ['name' => 'BadTransactionFailed', 'description' => 'The operation failed and all changes which were part of the transaction are rolled back.'],
        0x811F0000 => ['name' => 'BadTicketRequired', 'description' => 'The device identity needs a ticket before it can be accepted.'],
        0x81200000 => ['name' => 'BadTicketInvalid', 'description' => 'The device identity needs a ticket before it can be accepted.'],
        0x80E90000 => ['name' => 'BadLocked', 'description' => 'The requested operation is not allowed, because the Node is locked by a different application.'],
        0x80EC0000 => ['name' => 'BadRequiresLock', 'description' => 'The requested operation is not allowed, because the Node is not locked by the application.'],
        0x00DC0000 => ['name' => 'GoodEdited', 'description' => 'The value does not come from the real source and has been edited by the server.'],
        0x00DD0000 => ['name' => 'GoodPostActionFailed', 'description' => 'There was an error in execution of these post-actions.'],
        0x40DE0000 => ['name' => 'UncertainDominantValueChanged', 'description' => 'The related EngineeringUnit has been changed but the Variable Value is still provided based on the previous unit.'],
        0x00E00000 => ['name' => 'GoodDependentValueChanged', 'description' => 'A dependent value has been changed but the change has not been applied to the device.'],
        0x80E10000 => ['name' => 'BadDominantValueChanged', 'description' => 'The related EngineeringUnit has been changed but this change has not been applied to the device. The Variable Value is still dependent on the previous unit but its status is currently Bad.'],
        0x40E20000 => ['name' => 'UncertainDependentValueChanged', 'description' => 'A dependent value has been changed but the change has not been applied to the device. The quality of the dominant variable is uncertain.'],
        0x80E30000 => ['name' => 'BadDependentValueChanged', 'description' => 'A dependent value has been changed but the change has not been applied to the device. The quality of the dominant variable is Bad.'],
        0x01160000 => ['name' => 'GoodEdited_DependentValueChanged', 'description' => 'It is delivered with a dominant Variable value when a dependent Variable has changed but the change has not been applied.'],
        0x01170000 => ['name' => 'GoodEdited_DominantValueChanged', 'description' => 'It is delivered with a dependent Variable value when a dominant Variable has changed but the change has not been applied.'],
        0x01180000 => ['name' => 'GoodEdited_DominantValueChanged_DependentValueChanged', 'description' => 'It is delivered with a dependent Variable value when a dominant or dependent Variable has changed but change has not been applied.'],
        0x81190000 => ['name' => 'BadEdited_OutOfRange', 'description' => 'It is delivered with a Variable value when Variable has changed but the value is not legal.'],
        0x811A0000 => ['name' => 'BadInitialValue_OutOfRange', 'description' => 'It is delivered with a Variable value when a source Variable has changed but the value is not legal.'],
        0x811B0000 => ['name' => 'BadOutOfRange_DominantValueChanged', 'description' => 'It is delivered with a dependent Variable value when a dominant Variable has changed and the value is not legal.'],
        0x811C0000 => ['name' => 'BadEdited_OutOfRange_DominantValueChanged', 'description' => 'It is delivered with a dependent Variable value when a dominant Variable has changed, the value is not legal and the change has not been applied.'],
        0x811D0000 => ['name' => 'BadOutOfRange_DominantValueChanged_DependentValueChanged', 'description' => 'It is delivered with a dependent Variable value when a dominant or dependent Variable has changed and the value is not legal.'],
        0x811E0000 => ['name' => 'BadEdited_OutOfRange_DominantValueChanged_DependentValueChanged', 'description' => 'It is delivered with a dependent Variable value when a dominant or dependent Variable has changed, the value is not legal and the change has not been applied.'],
        0x00A70000 => ['name' => 'GoodCommunicationEvent', 'description' => 'The communication layer has raised an event.'],
        0x00A80000 => ['name' => 'GoodShutdownEvent', 'description' => 'The system is shutting down.'],
        0x00A90000 => ['name' => 'GoodCallAgain', 'description' => 'The operation is not finished and needs to be called again.'],
        0x00AA0000 => ['name' => 'GoodNonCriticalTimeout', 'description' => 'A non-critical timeout occurred.'],
        0x80AB0000 => ['name' => 'BadInvalidArgument', 'description' => 'One or more arguments are invalid.'],
        0x80AC0000 => ['name' => 'BadConnectionRejected', 'description' => 'Could not establish a network connection to remote server.'],
        0x80AD0000 => ['name' => 'BadDisconnect', 'description' => 'The server has disconnected from the client.'],
        0x80AE0000 => ['name' => 'BadConnectionClosed', 'description' => 'The network connection has been closed.'],
        0x80AF0000 => ['name' => 'BadInvalidState', 'description' => 'The operation cannot be completed because the object is closed, uninitialized or in some other invalid state.'],
        0x80B00000 => ['name' => 'BadEndOfStream', 'description' => 'Cannot move beyond end of the stream.'],
        0x80B10000 => ['name' => 'BadNoDataAvailable', 'description' => 'No data is currently available for reading from a non-blocking stream.'],
        0x80B20000 => ['name' => 'BadWaitingForResponse', 'description' => 'The asynchronous operation is waiting for a response.'],
        0x80B30000 => ['name' => 'BadOperationAbandoned', 'description' => 'The asynchronous operation was abandoned by the caller.'],
        0x80B40000 => ['name' => 'BadExpectedStreamToBlock', 'description' => 'The stream did not return all data requested (possibly because it is a non-blocking stream).'],
        0x80B50000 => ['name' => 'BadWouldBlock', 'description' => 'Non blocking behaviour is required and the operation would block.'],
        0x80B60000 => ['name' => 'BadSyntaxError', 'description' => 'A value had an invalid syntax.'],
        0x80B70000 => ['name' => 'BadMaxConnectionsReached', 'description' => 'The operation could not be finished because all available connections are in use.'],
        0x42080000 => ['name' => 'UncertainTransducerInManual', 'description' => 'The value may not be accurate because the transducer is in manual mode.'],
        0x42090000 => ['name' => 'UncertainSimulatedValue', 'description' => 'The value is simulated.'],
        0x420A0000 => ['name' => 'UncertainSensorCalibration', 'description' => 'The value may not be accurate due to a sensor calibration fault.'],
        0x420F0000 => ['name' => 'UncertainConfigurationError', 'description' => 'The value may not be accurate due to a configuration issue.'],
        0x04010000 => ['name' => 'GoodCascadeInitializationAcknowledged', 'description' => 'The value source supports cascade handshaking and the value has been Initialized based on an initialization request from a cascade secondary.'],
        0x04020000 => ['name' => 'GoodCascadeInitializationRequest', 'description' => 'The value source supports cascade handshaking and is requesting initialization of a cascade primary.'],
        0x04030000 => ['name' => 'GoodCascadeNotInvited', 'description' => 'The value source supports cascade handshaking, however, the source’s current state does not allow for cascade.'],
        0x04040000 => ['name' => 'GoodCascadeNotSelected', 'description' => 'The value source supports cascade handshaking, however, the source has not selected the corresponding cascade primary for use.'],
        0x04070000 => ['name' => 'GoodFaultStateActive', 'description' => 'There is a fault state condition active in the value source.'],
        0x04080000 => ['name' => 'GoodInitiateFaultState', 'description' => 'A fault state condition is being requested of the destination.'],
        0x04090000 => ['name' => 'GoodCascade', 'description' => 'The value is accurate, and the signal source supports cascade handshaking.'],
        0x80E70000 => ['name' => 'BadDataSetIdInvalid', 'description' => 'The DataSet specified for the DataSetWriter creation is invalid.'],
    ];


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

    public static function metadata(): array
    {
        return self::METADATA;
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

    public function getName(): ?string
    {
        return self::METADATA[$this->value]['name'] ?? null;
    }

    public function getDescription(): ?string
    {
        return self::METADATA[$this->value]['description'] ?? null;
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
