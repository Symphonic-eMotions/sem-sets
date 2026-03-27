<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SemSymAuthControllerTest extends DatabaseWebTestCase
{
    public function testLoginRequiresEmailAndApiKey(): void
    {
        $this->client->request(
            'POST',
            '/api/sem-sym/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => '', 'apiKey' => ''])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertJsonStringEqualsJsonString(
            '{"error":"email and apiKey are required"}',
            $this->client->getResponse()->getContent() ?: ''
        );
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $user = $this->createUser(email: 'api@example.com');
        $user
            ->setApiKey('known-key')
            ->setInstanceId('b2f8adca-71c8-44af-9b16-4a467dbf35b1')
            ->setIsActive(true);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/api/sem-sym/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'api@example.com', 'apiKey' => 'wrong-key'])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertJsonStringEqualsJsonString(
            '{"error":"Invalid credentials"}',
            $this->client->getResponse()->getContent() ?: ''
        );
    }

    public function testLoginReturnsInstanceIdForValidCredentials(): void
    {
        $user = $this->createUser(email: 'api@example.com');
        $user
            ->setApiKey('known-key')
            ->setInstanceId('b2f8adca-71c8-44af-9b16-4a467dbf35b1')
            ->setIsActive(true);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/api/sem-sym/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'api@example.com', 'apiKey' => 'known-key'])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertJsonStringEqualsJsonString(
            '{"instanceId":"b2f8adca-71c8-44af-9b16-4a467dbf35b1","email":"api@example.com"}',
            $this->client->getResponse()->getContent() ?: ''
        );
    }
}
