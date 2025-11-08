<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Sanity check test to verify PHPUnit setup
 */
final class SanityTest extends TestCase
{
    public function testPhpVersion(): void
    {
        $this->assertGreaterThanOrEqual('8.4.0', PHP_VERSION, 'PHP 8.4 or higher is required');
    }

    public function testRequiredExtensions(): void
    {
        $this->assertTrue(extension_loaded('sockets'), 'ext-sockets is required');
    }

    public function testAutoloading(): void
    {
        $this->assertTrue(
            class_exists('TechDock\OpcUa\Tests\Unit\SanityTest'),
            'PSR-4 autoloading is working'
        );
    }

    public function testBasicArithmetic(): void
    {
        $this->assertSame(4, 2 + 2, 'Basic test framework is working');
    }
}
