<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Entity\InstrumentPart;
use App\Repository\DocumentRepository;
use App\Service\DocumentSnapshotService;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route('/api')]
final class ApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ){
    }

    #[Route('/sets', name: 'api_sets', methods: ['GET'])]
    public function listSets(DocumentRepository $repo): JsonResponse
    {
        $docs = $repo->findPublished();
        $items = [];
        foreach ($docs as $d) {
            $items[] = [
                'slug'       => $d->getSlug(),
                'title'      => $d->getTitle(),
                'semVersion' => $d->getSemVersion(),
                'updatedAt'  => $d->getUpdatedAt()->format(DateTimeInterface::ATOM),
                'jsonUrl'    => $this->generateUrl('api_set_json', ['slug' => $d->getSlug()], 0),
                'assetsBase' => rtrim($this->generateUrl('api_set_asset', ['slug' => $d->getSlug(), 'filename' => ''], 0), '/'),
            ];
        }

        // Gebruik de helper van AbstractController voor JSON
        return $this->json(['items' => $items]);
    }

    // LET OP: niet 'json' noemen → naamconflict met AbstractController::json()

    /**
     * @throws FilesystemException
     */
    #[Route('/sets/{slug}.json', name: 'api_set_json', methods: ['GET'])]
    public function getSetJson(DocumentRepository $repo, FilesystemOperator $uploadsStorage, string $slug): StreamedResponse
    {
        /** @var ?Document $doc */
        $doc = $repo->findOnePublishedBySlug($slug); // helper (zie patch onder)
        if (!$doc || !$doc->getHeadVersion()) {
            throw $this->createNotFoundException();
        }

        $path = sprintf('sets/%d/latest/document.json', $doc->getId());
        if (!$uploadsStorage->fileExists($path)) {
            throw $this->createNotFoundException();
        }

        $response = new StreamedResponse(static function () use ($uploadsStorage, $path) {
            echo $uploadsStorage->read($path);
        });
        $response->headers->set('Content-Type', 'application/json');
        // eenvoudige cache headers; ETag/If-None-Match kun je later toevoegen
        $response->headers->set('Cache-Control', 'public, max-age=60');

        return $response;
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/sets/{slug}/assets/{filename}', name: 'api_set_asset', requirements: ['filename' => '.+'], methods: ['GET'])]
    public function getAsset(DocumentRepository $repo, FilesystemOperator $uploadsStorage, string $slug, string $filename): StreamedResponse
    {
        $doc = $repo->findOnePublishedBySlug($slug);
        if (!$doc) {
            throw $this->createNotFoundException();
        }

        $path = sprintf('sets/%d/assets/%s', $doc->getId(), $filename);
        if (!$uploadsStorage->fileExists($path)) {
            throw $this->createNotFoundException();
        }

        $response = new StreamedResponse(static function () use ($uploadsStorage, $path) {
            echo $uploadsStorage->read($path);
        });
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }

    #[Route('/documents/{id}/tracks/{trackId}/parts/{partId}', name: 'api_part_ramp_patch', methods: ['PATCH'])]
    public function patchPartRamp(
        Document $doc,
        string $trackId,
        string $partId,
        Request $req,
        DocumentSnapshotService $snapshotService,
    ): JsonResponse
    {
        // TODO: secure with token-based auth
//        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode((string) $req->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $rampUp = $data['rampSpeed'] ?? null;
        $rampDown = $data['rampSpeedDown'] ?? null;
        $baseVersion = $data['baseSetVersion'] ?? null;

        if (!is_numeric($rampUp) || !is_numeric($rampDown) || !is_numeric($baseVersion)) {
            return $this->json(['error' => 'Missing/invalid fields'], 422);
        }

        $rampUp = (float) $rampUp;
        $rampDown = (float) $rampDown;
        $baseVersion = (int) $baseVersion;

        if ($rampUp < 0.0 || $rampUp > 1.0 || $rampDown < 0.0 || $rampDown > 1.0) {
            return $this->json(['error' => 'Ramp out of range'], 422);
        }

        $currentHead = (int) ($doc->getHeadVersion()?->getVersionNr() ?? 0);
        if ($baseVersion !== $currentHead) {
            return $this->json([
                'error' => 'Version conflict',
                'serverSetVersion' => $currentHead,
            ], 409);
        }

        // Track vinden
        $track = null;
        foreach ($doc->getTracks() as $t) {
            if ($t->getTrackId() === $trackId) { $track = $t; break; }
        }
        if (!$track) {
            return $this->json(['error' => 'Track not found'], 404);
        }

        /** @var ?InstrumentPart $part */
        $part = null;
        foreach ($track->getInstrumentParts() as $p) {
            // Dwing string-vergelijking af (partId kan Ulid/string zijn)
            if ((string) $p->getPartId() === (string) $partId) {
                $part = $p;
                break;
            }
        }

        if (!$part) {
            return $this->json([
                'error' => 'Part not found',
                'hint' => 'Use instrumentParts[].onlinePartId from set.json as {onlinePartId} in the URL',
            ], 404);
        }


        $this->em->beginTransaction();
        try {
            $part->setRampSpeed($rampUp);
            $part->setRampSpeedDown($rampDown);

            $doc->setUpdatedBy($this->getUser());
            $this->em->flush();

            $snapshotService->createSnapshot($doc, 'api_patch_part_ramp', $this->getUser());
            $this->em->flush();

            $this->em->commit();

            $newHead = ($doc->getHeadVersion()?->getVersionNr() ?? $currentHead);

            return $this->json([
                'ok' => true,
                'setVersion' => $newHead,
                'trackId' => $trackId,
                'partId' => $partId,
                'rampSpeed' => $rampUp,
                'rampSpeedDown' => $rampDown,
            ], 200);

        } catch (Throwable $e) {
            $this->em->rollback();
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

}
