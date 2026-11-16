<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ArchitectureSmokeTest extends TestCase
{
    public function test_architecture_test_runner_is_properly_configured(): void
    {
        $this->assertTrue(true, 'Test runner PHPUnit 11 must be ready.');
        $this->assertSame('8.3', PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, 'PHP version must be 8.3.');
    }
}
