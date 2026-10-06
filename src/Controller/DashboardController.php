<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Controller;

use Marko\AdminAuth\AdminGuardResolver;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\AdminPanel\Config\AdminPanelConfigInterface;
use Marko\AdminPanel\Menu\AdminMenuBuilderInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class DashboardController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly AdminMenuBuilderInterface $menuBuilder,
        private readonly AdminGuardResolver $adminGuard,
        private readonly AdminPanelConfigInterface $config,
    ) {}

    /**
     * Shows only the sections the current admin user can reach. A user that is
     * not an admin user sees none; AdminAuthMiddleware already rejects them,
     * so this only keeps the page closed if it is reached without it.
     */
    #[Get(path: '/admin')]
    #[Middleware(AdminAuthMiddleware::class)]
    public function index(
        Request $request,
    ): Response {
        $user = $this->adminGuard->guard()->user();

        $sections = $user instanceof AdminUserInterface
            ? $this->menuBuilder->buildDashboardSections($user)
            : [];

        return $this->view->render('admin-panel::dashboard/index', [
            'sections' => $sections,
            'currentUser' => $user,
            'pageTitle' => $this->config->getPageTitle(),
        ]);
    }
}
