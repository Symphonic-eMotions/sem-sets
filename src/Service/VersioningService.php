<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\User;
use App\Repository\DocumentVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;

final class VersioningService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentVersionRepository $versionRepo,
        private readonly FilesystemOperator $uploadsStorage // alias op uploads.storage
    ) {}

    public function createSnapshot(Document $doc, string $json, ?string $changelog, ?User $author): DocumentVersion
    {
        // naïeve JSON validatie
        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('JSON is niet geldig: '.json_last_error_msg());
        }

        $verNr = $this->versionRepo->nextVersionNr($doc);

        $v = new DocumentVersion();
        $v->setDocument($doc);
        $v->setVersionNr($verNr);
        $v->setJsonText($json);
        $v->setAuthor($author);
        $v->setChangelog($changelog);

        $this->em->persist($v);
        $this->em->flush();

        // schrijf naar filesystem: versions/{nr}/document.json en latest/document.json
        $base = sprintf('sets/%d', $doc->getId());
        $this->uploadsStorage->write("$base/versions/$verNr/document.json", $json);
        $this->uploadsStorage->write("$base/latest/document.json", $json);

        // HEAD bijwerken
        $doc->setHeadVersion($v);
        $this->em->flush();

        return $v;
    }
}
