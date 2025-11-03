<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
final class ApiController extends AbstractController
{
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
                'updatedAt'  => $d->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                'jsonUrl'    => $this->generateUrl('api_set_json', ['slug' => $d->getSlug()], 0),
                'assetsBase' => rtrim($this->generateUrl('api_set_asset', ['slug' => $d->getSlug(), 'filename' => ''], 0), '/'),
            ];
        }

        // Gebruik de helper van AbstractController voor JSON
        return $this->json(['items' => $items]);
    }

    // LET OP: niet 'json' noemen -> naamconflict met AbstractController::json()
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
}
