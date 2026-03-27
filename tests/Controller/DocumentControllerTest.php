<?php

namespace App\Tests\Controller;

use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DocumentControllerTest extends DatabaseWebTestCase
{
    public function testDownloadBundleZip(): void
    {
        $this->createPayloadBlocks();
        $user = $this->createUser();
        $doc = $this->createDocument($user);
        $this->client->loginUser($user);

        // Request the ZIP bundle
        $this->client->request('GET', sprintf('/documents/%d/bundle.zip', $doc->getId()));

        $response = $this->client->getResponse();

        // Assertions
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.zip', $response->headers->get('Content-Disposition'));

        // For BinaryFileResponse, getContent() is empty/false. We rely on headers and status code.
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
    }
}
