<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Exception\AccountDeactivatedException;
use App\Domain\Security\Exception\InvalidCredentialsException;
use App\Domain\Security\Exception\RateLimitExceededException;
use App\Infrastructure\Security\CsrfGuard;
use App\Infrastructure\Security\SessionAuthService;
use App\Presentation\View\ViewRenderer;

final class AuthController
{
    public function __construct(
        private readonly SessionAuthService $authService,
        private readonly CsrfGuard $csrfGuard,
        private readonly ViewRenderer $viewRenderer,
    ) {
    }

    public function showLogin(?string $errorMessage = null): Response
    {
        $token = $this->csrfGuard->getToken();
        $csrfInput = $this->csrfGuard->tokenInputHtml();

        $body = $this->viewRenderer->render('auth/login', [
            'judul' => 'Login SIMRS Medars',
            'csrf_token' => $token,
            'csrf_input' => $csrfInput,
            'error' => $errorMessage,
        ], withLayout: false);

        return Response::html($body, 200);
    }

    /**
     * @param array<string, mixed> $postData
     */
    public function postLogin(array $postData, string $ipAddress = ''): Response
    {
        $token = (string) ($postData['_csrf_token'] ?? '');
        if (!$this->csrfGuard->validateToken($token)) {
            return Response::html(
                $this->viewRenderer->render('auth/login', [
                    'judul' => 'Login SIMRS Medars',
                    'csrf_token' => $this->csrfGuard->getToken(),
                    'csrf_input' => $this->csrfGuard->tokenInputHtml(),
                    'error' => 'Token keamanan CSRF tidak valid. Silakan muat ulang halaman.',
                ], withLayout: false),
                403
            );
        }

        $username = trim((string) ($postData['username'] ?? ''));
        $password = (string) ($postData['password'] ?? '');

        try {
            $this->authService->login($username, $password, $ipAddress);
            return Response::redirect('/pendaftaran');
        } catch (InvalidCredentialsException $e) {
            return Response::html(
                $this->viewRenderer->render('auth/login', [
                    'judul' => 'Login SIMRS Medars',
                    'csrf_token' => $this->csrfGuard->getToken(),
                    'csrf_input' => $this->csrfGuard->tokenInputHtml(),
                    'error' => $e->getMessage(),
                ], withLayout: false),
                401
            );
        } catch (AccountDeactivatedException $e) {
            return Response::html(
                $this->viewRenderer->render('auth/login', [
                    'judul' => 'Login SIMRS Medars',
                    'csrf_token' => $this->csrfGuard->getToken(),
                    'csrf_input' => $this->csrfGuard->tokenInputHtml(),
                    'error' => $e->getMessage(),
                ], withLayout: false),
                403
            );
        } catch (RateLimitExceededException $e) {
            return Response::html(
                $this->viewRenderer->render('auth/login', [
                    'judul' => 'Login SIMRS Medars',
                    'csrf_token' => $this->csrfGuard->getToken(),
                    'csrf_input' => $this->csrfGuard->tokenInputHtml(),
                    'error' => $e->getMessage(),
                ], withLayout: false),
                429
            );
        }
    }

    public function logout(string $ipAddress = ''): Response
    {
        $this->authService->logout($ipAddress);
        return Response::redirect('/auth/login');
    }
}
