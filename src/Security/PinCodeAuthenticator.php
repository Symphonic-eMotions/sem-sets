<?php
declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class PinCodeAuthenticator extends AbstractLoginFormAuthenticator
{
    public const string LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly RouterInterface $router,
        private readonly UserProviderInterface $userProvider
    ) {}

    public function authenticate(Request $request): Passport
    {
        $email = (string) $request->request->get('email', '');
        $pin   = (string) $request->request->get('pin', '');

        $request->getSession()->set(Security::LAST_USERNAME, $email);

        // Basale check op 6 cijfers — eigen voorkeur, validatie volgt via hasher.
        if (!\preg_match('/^\d{6}$/', $pin)) {
            throw new CustomUserMessageAuthenticationException('Ongeldige pincode (6 cijfers).');
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($pin), // gebruikt standaard password hasher
            [ new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')) ]
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate(self::LOGIN_ROUTE);
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('app_dashboard'));
    }
}
