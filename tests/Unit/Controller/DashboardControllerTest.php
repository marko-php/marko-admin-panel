<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Tests\Unit\Controller;

use LogicException;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\Discovery\AdminSectionDefinition;
use Marko\Admin\MenuItem;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\AdminPanel\Config\AdminPanelConfig;
use Marko\AdminPanel\Controller\DashboardController;
use Marko\AdminPanel\Menu\AdminMenuBuilder;
use Marko\AdminPanel\Tests\Fixtures\FixedAdminGuardResolver;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeGuard;
use Marko\View\ViewInterface;
use ReflectionMethod;

// Stub for ViewInterface
class StubView implements ViewInterface
{
    public string $lastTemplate = '';

    /** @var array<string, mixed> */
    public array $lastData = [];

    public function render(
        string $template,
        array $data = [],
    ): Response {
        $this->lastTemplate = $template;
        $this->lastData = $data;

        return Response::html('<html>rendered</html>');
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string {
        $this->lastTemplate = $template;
        $this->lastData = $data;

        return '<html>rendered</html>';
    }
}

// Stub for AdminSectionRegistryInterface
class StubSectionRegistry implements AdminSectionRegistryInterface
{
    /** @var array<AdminSectionInterface> */
    private array $sections = [];

    public function register(
        AdminSectionInterface $section,
    ): void {
        $this->sections[$section->getId()] = $section;
    }

    public function registerDefinition(
        AdminSectionDefinition $definition,
    ): void {
        throw new LogicException('Register built sections with register() in this test');
    }

    public function all(): array
    {
        return array_values($this->sections);
    }

    public function get(
        string $id,
    ): AdminSectionInterface {
        return $this->sections[$id];
    }
}

// Stub for AdminSectionInterface
class StubAdminSection implements AdminSectionInterface
{
    /**
     * @param array<MenuItemInterface> $menuItems
     */
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $icon = 'default',
        private readonly int $sortOrder = 0,
        private readonly array $menuItems = [],
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getMenuItems(): array
    {
        return $this->menuItems;
    }
}

function createDashboardSection(
    string $id,
    string $label,
    int $sortOrder = 0,
): StubAdminSection {
    return new StubAdminSection(
        id: $id,
        label: $label,
        sortOrder: $sortOrder,
        menuItems: [
            new MenuItem(
                id: "$id-index",
                label: $label,
                url: "/admin/$id",
                permission: "$id.view",
            ),
        ],
    );
}

function createDashboardAdminUser(
    bool $superAdmin = false,
    array $permissionKeys = [],
): AdminUser {
    $role = new Role();
    $role->id = 1;
    $role->name = $superAdmin ? 'Super Admin' : 'Editor';
    $role->slug = $superAdmin ? 'super-admin' : 'editor';
    $role->isSuperAdmin = $superAdmin ? '1' : '0';

    $user = new AdminUser();
    $user->id = 5;
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin User';
    $user->setRoles(roles: [$role], permissionKeys: $permissionKeys);

    return $user;
}

function createDashboardController(
    StubView $view,
    StubSectionRegistry $registry,
    FakeGuard $guard,
    string $pageTitle = 'Marko Admin',
): DashboardController {
    return new DashboardController(
        view: $view,
        menuBuilder: new AdminMenuBuilder($registry),
        adminGuard: new FixedAdminGuardResolver($guard),
        config: new AdminPanelConfig(new FakeConfigRepository([
            'admin-panel.page_title' => $pageTitle,
        ])),
    );
}

it('requires authentication via AdminAuthMiddleware for dashboard', function (): void {
    $middlewareAttributes = (new ReflectionMethod(DashboardController::class, 'index'))->getAttributes(
        Middleware::class,
    );

    expect($middlewareAttributes)->toHaveCount(1)
        ->and($middlewareAttributes[0]->newInstance()->middleware)->toContain(AdminAuthMiddleware::class);
});

it('renders dashboard template with registered sections on GET /admin', function (): void {
    $view = new StubView();
    $registry = new StubSectionRegistry();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    $registry->register(createDashboardSection('catalog', 'Catalog'));
    $registry->register(createDashboardSection('content', 'Content'));
    $guard->setUser(createDashboardAdminUser(superAdmin: true));

    $response = createDashboardController($view, $registry, $guard)->index(new Request());

    expect($response->statusCode())->toBe(200)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toContain('text/html')
        ->and($view->lastTemplate)->toBe('admin-panel::dashboard/index')
        ->and($view->lastData)->toHaveKey('sections')
        ->and($view->lastData['sections'])->toHaveCount(2);
});

it('passes admin sections to dashboard template for display', function (): void {
    $view = new StubView();
    $registry = new StubSectionRegistry();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    $registry->register(createDashboardSection('catalog', 'Catalog', 10));
    $registry->register(createDashboardSection('content', 'Content', 20));
    $registry->register(createDashboardSection('settings', 'Settings', 30));
    $guard->setUser(createDashboardAdminUser(superAdmin: true));

    createDashboardController($view, $registry, $guard)->index(new Request());

    $sections = $view->lastData['sections'];

    expect($sections)->toHaveCount(3)
        ->and($sections[0]->getId())->toBe('catalog')
        ->and($sections[0]->getLabel())->toBe('Catalog')
        ->and($sections[1]->getId())->toBe('content')
        ->and($sections[2]->getId())->toBe('settings');
});

it('shows a low-privilege admin only the sections they have permission for', function (): void {
    $view = new StubView();
    $registry = new StubSectionRegistry();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    $registry->register(createDashboardSection('catalog', 'Catalog'));
    $registry->register(createDashboardSection('content', 'Content'));
    $registry->register(createDashboardSection('settings', 'Settings'));
    $guard->setUser(createDashboardAdminUser(permissionKeys: ['content.view']));

    createDashboardController($view, $registry, $guard)->index(new Request());

    $sections = $view->lastData['sections'];

    expect($sections)->toHaveCount(1)
        ->and($sections[0]->getId())->toBe('content');
});

it('shows no sections to an authenticated user that is not an admin user', function (): void {
    $view = new StubView();
    $registry = new StubSectionRegistry();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    $registry->register(createDashboardSection('catalog', 'Catalog'));
    $guard->setUser(new FakeAuthenticatable(id: 9));

    createDashboardController($view, $registry, $guard)->index(new Request());

    expect($view->lastData['sections'])->toBe([]);
});

it('passes current user to base layout template', function (): void {
    $view = new StubView();
    $registry = new StubSectionRegistry();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    $user = createDashboardAdminUser(superAdmin: true);
    $guard->setUser($user);

    createDashboardController($view, $registry, $guard)->index(new Request());

    expect($view->lastData)->toHaveKey('currentUser')
        ->and($view->lastData['currentUser'])->toBe($user)
        ->and($view->lastData['currentUser']->getAuthIdentifier())->toBe(5);
});

it('passes the configured admin-panel.page_title to the template as pageTitle', function (): void {
    $view = new StubView();
    $registry = new StubSectionRegistry();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    $guard->setUser(createDashboardAdminUser(superAdmin: true));

    createDashboardController($view, $registry, $guard, pageTitle: 'Acme Back Office')->index(new Request());

    expect($view->lastData)->toHaveKey('pageTitle')
        ->and($view->lastData['pageTitle'])->toBe('Acme Back Office');
});
