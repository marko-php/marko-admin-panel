<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Controller;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminAuth\AdminGuardResolver;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\View\ViewInterface;

/**
 * Logs admins in and out on the admin guard (admin-auth.guard), never the
 * app's default guard, so only AdminUserProvider users can sign in here.
 * The login and logout POST routes are CSRF-protected by the global
 * CsrfMiddleware that marko/security registers.
 */
readonly class LoginController
{
    public function __construct(
        private ViewInterface $view,
        private AdminGuardResolver $adminGuard,
        private AdminConfigInterface $adminConfig,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get(path: '/admin/login')]
    public function showLoginForm(
        Request $request,
    ): Response {
        if ($this->adminGuard->guard()->check()) {
            return Response::redirect($this->adminConfig->getRoutePrefix());
        }

        return $this->view->render('admin-panel::auth/login', [
            'loginUrl' => $this->adminConfig->getRoutePrefix() . '/login',
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post(path: '/admin/login')]
    public function authenticate(
        Request $request,
    ): Response {
        $credentials = [
            'email' => $request->post('email'),
            'password' => $request->post('password'),
        ];

        if ($this->adminGuard->guard()->attempt($credentials)) {
            return Response::redirect($this->adminConfig->getRoutePrefix());
        }

        return $this->view->render('admin-panel::auth/login', [
            'loginUrl' => $this->adminConfig->getRoutePrefix() . '/login',
            'error' => 'Invalid email or password.',
        ]);
    }

    #[Post(path: '/admin/logout')]
    public function logout(
        Request $request,
    ): Response {
        $this->adminGuard->guard()->logout();

        return Response::redirect($this->adminConfig->getRoutePrefix() . '/login');
    }
}
