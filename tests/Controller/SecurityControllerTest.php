<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SecurityControllerTest extends DatabaseWebTestCase
{
    public function testLoginPageLoads(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSelectorTextContains('h1', 'Symphonic eMotions Cloud');
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="pin"]');
    }

    public function testProtectedDashboardRedirectsAnonymousUserToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testValidLoginRedirectsToDashboard(): void
    {
        $this->createUser(email: 'admin@example.com', pin: '123456');

        $crawler = $this->client->request('GET', '/login');

        $this->client->submitForm('Inloggen', [
            'email' => 'admin@example.com',
            'pin' => '123456',
            '_csrf_token' => $crawler->filter('input[name="_csrf_token"]')->attr('value'),
        ]);

        self::assertResponseRedirects('/');

        $this->client->followRedirect();
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
