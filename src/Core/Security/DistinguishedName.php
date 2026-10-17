<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use Exception;
use phpseclib4\File\ASN1\Types\BaseString;
use phpseclib4\File\X509;
use RuntimeException;
use Stringable;

/**
 * Distinguished Name helpers on top of phpseclib 4
 *
 * phpseclib 4 returns X509::DN_ARRAY as ['rdnSequence' => [RDN, ...]], where each RDN
 * is a list of ['type' => OID, 'value' => ASN.1 string object].
 *
 * X509::DN_CANON is not used: in phpseclib 4.0.2 it lowercases the DN values of the
 * certificate object in place, which breaks later signature checks on that object.
 *
 * @internal
 */
final class DistinguishedName
{
    private const SHORT_NAMES = [
        'id-at-commonName' => 'CN',
        'id-at-organizationName' => 'O',
        'id-at-organizationalUnitName' => 'OU',
        'id-at-countryName' => 'C',
        'id-at-localityName' => 'L',
        'id-at-stateOrProvinceName' => 'ST',
        'id-domainComponent' => 'DC',
    ];

    /**
     * Normalise any DN accepted by X509::setSubjectDN() (DN_ARRAY output or a
     * ['CN' => 'name'] map) into the DN_ARRAY shape with decoded ASN.1 objects.
     *
     * @param array<int|string, mixed> $dn
     * @return array<int|string, mixed>
     */
    public static function normalize(array $dn): array
    {
        $x509 = new X509();
        $x509->setSubjectDN($dn);
        $normalized = $x509->getSubjectDN(X509::DN_ARRAY);

        if (!is_array($normalized)) {
            throw new RuntimeException('Failed to normalize DN');
        }

        return $normalized;
    }

    /**
     * Canonical form for comparisons: RDN order kept, attributes inside a multi-valued
     * RDN sorted, values converted to UTF-8, trimmed, whitespace-collapsed and lowercased.
     *
     * @param array<int|string, mixed> $dn DN in X509::DN_ARRAY format
     */
    public static function canonical(array $dn): string
    {
        $rdns = [];

        foreach (self::attributes($dn) as $rdn) {
            $parts = [];
            foreach ($rdn as [$type, $value]) {
                $value = strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
                $parts[] = $type . '=' . $value;
            }
            sort($parts);
            $rdns[] = implode('+', $parts);
        }

        return implode(',', $rdns);
    }

    /**
     * Human-readable form, e.g. "CN=localhost, O=Example"
     *
     * @param array<int|string, mixed> $dn DN in X509::DN_ARRAY format
     */
    public static function toString(array $dn): string
    {
        $parts = [];

        foreach (self::attributes($dn) as $rdn) {
            foreach ($rdn as [$type, $value]) {
                if ($value !== '') {
                    $parts[] = (self::SHORT_NAMES[$type] ?? $type) . '=' . $value;
                }
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<int|string, mixed> $dn
     * @return list<list<array{string, string}>> RDNs as lists of [type, value]
     */
    private static function attributes(array $dn): array
    {
        $sequence = $dn['rdnSequence'] ?? null;
        if (!is_iterable($sequence)) {
            throw new RuntimeException('Invalid DN: missing rdnSequence');
        }

        $result = [];
        foreach ($sequence as $rdn) {
            if (!is_iterable($rdn)) {
                continue;
            }
            $attributes = [];
            foreach ($rdn as $attr) {
                if (!is_array($attr) || !isset($attr['type'], $attr['value'])) {
                    continue;
                }
                $attributes[] = [self::stringify($attr['type']), self::valueToString($attr['value'])];
            }
            $result[] = $attributes;
        }

        return $result;
    }

    private static function valueToString(mixed $value): string
    {
        if ($value instanceof BaseString && $value->isConvertable()) {
            try {
                return (string) $value->toUTF8String();
            } catch (Exception) {
                // fall through to the raw value
            }
        }

        return self::stringify($value);
    }

    private static function stringify(mixed $value): string
    {
        return is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
    }
}
