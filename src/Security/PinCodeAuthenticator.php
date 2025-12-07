<?php
declare(strict_types=1);

namespace App\Security;

use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Throwable;
use function preg_match;

final class PinCodeAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly RouterInterface $router,
        private readonly UserProviderInterface $userProvider
    ) {}

    public function authenticate(Request $request): Passport
    {
        $email = (string) $request->request->get('email', '');
        $pin   = (string) $request->request->get('pin', '');

        // Basale check op 6 cijfers — eigen voorkeur, validatie volgt via hasher.
        if (!preg_match('/^\d{6}$/', $pin)) {
            throw new CustomUserMessageAuthenticationException('Ongeldige pincode (6 cijfers).');
        }

        return new Passport(
            new UserBadge(
                $email,
                function (string $userIdentifier): UserInterface {
                    try {
                        // Hier gaat de daadwerkelijke DB-call via je UserProvider
                        return $this->userProvider->loadUserByIdentifier($userIdentifier);
                    } catch (DbalException $e) {
                        // Database-issue → Vriendelijke melding voor de gebruiker
                        throw new CustomUserMessageAuthenticationException(
                            'Inloggen is tijdelijk niet mogelijk. Probeer het later opnieuw.'
                        );
                    } catch (Throwable $e) {
                        // Catch-all voor andere technische fouten (optioneel)
                        throw new CustomUserMessageAuthenticationException(
                            'Er is een technisch probleem opgetreden tijdens het inloggen.'
                        );
                    }
                }
            ),
            new PasswordCredentials($pin),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
            ]
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
