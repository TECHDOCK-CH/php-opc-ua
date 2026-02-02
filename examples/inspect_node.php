<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Types\AttributeId;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\ReadValueId;
use TechDock\OpcUa\Core\Types\DataValue;
use TechDock\OpcUa\Core\Types\Variant;
use TechDock\OpcUa\Core\Types\DateTime;

function formatValue($val): string {
    if ($val === null) return 'null';
    if (is_bool($val)) return $val ? 'true' : 'false';
    if (is_array($val)) return json_encode($val);
    if ($val instanceof Variant) {
        $typeName = $val->type->name;
        $v = formatValue($val->value);
        return "Variant(Scalar<{$typeName}>, value: {$v})";
    }
    return (string)$val;
}

function formatDate(?DateTime $dt): string {
    if ($dt === null) return 'null';
    // DateTime in this library seems to have a toString or similar.
    // Let's check src/Core/Types/DateTime.php if possible, or assume it works like other types.
    return (string)$dt;
}

$endpointUrl = 'opc.tcp://127.0.0.1:4840';
$targetNodeId = NodeId::string(1, 'Alarm high downflow');

$client = new OpcUaClient($endpointUrl, MessageSecurityMode::None);

try {
    $client->connect();
    $session = $client->createSession();
    $session->create();
    $session->activate();

    $attributes = [
        AttributeId::NODE_ID,
        AttributeId::NODE_CLASS,
        AttributeId::BROWSE_NAME,
        AttributeId::DISPLAY_NAME,
        AttributeId::DESCRIPTION,
        AttributeId::WRITE_MASK,
        AttributeId::USER_WRITE_MASK,
        AttributeId::VALUE,
    ];

    $readValues = array_map(fn($attrId) => ReadValueId::attribute($targetNodeId, $attrId), $attributes);
    
    /** @var DataValue[] $results */
    $results = $session->read($readValues);

    $data = [];
    foreach ($attributes as $index => $attrId) {
        $data[$attrId] = $results[$index] ?? null;
    }

    echo "+-------------------- Attribute List --------------------+\n";
    printf("| NodeId...............: %s |\n", $data[AttributeId::NODE_ID]?->value?->value ?? $targetNodeId->toString());
    
    $nodeClass = $data[AttributeId::NODE_CLASS]?->value?->value ?? 0;
    $nodeClassLabels = [1 => 'Object', 2 => 'Variable', 4 => 'Method', 8 => 'ObjectType', 16 => 'VariableType', 32 => 'ReferenceType', 64 => 'DataType', 128 => 'View'];
    $nodeClassLabel = $nodeClassLabels[$nodeClass] ?? "Unknown($nodeClass)";
    printf("| NodeClass............: %s (%d)                   |\n", $nodeClassLabel, $nodeClass);

    $browseName = $data[AttributeId::BROWSE_NAME]?->value?->value;
    printf("| BrowseName...........: %s           |\n", $browseName ? $browseName->toString() : 'null');

    $displayName = $data[AttributeId::DISPLAY_NAME]?->value?->value;
    $dnText = $displayName ? "locale=" . ($displayName->locale ?? 'null') . " text=" . ($displayName->text ?? 'null') : 'null';
    printf("| DisplayName..........: %s |\n", $dnText);

    $description = $data[AttributeId::DESCRIPTION]?->value?->value;
    $descText = $description ? "locale=" . ($description->locale ?? 'null') . " text=" . ($description->text ?? 'null') : 'locale=null text=null';
    printf("| Description..........: %s           |\n", $descText);

    printf("| WriteMask............: (%d)                             |\n", $data[AttributeId::WRITE_MASK]?->value?->value ?? 0);
    printf("| UserWriteMask........: (%d)                             |\n", $data[AttributeId::USER_WRITE_MASK]?->value?->value ?? 0);

    $valueAttr = $data[AttributeId::VALUE];
    echo "| Value................: { /* DataValue */               |\n";
    if ($valueAttr) {
        printf("|   value..............: %s\n", formatValue($valueAttr->value));
        printf("|   statusCode.........: %s\n", $valueAttr->statusCode ? $valueAttr->statusCode->toString() : 'Good (0x00000000)');
        printf("|   serverTimestamp....: %s\n", formatDate($valueAttr->serverTimestamp));
        printf("|   sourceTimestamp....: %s\n", formatDate($valueAttr->sourceTimestamp));
    } else {
        echo "|   (null)\n";
    }
    echo "| }                                                      |\n";
    echo "+--------------------------------------------------------+\n";

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    if (isset($client)) {
        $client->disconnect();
    }
}
