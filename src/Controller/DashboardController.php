<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Controller;

use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\AdminAuth\AdminGuardResolver;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class DashboardController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly AdminSectionRegistryInterface $sectionRegistry,
        private readonly AdminGuardResolver $adminGuard,
    ) {}

    #[Get(path: '/admin')]
    #[Middleware(AdminAuthMiddleware::class)]
    public function index(
        Request $request,
    ): Response {
        $sections = $this->sectionRegistry->all();

        return $this->view->render('admin-panel::dashboard/index', [
            'sections' => $sections,
            'currentUser' => $this->adminGuard->guard()->user(),
        ]);
    }
}
