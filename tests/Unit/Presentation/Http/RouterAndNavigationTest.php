<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Presentation\Http\NavigationBuilder;
use App\Presentation\Http\Response;
use App\Presentation\Http\Router;
use App\Presentation\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class RouterAndNavigationTest extends TestCase
{
    public function testRouterDispatchesResponseObjectCorrectly(): void
    {
        $router = new Router();
        $router->get('/redirect-test', static fn(): Response => Response::redirect('/pendaftaran'));
        $router->get('/json-test', static fn(): Response => Response::json(['ok' => true], 201));

        $redirectRes = $router->dispatch('GET', '/redirect-test');
        $this->assertSame(302, $redirectRes['status']);
        $this->assertSame('/pendaftaran', $redirectRes['headers']['Location']);

        $jsonRes = $router->dispatch('GET', '/json-test');
        $this->assertSame(201, $jsonRes['status']);
        $this->assertSame('{"ok":true}', $jsonRes['body']);
    }

    public function testViewRendererBlocksPathTraversal(): void
    {
        $renderer = new ViewRenderer();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path traversal tidak diizinkan');
        $renderer->render('../../composer.json');
    }

    public function testNavigationBuilderFiltersByRbac(): void
    {
        $builder = new NavigationBuilder();

        $loketUser = new UserAccount(
            id: 2,
            username: 'loket1',
            passwordHash: 'hash',
            email: 'loket@medars.local',
            fullname: 'Petugas Loket',
            role: 'staff',
            access: ['pendaftaran'],
            cap: [],
            status: '1'
        );

        $loketNav = $builder->buildForUser($loketUser);
        $routes = array_column($loketNav, 'route');

        $this->assertContains('/', $routes);
        $this->assertContains('/pendaftaran', $routes);
        $this->assertNotContains('/dokter-ralan', $routes);
        $this->assertNotContains('/users', $routes);
        $this->assertNotContains('/settings', $routes);

        $adminUser = new UserAccount(
            id: 1,
            username: 'admin',
            passwordHash: 'hash',
            email: 'admin@medars.local',
            fullname: 'Super Admin',
            role: 'admin',
            access: ['all'],
            cap: ['all'],
            status: '1'
        );

        $adminNav = $builder->buildForUser($adminUser);
        $adminRoutes = array_column($adminNav, 'route');
        $this->assertContains('/pendaftaran', $adminRoutes);
        $this->assertContains('/dokter-ralan', $adminRoutes);
        $this->assertContains('/users', $adminRoutes);
        $this->assertContains('/settings', $adminRoutes);
    }
}
