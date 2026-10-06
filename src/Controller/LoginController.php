<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Controller;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminAuth\AdminGuardResolver;
use Marko\Authentication\Exceptions\TooManyLoginAttemptsException;
use Marko\RateLimiter\Attributes\RateLimit;
use Marko\RateLimiter\Middleware\RateLimitMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
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
 *
 * Login is throttled twice: the admin guard's SessionGuard locks out an
 * email for a client after repeated failures (authentication.throttle), and
 * RateLimitMiddleware caps login POSTs per client IP across all emails, so
 * one address cannot spray guesses over many accounts.
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
    #[Middleware(RateLimitMiddleware::class)]
    #[RateLimit(maxAttempts: 10, decaySeconds: 60, name: 'admin-login')]
    public function authenticate(
        Request $request,
    ): Response {
        $credentials = [
            'email' => $request->post('email'),
            'password' => $request->post('password'),
        ];

        try {
            if ($this->adminGuard->guard()->attempt($credentials)) {
                return Response::redirect($this->adminConfig->getRoutePrefix());
            }
        } catch (TooManyLoginAttemptsException $exception) {
            return $this->view->render('admin-panel::auth/login', [
                'loginUrl' => $this->adminConfig->getRoutePrefix() . '/login',
                'csrfToken' => $this->csrfTokenManager->get(),
                'error' => 'Too many login attempts. Please try again in ' . $exception->getRetryAfter() . ' seconds.',
            ])->withStatus(429)->withHeaders($exception->getHeaders());
        }

        return $this->view->render('admin-panel::auth/login', [
            'loginUrl' => $this->adminConfig->getRoutePrefix() . '/login',
            'csrfToken' => $this->csrfTokenManager->get(),
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
