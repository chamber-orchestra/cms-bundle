<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * The sign-in page of the admin (routes `cms_login` and `cms_logout`, under the admin prefix the application imports the bundle routes with). The form posts to the same
 * path: the `form_login` of the admin firewall answers the POST, this action only draws the page and the error of the last attempt.
 */
final class AdminLoginController extends AbstractController
{
    #[Route('/login', name: 'cms_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $utils, Request $request): Response
    {
        if ($this->getUser()) {
            // already signed in: to the root of the admin (the sign-in path without its last segment)
            return $this->redirect(\substr($request->getBaseUrl().$request->getPathInfo(), 0, -\strlen('/login')) ?: '/');
        }

        $error = $utils->getLastAuthenticationError();

        return $this->render('@ChamberOrchestraCms/security/login.html.twig', [
            'last_username' => $utils->getLastUsername(),
            'error' => self::message($error),
        ]);
    }

    #[Route('/logout', name: 'cms_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by the logout key of the firewall.');
    }

    private static function message(?AuthenticationException $error): ?string
    {
        return match (true) {
            null === $error => null,
            $error instanceof TooManyLoginAttemptsAuthenticationException => 'security.error.throttled',
            $error instanceof InvalidCsrfTokenException => 'security.error.csrf',
            $error instanceof CustomUserMessageAccountStatusException => 'security.error.disabled',
            default => 'security.error.credentials',
        };
    }
}
