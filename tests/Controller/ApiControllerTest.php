<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiControllerTest extends DatabaseWebTestCase
{
    public function testListSetsReturnsOnlyPublishedDocuments(): void
    {
        $user = $this->createUser();
        $published = $this->createPublishedDocument($user, 'published-set', 'Published Set');
        $this->createDocument($user, 'draft-set', 'Draft Set');

        $this->client->request('GET', '/api/sets');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['items']);
        self::assertSame('published-set', $payload['items'][0]['slug']);
        self::assertSame('Published Set', $payload['items'][0]['title']);
        self::assertStringEndsWith('/api/sets/published-set.json', $payload['items'][0]['jsonUrl']);
        self::assertStringEndsWith(
            sprintf('/api/sets/%s/assets', $published->getSlug()),
            $payload['items'][0]['assetsBase']
        );
    }

    public function testGetSetJsonReturns404WithoutHeadVersion(): void
    {
        $user = $this->createUser();
        $document = $this->createDocument($user, 'missing-head', 'Missing Head');
        $document->setPublished(true);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/sets/missing-head.json');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testPatchPartRampRejectsInvalidJson(): void
    {
        $user = $this->createUser();
        $document = $this->createDocument($user);

        $this->client->request(
            'PATCH',
            sprintf('/api/documents/%d/tracks/track-1/parts/part-1', $document->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: 'not-json'
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertJsonStringEqualsJsonString(
            '{"error":"Invalid JSON"}',
            $this->client->getResponse()->getContent() ?: ''
        );
    }

    public function testGetSetJsonAddsInstrumentVolumeWhenMissing(): void
    {
        $user = $this->createUser();
        $document = $this->createPublishedDocument($user, 'compat-set', 'Compat Set');

        $json = json_encode([
            'instrumentsConfig' => [[
                'trackId' => 'track-1',
                'trackVolume' => -7.25,
            ]],
        ], JSON_THROW_ON_ERROR);

        $storage = static::getContainer()->get('uploads.storage');
        $storage->write(sprintf('sets/%d/latest/document.json', $document->getId()), $json);

        $this->client->request('GET', '/api/sets/compat-set.json');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = $this->client->getResponse();
        self::assertInstanceOf(StreamedResponse::class, $response);
        $callback = $response->getCallback();
        self::assertIsCallable($callback);

        ob_start();
        $callback();
        $content = (string) ob_get_clean();

        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        $trackConfig = $payload['instrumentsConfig'][0];

        self::assertSame(-7.25, $trackConfig['trackVolume']);
        self::assertSame(-7.25, $trackConfig['instrumentVolume']);
    }
}
