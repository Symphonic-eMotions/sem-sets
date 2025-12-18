<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use League\Flysystem\FilesystemException;
use Symfony\Component\Security\Core\User\UserInterface;

final class DocumentSnapshotService
{
    public function __construct(
        private readonly DocumentPayloadBuilder $payloadBuilder,
        private readonly VersioningService $versioningService,
    ) {}

    /**
     * @throws FilesystemException
     */
    public function createSnapshot(Document $doc, string $reason, ?UserInterface $user): void
    {
        $json = $this->payloadBuilder->buildPayloadJson($doc);
        $this->versioningService->createSnapshot($doc, $json, $reason, $user);
    }
}
