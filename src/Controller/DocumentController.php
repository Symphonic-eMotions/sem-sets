<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\Document;
use App\Form\DocumentFormType;
use App\Form\NewDocumentFormType;
use App\Midi\MidiAnalyzer;
use App\Repository\AssetRepository;
use App\Repository\DocumentRepository;
use App\Repository\DocumentVersionRepository;
use App\Service\AssetStorage;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;
use Throwable;

#[Route('/')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FilesystemOperator $uploadsStorage, // service-id: uploads.storage
        private readonly AssetRepository $assetRepo,
    ) {}

    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function index(DocumentRepository $repo): Response
    {
        return $this->render('Document/index.html.twig', [
            'documents' => $repo->findBy([], ['updatedAt'=>'DESC']),
        ]);
    }

    #[Route('documents/new', name: 'doc_new', methods: ['GET','POST'])]
    public function new(Request $req): Response
    {
        $doc = new Document();
        $form = $this->createForm(NewDocumentFormType::class, $doc);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            // slugify onder water
            $doc->setSlug($this->slugify($doc->getTitle()));
            $doc->setSemVersion($doc->getSemVersion());
            $doc->setGridColumns(2);
            $doc->setGridRows(2);
            $doc->setLevelDurations([32,32]);

            $doc->setCreatedBy($this->getUser());
            $doc->setUpdatedBy($this->getUser());
            $this->em->persist($doc);
            $this->em->flush();

            // ga direct naar edit (user vult daar alles verder in)
            return $this->redirectToRoute('doc_edit', ['id' => $doc->getId()]);
        }

        return $this->render('Document/new.html.twig', ['form' => $form]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('documents/{id}/edit', name: 'doc_edit', methods: ['GET','POST'])]
    public function edit(
        Document $doc,
        Request $req,
        VersioningService $vs,
        AssetStorage $assets,
        MidiAnalyzer $midiAnalyzer,
    ): Response {
        $form = $this->createForm(DocumentFormType::class, $doc);
        $form->handleRequest($req);

        if (!$form->isSubmitted()) {
            // 'tracks' is jouw CollectionType<DocumentTrackType>
            $tracksForm = $form->get('tracks');

            foreach ($doc->getTracks() as $index => $track) {
                if (!isset($tracksForm[$index])) {
                    continue;
                }

                $trackForm = $tracksForm[$index];

                if (!$trackForm->has('loopLength')) {
                    continue;
                }

                $loop = method_exists($track, 'getLoopLength')
                    ? $track->getLoopLength()
                    : [];

                if (!empty($loop)) {
                    // We zetten de data in dezelfde vorm als JS verwacht: "[48,48]"
                    $raw = '[' . implode(',', array_map('intval', $loop)) . ']';
                    $trackForm->get('loopLength')->setData($raw);
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {

            // 1) Slug bijwerken op basis van titel
            $title = (string) $form->get('title')->getData();
            $doc->setSlug($this->slugify($title));

            // 2) Grid "NxM" → kolommen/rijen (begrens 1..3 zoals je eerder deed)
            $gridSize = $form->get('gridSize')->getData(); // bv. "3x2"
            if (is_string($gridSize) && preg_match('/^(\d+)x(\d+)$/', $gridSize, $m)) {
                $cols = max(1, min(3, (int) $m[1]));
                $rows = max(1, min(3, (int) $m[2]));
                $doc->setGridColumns($cols);
                $doc->setGridRows($rows);
            }

            // 3) Tracks (DocumentTrack-collectie) normaliseren en relationeel goedzetten
            $tracksForm = $form->get('tracks');
            $position   = 0;

            foreach ($doc->getTracks() as $index => $t) {
                // Bijbehorend subformulier voor deze track
                $trackForm = $tracksForm[$index] ?? null;

                // 3a) loopLength uit het formulier lezen (raw string zoals "[48,48]")
                if ($trackForm && $trackForm->has('loopLength')) {
                    $rawLoop = $trackForm->get('loopLength')->getData();
                    $t->setLoopLength($rawLoop);
                }

                // 3b) bi-directionele relatie
                if ($t->getDocument() !== $doc) {
                    $t->setDocument($doc);
                }

                // 3c) stabiel id indien leeg OF null
                if (!$t->getTrackId()) {
                    $t->setTrackId($this->newTrackId());
                }

                // 3d) levels forceren naar ints
                $levels = array_values(array_map(
                    static fn($v) => (int) $v,
                    (array) $t->getLevels()
                ));
                $t->setLevels($levels);

                // 3e) positie volgens formulier-volgorde
                $t->setPosition($position++);

                // ---------------------------------------------------------
                // 3f) NEW: areaOfInterest resizen naar huidig document-grid
                // ---------------------------------------------------------
                $expectedAreas = $doc->getGridColumns() * $doc->getGridRows();
                $aoi = array_values((array) $t->getAreaOfInterest());

                if ($expectedAreas > 0) {
                    if (count($aoi) === 0) {
                        // default: alles aan bij lege AOI
                        $aoi = array_fill(0, $expectedAreas, 1);
                    } elseif (count($aoi) > $expectedAreas) {
                        $aoi = array_slice($aoi, 0, $expectedAreas);
                    } elseif (count($aoi) < $expectedAreas) {
                        $aoi = array_merge($aoi, array_fill(0, $expectedAreas - count($aoi), 0));
                    }

                    // forceer strikt 0/1
                    $aoi = array_map(static fn($v) => (int)((int)$v === 1), $aoi);

                    $t->setAreaOfInterest($aoi);
                }

                // (optioneel) guard: midiAsset moet bij dit document horen
                // if ($a = $t->getMidiAsset()) {
                //     if ($a->getDocument()?->getId() !== $doc->getId()) {
                //         $t->setMidiAsset(null);
                //     }
                // }
            }

            // 4) BPM bijwerken
            $postedBpm = $form->get('setBPM')->getData();
            if ((string) $postedBpm !== $doc->getSetBPM()) {
                $doc->setSetBPM($postedBpm);
            }

            // 5) Metadata + opslag
            $doc->setUpdatedBy($this->getUser());
            $this->em->flush();

            // 6) Snapshot na update (JSON payload bouwt nu uit DocumentTrack)
            $this->createSnapshot($vs, $doc);

            // 7) Nieuwe uploads verwerken (maakt Asset entities aan)
            $this->handleMidiUploads($form, $doc, $assets);

            // 8) Level lengten
            $this->syncTrackLevelsToSet($doc);

            $this->em->flush();

            // 8) PRG: redirect zodat midiAsset-keuzes zijn ververst
            $this->addFlash('success', 'Document opgeslagen.');
            return $this->redirectToRoute('doc_edit', ['id' => $doc->getId()]);
        }

        // Load midi info
        $midiInfo = [];
        foreach ($doc->getTracks() as $track) {
            $asset = $track->getMidiAsset();
            if (!$asset) {
                $midiInfo[$track->getTrackId()] = null;
                continue;
            }

            $tmpPath = $assets->createLocalTempFile($asset);
            try {
                $summary = $midiAnalyzer->summarize($tmpPath);
                $midiInfo[$track->getTrackId()] = [
                    'bpm'        => $summary->bpm,
                    'timeSig'    => $summary->hasTimeSignature()
                        ? sprintf('%d/%d', $summary->timeSignatureNumerator, $summary->timeSignatureDenominator)
                        : null,
                    'bars'       => $summary->barCount,
                    'duration'   => $summary->getDurationFormatted(),
                    'rawSeconds' => $summary->durationSeconds,
                ];

                // Optioneel: opruimen na analyse
//                @unlink($tmpPath);

            } catch (Throwable $e) {
                $midiInfo[$track->getTrackId()] = [
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $this->render('Document/edit.html.twig', [
            'document' => $doc,
            'form'     => $form->createView(),
            'assets'   => $this->assetRepo->findForDocument($doc),
            'midiInfo' => $midiInfo
        ]);
    }

    private function syncTrackLevelsToSet(Document $doc): void
    {
        $set = array_values($doc->getLevelDurations());
        $setLen = count($set);

        foreach ($doc->getTracks() as $t) {
            $levels = array_values((array) $t->getLevels());

            if ($setLen <= 0) {
                $t->setLevels([]);
                continue;
            }

            if (count($levels) > $setLen) {
                $levels = array_slice($levels, 0, $setLen);
            } elseif (count($levels) < $setLen) {
                $levels = array_merge($levels, array_fill(0, $setLen - count($levels), 0));
            }

            // extra: forceer strikt 0/1
            $levels = array_map(static fn($v) => (int)((int)$v === 1), $levels);

            $t->setLevels($levels);
        }
    }

    #[Route('/documents/{id}', name: 'doc_delete', methods: ['POST', 'DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Document $document, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $tokenId = 'delete-doc-' . $document->getId();
        if (!$this->isCsrfTokenValid($tokenId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Ongeldige CSRF-token.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Verwijder assets op schijf + DB, en document + versies in één transactie
        $this->em->beginTransaction();
        try {
            // 1) Verwijder directory var/uploads/sets/{id}
            $baseDir = sprintf('sets/%d', $document->getId());
            if ($this->uploadsStorage->directoryExists($baseDir)) {
                $this->uploadsStorage->deleteDirectory($baseDir);
            }

            // 2) Verwijder Asset entities gekoppeld aan dit document (als je ze hebt)
            $assetRepo = $this->em->getRepository(Asset::class);
            $assets = $assetRepo->findBy(['document' => $document]);
            foreach ($assets as $asset) {
                $this->em->remove($asset);
            }

            // 3) Verwijder DocumentVersion entities (als cascade niet al staat)
            // bijv: $versions = $this->em->getRepository(DocumentVersion::class)->findBy(['document' => $document]);
            // foreach ($versions as $v) { $this->em->remove($v); }

            // 4) Verwijder Document zelf
            $this->em->remove($document);
            $this->em->flush();
            $this->em->commit();

            $this->addFlash('success', 'Set en gekoppelde assets zijn verwijderd.');
        } catch (Throwable $e) {
            $this->em->rollback();
            $this->addFlash('error', 'Verwijderen mislukt: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
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

    #[Route('documents/{id}/api.json', name: 'doc_api_json', methods: ['GET'])]
    public function apiJson(Document $doc): Response
    {
        $json = $this->buildPayloadJson($doc);

        $filenameBase = $doc->getSlug() ?: ('set-' . $doc->getId());
        $filename = sprintf('%s.json', $filenameBase);

        $response = new StreamedResponse(static function () use ($json) {
            echo $json;
        });

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="%s"', $filename)
        );

        return $response;
    }

    #[Route('documents/{id}/versions', name: 'doc_versions', methods: ['GET'])]
    public function versions(Document $doc, DocumentVersionRepository $vr): Response
    {
        $versions = $vr->findBy(['document' => $doc], ['versionNr' => 'DESC']);
        return $this->render('Document/versions.html.twig', [
            'document' => $doc,
            'versions' => $versions,
            'canDownload' => true,
        ]);
    }

    #[Route('documents/{id}/versions/{version}/restore', name: 'doc_restore_version', methods: ['POST'])]
    public function restoreVersion(
        Document $document,
        int $version,
        Request $request,
        DocumentVersionRepository $versions,
        VersioningService $vs
    ): Response {
        // CSRF check (token komt uit je form in de template)
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('restore_version_'.$document->getId().'_'.$version, $token)) {
            throw $this->createAccessDeniedException('Ongeldig CSRF token.');
        }

        $v = $versions->findOneByDocumentAndNumber($document, $version);
        if (!$v) {
            throw $this->createNotFoundException('Versie niet gevonden.');
        }

        $vs->promoteToHead($document, $v);
        $this->addFlash('success', sprintf('v%d is nu de nieuwe HEAD.', $version));

        return $this->redirectToRoute('doc_versions', ['id' => $document->getId()]);
    }

    #[Route('documents/{id}/versions/{version}/download', name: 'doc_version_download', methods: ['GET'])]
    public function downloadVersion(
        Document $document,
        int $version,
        DocumentVersionRepository $versions
    ): StreamedResponse {
        $v = $versions->findOneByDocumentAndNumber($document, $version);
        if (!$v) {
            throw $this->createNotFoundException('Versie niet gevonden.');
        }

        $json = $v->getJsonText();
        $filename = sprintf('%s-v%d.json', $document->getSlug(), $version);

        $response = new StreamedResponse(static function () use ($json) {
            echo $json;
        });
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    private function newTrackId(): string
    {
        return 'trk_'.(new Ulid())->toBase32();
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
     * @throws FilesystemException
     */
    private function createSnapshot(VersioningService $vs, Document $doc): void
    {
        $json = $this->buildPayloadJson($doc);
        $vs->createSnapshot($doc, $json, 'update', $this->getUser());
    }

    /**
     * Centrale JSON payload (hergebruikt door new/edit).
     */
    private function buildPayloadJson(Document $doc): string
    {
        $bpm            = (float) $doc->getSetBPM();
        $levelDurations = array_map('intval', $doc->getLevelDurations());

        $instrumentsConfig = [];

        foreach ($doc->getTracks() as $t) {
            // 1) LoopLength ophalen (altijd array<int>)
            $loopLength = method_exists($t, 'getLoopLength')
                ? $t->getLoopLength()
                : [];

            $loopLength = array_values(array_map('intval', $loopLength));

            // 2) MIDI-bestanden
            $midi      = [];
            $midiLabel = null;

            if ($t->getMidiAsset()) {
                $orig = $t->getMidiAsset()->getOriginalName();
                $name = pathinfo($orig, PATHINFO_FILENAME) ?: $orig;
                $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION) ?: 'mid');

                // Mooie naam op basis van midi file
                $midiLabel = $this->humanizeLabel($name);

                $midi[] = [
                    'midiFileName' => $name,
                    'midiFileExt'  => $ext,
                    'loopLength'   => $loopLength,  // <-- hier komt nu [100] of [48,48] etc.
                ];
            }

            // 3) EXS preset → exsFiles-blok + label
            $exsPreset       = method_exists($t, 'getExsPreset') ? $t->getExsPreset() : null;
            $exsFiles        = null;
            $instrumentType  = null;
            $exsLabel        = null;

            if ($exsPreset) {
                $exsFiles = [[
                    'exsFileExt'  => 'exs',
                    'exsFileName' => $exsPreset,
                ]];
                $instrumentType = 'exsSampler';
                $exsLabel       = $this->humanizeLabel($exsPreset); // "Cellos Legato", "Advanced FM", etc.
            }

            // 4) Track / instrument naam opbouwen
            $instrumentName = null;

            if ($midiLabel && $exsLabel) {
                $instrumentName = sprintf('%s – %s', $midiLabel, $exsLabel);
            } elseif ($midiLabel) {
                $instrumentName = $midiLabel;
            } elseif ($exsLabel) {
                $instrumentName = $exsLabel;
            } else {
                // fallback: trackId humanizen
                $instrumentName = $this->humanizeLabel($t->getTrackId() ?? '') ?? $t->getTrackId();
            }

            // 5) Instrument-config entry
            $instrumentsConfig[] = [
                'trackId'        => $t->getTrackId(),
                'levels'         => array_values(array_map('intval', $t->getLevels())),
                'midiFiles'      => $midi,
                'instrumentType' => $instrumentType,   // null of 'exsSampler'
                'exsFiles'       => $exsFiles,         // null of array
                'instrumentName' => $instrumentName,
            ];
        }

        $payload = [
            'gridColumns'       => $doc->getGridColumns(),
            'gridRows'          => $doc->getGridRows(),
            'published'         => $doc->isPublished(),
            'semVersion'        => $doc->getSemVersion(),
            'setName'           => $doc->getTitle(),
            'setBPM'            => $bpm,
            'levelDurations'    => $levelDurations,
            'instrumentsConfig' => $instrumentsConfig,
        ];

        return (string) json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT
        );
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

    private function humanizeLabel(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        // 1) extensie weghalen (voor het geval er toch nog .mid of zo inzit)
        $base = pathinfo($value, PATHINFO_FILENAME) ?: $value;

        // 2) vervang scheidingstekens door spaties
        $base = str_replace(['-', '_', '.'], ' ', $base);

        // 3) camelCase naar spaties: "moonLeftHand" -> "moon Left Hand"
        $base = preg_replace('/(?<!^)([A-Z])/', ' $1', $base);

        // 4) meerdere spaties normaliseren
        $base = preg_replace('/\s+/', ' ', $base);

        // 5) trim + eerste letter hoofdletter, rest laten zoals is
        $base = trim($base);
        if ($base === '') {
            return null;
        }

        return ucfirst($base);
    }
}
