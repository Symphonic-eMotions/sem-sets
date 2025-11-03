<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Form\DocumentFormType;
use App\Repository\AssetRepository;
use App\Repository\DocumentRepository;
use App\Repository\DocumentVersionRepository;
use App\Service\AssetStorage;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route('/')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function index(DocumentRepository $repo): Response
    {
        return $this->render('document/index.html.twig', [
            'documents' => $repo->findBy([], ['updatedAt'=>'DESC']),
        ]);
    }

    #[Route('documents/new', name: 'doc_new', methods: ['GET','POST'])]
    public function new(Request $req, VersioningService $vs, AssetStorage $assets): Response
    {
        $doc  = new Document();
        $form = $this->createForm(DocumentFormType::class, $doc);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1) Persist voor ID
            $doc->setCreatedBy($this->getUser());
            $doc->setUpdatedBy($this->getUser());
            $title = $form->get('title')->getData();
            $slug = $this->slugify($title);
            $doc->setSlug($slug);
            $this->em->persist($doc);
            $this->em->flush();

            // 2) Snapshot (initial)
            $this->createSnapshot($vs, $doc, 'initial');

            // 3) MIDI uploads
            $this->handleMidiUploads($form, $doc, $assets);

            return $this->redirectToRoute('doc_edit', ['id' => $doc->getId()]);
        }

        return $this->render('document/new.html.twig', ['form' => $form]);
    }

    #[Route('documents/{id}/edit', name: 'doc_edit', methods: ['GET','POST'])]
    public function edit(
        Document $doc,
        Request $req,
        VersioningService $vs,
        AssetStorage $assets,
        AssetRepository $assetRepo
    ): Response {
        $form = $this->createForm(DocumentFormType::class, $doc);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {

            $title = $form->get('title')->getData();
            $slug = $this->slugify($title);
            $doc->setSlug($slug);

            // 1) Metadata bijwerken
            $doc->setUpdatedBy($this->getUser());
            $this->em->flush();

            // 2) Snapshot (update)
            $this->createSnapshot($vs, $doc, 'update');

            // 3) MIDI uploads
            $this->handleMidiUploads($form, $doc, $assets);

            return $this->redirectToRoute('doc_edit', ['id' => $doc->getId()]);
        }

        $assetList = $assetRepo->findBy(['document' => $doc], ['id' => 'DESC']);

        return $this->render('document/edit.html.twig', [
            'form' => $form,
            'document' => $doc,
            'assets'   => $assetList,
        ]);
    }

    #[Route('documents/{id}/assets/{assetId}/download', name: 'doc_asset_download', methods: ['GET'])]
    public function downloadAsset(
        Document $doc,
        int $assetId,
        AssetRepository $assetRepo,
        AssetStorage $storage
    ): Response {
        $asset = $assetRepo->find($assetId);
        if (!$asset || $asset->getDocument()->getId() !== $doc->getId()) {
            throw $this->createNotFoundException();
        }

        // Lees via Flysystem stream
        $stream = $storage->openReadStream($asset); // zie storage helper hieronder
        if ($stream === false) {
            $this->addFlash('danger', 'Kon bestand niet openen.');
            return $this->redirectToRoute('doc_edit', ['id' => $doc->getId()]);
        }

        $response = new StreamedResponse(function() use ($stream) {
            fpassthru($stream);
            fclose($stream);
        });

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $asset->getOriginalName()
        );

        $response->headers->set('Content-Type', $asset->getMimeType() ?: 'application/octet-stream');
        if ($asset->getSize()) {
            $response->headers->set('Content-Length', (string)$asset->getSize());
        }
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('documents/{id}/assets/{assetId}/delete', name: 'doc_asset_delete', methods: ['POST'])]
    public function deleteAsset(
        Document $doc,
        int $assetId,
        Request $req,
        AssetRepository $assetRepo,
        AssetStorage $storage
    ): Response {
        $token = $req->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-asset-'.$assetId, $token)) {
            $this->addFlash('danger', 'Ongeldige CSRF token.');
            return $this->redirectToRoute('doc_edit', ['id' => $doc->getId()]);
        }

        $asset = $assetRepo->find($assetId);
        if (!$asset || $asset->getDocument()->getId() !== $doc->getId()) {
            $this->addFlash('danger', 'Bestand niet gevonden.');
            return $this->redirectToRoute('doc_edit', ['id' => $doc->getId()]);
        }

        try {
            $storage->delete($asset); // zie storage helper hieronder
            $this->addFlash('success', 'Bestand verwijderd.');
        } catch (Throwable $e) {
            $this->addFlash('danger', 'Verwijderen mislukt: '.$e->getMessage());
        }

        return $this->redirectToRoute('doc_edit', ['id' => $doc->getId()]);
    }

    #[Route('documents/{id}/versions', name: 'doc_versions', methods: ['GET'])]
    public function versions(Document $doc, DocumentVersionRepository $vr): Response
    {
        $versions = $vr->findBy(['document' => $doc], ['versionNr' => 'DESC']);
        return $this->render('document/versions.html.twig', [
            'document' => $doc,
            'versions' => $versions,
        ]);
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'set';
    }

    /**
     * Bouwt de (MVP) payload en maakt een nieuwe versie-snapshot aan.
     */
    private function createSnapshot(VersioningService $vs, Document $doc, string $action): void
    {
        $json = $this->buildPayloadJson($doc);
        $vs->createSnapshot($doc, $json, $action, $this->getUser());
    }

    /**
     * Centrale JSON payload (hergebruikt door new/edit).
     */
    private function buildPayloadJson(Document $doc): string
    {
        $payload = [
            'gridColumns'       => 4,
            'gridRows'          => 4,
            'published'         => $doc->isPublished(),
            'semVersion'        => $doc->getSemVersion(),
            'setName'           => $doc->getTitle(),
            'setBPM'            => 118,
            'instrumentsConfig' => [],
        ];

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Verwerkt de MIDI-uploads vanaf het formulier en slaat assets op.
     * Verzorgt ook flash-meldingen.
     */
    private function handleMidiUploads(FormInterface $form, Document $doc, AssetStorage $assets): void
    {
        /** @var UploadedFile[]|null $files */
        $files = $form->get('midiFiles')->getData();
        if (!$files) {
            return;
        }

        $ok = 0;
        $fail = 0;

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                $this->addFlash('danger', 'Interne fout: geen geldig upload-object.');
                $fail++;
                continue;
            }

            if (!$file->isValid()) {
                $this->addFlash('danger', sprintf(
                    'Uploadfout voor %s: %s',
                    $file->getClientOriginalName(),
                    $file->getErrorMessage()
                ));
                $fail++;
                continue;
            }

            // Extra – hard guard op extensie
            $ext = strtolower($file->getClientOriginalExtension()
                ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            if (!in_array($ext, ['mid', 'midi'], true)) {
                $this->addFlash('warning', sprintf(
                    'Bestand %s overgeslagen (geen .mid/.midi).',
                    $file->getClientOriginalName()
                ));
                $fail++;
                continue;
            }

            // Inhoud lezen en opslaan
            $binary = @file_get_contents($file->getRealPath());
            if ($binary === false) {
                $this->addFlash('danger', sprintf(
                    'Kon inhoud van %s niet lezen.',
                    $file->getClientOriginalName()
                ));
                $fail++;
                continue;
            }

            try {
                $assets->store(
                    $doc,
                    $file->getClientOriginalName(),
                    $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
                    $file->getSize(),
                    $binary,
                    $this->getUser()
                );
                $ok++;
            } catch (Throwable $e) {
                $this->addFlash('danger', sprintf(
                    'Opslaan mislukt voor %s: %s',
                    $file->getClientOriginalName(),
                    $e->getMessage()
                ));
                $fail++;
            }
        }

        if ($ok > 0) {
            $this->addFlash('success', sprintf('%d MIDI-bestand(en) opgeslagen.', $ok));
        }
        if ($fail > 0) {
            $this->addFlash('warning', sprintf('%d bestand(en) overgeslagen of mislukt.', $fail));
        }
    }
}
