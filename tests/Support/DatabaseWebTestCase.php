<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\PayloadBlock;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();

        $this->resetDatabase();
        $this->resetStorageDirectories();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->entityManager)) {
            $this->entityManager->clear();
            $this->entityManager->close();
        }

        static::ensureKernelShutdown();
    }

    protected function createUser(
        string $email = 'test@example.com',
        string $pin = '123456',
        array $roles = ['ROLE_ADMIN'],
    ): User {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user
            ->setEmail($email)
            ->setPassword($hasher->hashPassword($user, $pin))
            ->setRoles($roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function createPublishedDocument(User $user, string $slug = 'published-set', string $title = 'Published Set'): Document
    {
        $document = $this->createDocument($user, $slug, $title);
        $document->setPublished(true);

        $version = new DocumentVersion();
        $version
            ->setDocument($document)
            ->setVersionNr(1)
            ->setJsonText('{"ok":true}')
            ->setAuthor($user)
            ->setChangelog('initial');

        $this->entityManager->persist($version);
        $this->entityManager->flush();

        $document->setHeadVersion($version);
        $this->entityManager->flush();

        return $document;
    }

    protected function createDocument(User $user, string $slug = 'test-set', string $title = 'Test Set'): Document
    {
        $document = new Document();
        $document
            ->setTitle($title)
            ->setSlug($slug)
            ->setCreatedBy($user)
            ->setUpdatedBy($user);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    protected function createPayloadBlocks(array $names = ['niveauTrack', 'niveauSet', 'masterEffects']): void
    {
        foreach ($names as $name) {
            $this->entityManager->persist(new PayloadBlock($name));
        }

        $this->entityManager->flush();
    }

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        if ($metadata !== []) {
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }
    }

    private function resetStorageDirectories(): void
    {
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $filesystem = new Filesystem();

        foreach (['var/storage/default', 'var/uploads'] as $relativePath) {
            $absolutePath = $projectDir . '/' . $relativePath;
            $filesystem->remove($absolutePath);
            $filesystem->mkdir($absolutePath);
        }
    }
}
