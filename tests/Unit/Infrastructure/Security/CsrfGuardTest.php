<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\CsrfGuard;
use PHPUnit\Framework\TestCase;

class CsrfGuardTest extends TestCase
{
    private array $sessionStorage = [];

    protected function setUp(): void
    {
        $this->sessionStorage = [];
    }

    public function testGenerateAndValidateToken(): void
    {
        $guard = new CsrfGuard($this->sessionStorage);

        $token = $guard->generateToken();
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // 32 bytes bin2hex = 64 chars
        $this->assertTrue($guard->validateToken($token));
        $this->assertFalse($guard->validateToken('invalid-token-12345'));
        $this->assertFalse($guard->validateToken(null));
    }

    public function testTokenInputHtml(): void
    {
        $guard = new CsrfGuard($this->sessionStorage);
        $token = $guard->getToken();

        $html = $guard->tokenInputHtml();
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
        $this->assertStringContainsString('value="' . $token . '"', $html);
    }

    public function testTokenPersistsInSessionStorage(): void
    {
        $guard1 = new CsrfGuard($this->sessionStorage);
        $token1 = $guard1->getToken();

        $guard2 = new CsrfGuard($this->sessionStorage);
        $this->assertSame($token1, $guard2->getToken());
        $this->assertTrue($guard2->validateToken($token1));
    }
}
