<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

/**
 * Renders a friendly 403 page when an authenticated user (e.g. a guest without
 * ROLE_ADMIN) tries to reach a forbidden area such as /admin/guest.
 *
 * Anonymous users are still redirected to the login form by the firewall entry
 * point; this handler only runs once a user is authenticated but lacks the
 * required role. The 403 status code is preserved.
 */
final class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return new Response(
            $this->twig->render('security/forbidden.html.twig'),
            Response::HTTP_FORBIDDEN,
        );
    }
}


