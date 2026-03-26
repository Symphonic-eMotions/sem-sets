<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\Document;
use App\Midi\MidiNoteStaggerer;
use App\Repository\AssetRepository;
use App\Repository\DocumentRepository;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class DocumentAssetStaggerNotesController extends AbstractController
{
    #[Route(
        '/documents/{id}/assets/{assetId}/stagger-notes',
        name: 'doc_asset_stagger_notes',
        methods: ['POST'],
    )]
    public function __invoke(
        int                $id,
        int                $assetId,
        Request            $request,
        DocumentRepository $docs,
        AssetRepository    $assets,
        MidiNoteStaggerer  $staggerer,
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

        $csrf = (string) $request->request->get('csrf');
        if (!$this->isCsrfTokenValid('stagger-notes-' . $asset->getId(), $csrf)) {
            return new JsonResponse(['error' => 'Ongeldige CSRF token'], 400);
        }

        $offsetTicks = max(1, (int) ($request->request->get('offsetTicks', 1)));

        try {
            $newAsset = $staggerer->staggerAsset($doc, $asset, $this->getUser(), $offsetTicks);
        } catch (FilesystemException $e) {
            return new JsonResponse(['error' => 'Opslagfout: ' . $e->getMessage()], 500);
        } catch (Throwable $e) {
            return new JsonResponse(['error' => 'Stagger mislukt: ' . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'ok'   => true,
            'name' => $newAsset->getOriginalName(),
            'id'   => $newAsset->getId(),
        ]);
    }
}
