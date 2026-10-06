<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Controller;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\View\ViewInterface;

/**
 * The login and logout POST routes are CSRF-protected by the global
 * CsrfMiddleware that marko/security registers.
 */
readonly class LoginController
{
    public function __construct(
        private ViewInterface $view,
        private GuardInterface $guard,
        private AdminConfigInterface $adminConfig,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get(path: '/admin/login')]
    public function showLoginForm(
        Request $request,
    ): Response {
        if ($this->guard->check()) {
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

        if ($this->guard->attempt($credentials)) {
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
        $this->guard->logout();

        return Response::redirect($this->adminConfig->getRoutePrefix() . '/login');
    }
}
