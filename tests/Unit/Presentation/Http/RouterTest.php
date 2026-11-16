<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Presentation\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function test_dispatches_registered_get_route(): void
    {
        $router = new Router();
        $router->get('/dashboard', fn () => '<h1>Dashboard KSM</h1>');

        $response = $router->dispatch('GET', '/dashboard');

        $this->assertSame(200, $response['status']);
        $this->assertSame('<h1>Dashboard KSM</h1>', $response['body']);
    }

    public function test_returns_404_when_route_not_found(): void
    {
        $router = new Router();
        $router->get('/dashboard', fn () => 'OK');

        $response = $router->dispatch('GET', '/tidak-ada');

        $this->assertSame(404, $response['status']);
    }

    public function test_strips_query_string_when_matching_route(): void
    {
        $router = new Router();
        $router->get('/api/stream', fn () => 'SSE_OK');

        $response = $router->dispatch('GET', '/api/stream?channel=antrean');

        $this->assertSame(200, $response['status']);
        $this->assertSame('SSE_OK', $response['body']);
    }
}
