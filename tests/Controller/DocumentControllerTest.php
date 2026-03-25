<?php

namespace App\Tests\Controller;

use App\Entity\Document;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DocumentControllerTest extends WebTestCase
{
    private EntityManagerInterface|null $entityManager = null;
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        // Start transaction for cleanup
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback to keep database clean
        if ($this->entityManager) {
            $this->entityManager->rollback();
            $this->entityManager->close();
            $this->entityManager = null;
        }
        parent::tearDown();
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }

    private function createTestDocument(User $user): Document
    {
        $doc = new Document();
        $doc->setTitle('Test Set');
        $doc->setSlug('test-set');
        $doc->setCreatedBy($user);
        $doc->setUpdatedBy($user);
        $this->entityManager->persist($doc);
        $this->entityManager->flush();
        return $doc;
    }

    private function createPayloadBlocks(): void
    {
        $blocks = ['niveauTrack', 'niveauSet', 'masterEffects'];
        foreach ($blocks as $name) {
            $block = new \App\Entity\PayloadBlock($name);
            $this->entityManager->persist($block);
        }
        $this->entityManager->flush();
    }

    public function testDownloadBundleZip(): void
    {
        $this->createPayloadBlocks();
        $user = $this->createTestUser();
        $doc = $this->createTestDocument($user);
        $this->client->loginUser($user);

        $this->client->loginUser($user, 'main'); // 'main' firewall context

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
