<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

final class CsrfGuard
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(
        private array &$session,
    ) {
    }

    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session[self::SESSION_KEY] = $token;
        return $token;
    }

    public function getToken(): string
    {
        if (empty($this->session[self::SESSION_KEY]) || !is_string($this->session[self::SESSION_KEY])) {
            return $this->generateToken();
        }

        return $this->session[self::SESSION_KEY];
    }

    public function validateToken(?string $submittedToken): bool
    {
        if ($submittedToken === null || $submittedToken === '') {
            return false;
        }

        $sessionToken = $this->session[self::SESSION_KEY] ?? null;
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    public function tokenInputHtml(): string
    {
        $token = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::SESSION_KEY . '" value="' . $token . '">';
    }
}
