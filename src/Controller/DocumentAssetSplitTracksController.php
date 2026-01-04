<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\Document;
use App\Midi\MidiTrackSplitter;
use App\Repository\AssetRepository;
use App\Repository\DocumentRepository;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class DocumentAssetSplitTracksController extends AbstractController
{
    #[Route('/documents/{id}/assets/{assetId}/split-tracks', name: 'doc_asset_split_tracks', methods: ['POST'])]
    public function __invoke(
        int $id,
        int $assetId,
        Request $request,
        DocumentRepository $docs,
        AssetRepository $assets,
        MidiTrackSplitter $splitter,
    ): JsonResponse {
        /** @var Document|null $doc */
        $doc = $docs->find($id);
        if (!$doc) {
            return new JsonResponse(['error' => 'Document niet gevonden'], 404);
        }

        /** @var Asset|null $asset */
        $asset = $assets->find($assetId);
        if (!$asset || $asset->getDocument()->getId() !== $doc->getId()) {
            return new JsonResponse(['error' => 'Asset niet gevonden'], 404);
        }

        // simpele CSRF (zelfde stijl als je deleteknop)
        $csrf = (string) $request->request->get('csrf');
        if (!$this->isCsrfTokenValid('split-tracks-' . $asset->getId(), $csrf)) {
            return new JsonResponse(['error' => 'Ongeldige CSRF token'], 400);
        }

        try {
            $created = $splitter->splitAssetIntoTrackAssets($doc, $asset, $this->getUser());
        } catch (FilesystemException $e) {
            return new JsonResponse(['error' => 'Opslagfout: ' . $e->getMessage()], 500);
        } catch (Throwable $e) {
            return new JsonResponse(['error' => 'Split mislukt: ' . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'ok' => true,
            'createdCount' => count($created),
            'createdNames' => array_map(static fn(Asset $a) => $a->getOriginalName(), $created),
        ]);
    }
}
