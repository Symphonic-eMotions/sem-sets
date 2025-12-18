<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\Document;
use App\Entity\DocumentTrack;
use App\Entity\EffectSettingsKeyValue;
use App\Entity\InstrumentPart;
use App\Form\DocumentFormType;
use App\Form\NewDocumentFormType;
use App\Midi\MidiAnalyzer;
use App\Repository\AssetRepository;
use App\Repository\DocumentRepository;
use App\Repository\DocumentVersionRepository;
use App\Repository\EffectSettingsRepository;
use App\Service\AssetStorage;
use App\Service\DocumentPayloadBuilder;
use App\Service\DocumentSnapshotService;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;
use Throwable;
use ZipArchive;

#[Route('/')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FilesystemOperator     $uploadsStorage, // service-id: uploads.storage
        private readonly AssetRepository        $assetRepo,
        private readonly DocumentPayloadBuilder $payloadBuilder,
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
        AssetStorage $assets,
        MidiAnalyzer $midiAnalyzer,
        EffectSettingsRepository $effectSettingsRepo,
        DocumentSnapshotService $snapshotService,
    ): Response {
        $form = $this->createForm(DocumentFormType::class, $doc);
        $form->handleRequest($req);

        // ========================
        // PREFILL (GET, niet submitted)
        // ========================
        if (!$form->isSubmitted()) {

            $tracksForm = $form->get('tracks');
            $tracks = array_values($doc->getTracks()->toArray());

            foreach ($tracks as $index => $track) {
                if (!isset($tracksForm[$index])) { continue; }
                $trackForm = $tracksForm[$index];

                // LoopLength → textveld (raw JSON) voor loop-editor
                if ($trackForm->has('loopLength')) {
                    $loop = $track->getLoopLength() ?? [];
                    if (!empty($loop)) {
                        $raw = '[' . implode(',', array_map('intval', $loop)) . ']';
                        $trackForm->get('loopLength')->setData($raw);
                    }
                }

                if ($trackForm->has('instrumentParts')) {

                    $partsForm = $trackForm->get('instrumentParts');
                    $parts = array_values($track->getInstrumentParts()->toArray());

                    foreach ($parts as $pIndex => $part) {
                        if (!isset($partsForm[$pIndex])) {
                            continue;
                        }

                        $partForm = $partsForm[$pIndex];

                        if (!$part->getPartId()) {
                            $part->setPartId($this->newPartId());
                        }

                        // AOI → string voor het formulier
                        if ($partForm->has('areaOfInterest')) {
                            $aoi = $part->getAreaOfInterest() ?? [];
                            if (!empty($aoi)) {
                                $raw = '[' . implode(',', array_map('intval', $aoi)) . ']';
                                $partForm->get('areaOfInterest')->setData($raw);
                            }
                        }

                        // ✅ LoopsToGrid → string voor het formulier (alleen eerste part heeft editor, maar veld bestaat overal)
                        if ($partForm->has('loopsToGrid')) {
                            $loops = $part->getLoopsToGrid() ?? [];
                            if (!empty($loops)) {
                                $raw = '[' . implode(',', array_map('intval', $loops)) . ']';
                                $partForm->get('loopsToGrid')->setData($raw);
                            } else {
                                // leeg laten → JS zet default (alles loop A) indien nodig
                                $partForm->get('loopsToGrid')->setData('');
                            }
                        }

                        // 🔹 targetBinding → "effect:ID" of "seq:velocity"
                        if ($partForm->has('targetBinding')) {
                            $binding = null;

                            if ($part->getTargetType() === InstrumentPart::TARGET_TYPE_EFFECT
                                && $part->getTargetEffectParam()
                            ) {
                                $binding = 'effect:' . $part->getTargetEffectParam()->getId();
                            } elseif ($part->getTargetType() === InstrumentPart::TARGET_TYPE_SEQUENCER
                                && $part->getTargetSequencerParam()
                            ) {
                                $binding = 'seq:' . $part->getTargetSequencerParam(); // "seq:velocity"
                            }

                            $partForm->get('targetBinding')->setData($binding);
                        }

                        // 🔹 Nieuw: damper-extra-settings prefilling
                        // Alleen overschrijven als de entity een waarde heeft.
                        if ($partForm->has('minimalLevel')) {
                            $min = $part->getMinimalLevel();
                            if ($min !== null) {
                                $partForm->get('minimalLevel')->setData($min);
                            }
                            // als $min === null laten we de default 0.1 uit het FormType intact
                        }

                        if ($partForm->has('rampSpeed')) {
                            $up = $part->getRampSpeed();
                            if ($up !== null && $up !== '') {
                                $partForm->get('rampSpeed')->setData($up);
                            }
                            // null → laat de Choice-default (0.08) staan
                        }

                        if ($partForm->has('rampSpeedDown')) {
                            $down = $part->getRampSpeedDown();
                            if ($down !== null && $down !== '') {
                                $partForm->get('rampSpeedDown')->setData($down);
                            }
                            // null → laat de Choice-default (0.04) staan
                        }
                    }
                }
            }
        }

        // ========================
        // SUBMIT + SAVE
        // ========================
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
            $tracks = array_values($doc->getTracks()->toArray());

            /* @var DocumentTrack $t */
            foreach ($tracks as $index => $t) {
                $trackForm = $tracksForm[$index] ?? null;

                $partsForm = $trackForm?->has('instrumentParts')
                    ? $trackForm->get('instrumentParts')
                    : null;

                // LoopLength uit raw veld → entity
                if ($trackForm && $trackForm->has('loopLength')) {
                    $rawLoop = $trackForm->get('loopLength')->getData();
                    $t->setLoopLength($rawLoop);
                }

                // effects normaliseren
                $trackEffects = array_values($t->getTrackEffects()->toArray());
                $trackEffects = array_filter($trackEffects, static fn($te) => $te->getPreset() !== null);

                $pos = 0;
                foreach ($trackEffects as $te) {
                    $te->setTrack($t);
                    $te->setPosition($pos++);
                }

                if ($partsForm) {
                    $parts = array_values($t->getInstrumentParts()->toArray());
                    $partPos = 0;
                    $expectedAreas = $doc->getGridColumns() * $doc->getGridRows();

                    /* @var InstrumentPart $part */
                    foreach ($parts as $pIndex => $part) {
                        $partForm = $partsForm[$pIndex] ?? null;

                        if ($partForm && $partForm->has('areaOfInterest')) {
                            $rawAoi = $partForm->get('areaOfInterest')->getData();
                            $part->setAreaOfInterest($rawAoi);
                        }

                        // LoopsToGrid raw → entity
                        if ($partForm && $partForm->has('loopsToGrid')) {
                            $rawLoops = $partForm->get('loopsToGrid')->getData();
                            $part->setLoopsToGrid($rawLoops);
                        }

                        // targetBinding → targetType + effect/sequencer param
                        if ($partForm && $partForm->has('targetBinding')) {

                            /** @var string|null $binding */
                            $binding = $partForm->get('targetBinding')->getData();

                            // Sla raw binding op voor JSON-export
                            $part->setTargetBinding($binding);

                            // reset
                            $part->setTargetType(InstrumentPart::TARGET_TYPE_NONE);
                            $part->setTargetEffectParam(null);
                            $part->setTargetSequencerParam(null);

                            if (is_string($binding) && $binding !== '') {

                                if (str_starts_with($binding, 'effect:')) {
                                    $id = (int) substr($binding, 7);
                                    if ($id > 0) {
                                        $kvRepo = $this->em->getRepository(EffectSettingsKeyValue::class);
                                        $kv = $kvRepo->find($id);
                                        if ($kv) {
                                            $part->setTargetType(InstrumentPart::TARGET_TYPE_EFFECT);
                                            $part->setTargetEffectParam($kv);
                                        }
                                    }
                                }

                                elseif (str_starts_with($binding, 'seq:')) {
                                    $param = substr($binding, 4) ?: null;
                                    if ($param === 'velocity') {
                                        $part->setTargetType(InstrumentPart::TARGET_TYPE_SEQUENCER);
                                        $part->setTargetSequencerParam('velocity');
                                    }
                                }
                            }
                        }

                        // AOI resizen...
                        $aoi = array_values($part->getAreaOfInterest());
                        if ($expectedAreas > 0) {
                            if (count($aoi) === 0) {
                                $aoi = array_fill(0, $expectedAreas, 1);
                            } elseif (count($aoi) > $expectedAreas) {
                                $aoi = array_slice($aoi, 0, $expectedAreas);
                            } elseif (count($aoi) < $expectedAreas) {
                                $aoi = array_merge($aoi, array_fill(0, $expectedAreas - count($aoi), 0));
                            }
                            $aoi = array_map(static fn($v) => (int)((int)$v === 1), $aoi);
                            $part->setAreaOfInterest($aoi);
                        }

                        if ($part->getTrack() !== $t) {
                            $part->setTrack($t);
                        }
                        $part->setPosition($partPos++);
                    }
                }

                if ($t->getDocument() !== $doc) {
                    $t->setDocument($doc);
                }
                if (!$t->getTrackId()) {
                    $t->setTrackId($this->newTrackId());
                }

                $levels = array_values(array_map(static fn($v) => (int)$v, (array)$t->getLevels()));
                $t->setLevels($levels);

                $t->setPosition($position++);
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
            $snapshotService->createSnapshot($doc, 'update', $this->getUser());

            // 7) Nieuwe uploads verwerken (maakt Asset entities aan)
            $this->handleMidiUploads($form, $doc, $assets);

            // 8) Level lengten
            $this->syncTrackLevelsToSet($doc);

            $this->em->flush();

            // PRG: redirect zodat midiAsset-keuzes zijn ververst
            $this->addFlash('success', 'Document opgeslagen.');
            return $this->redirectToRoute('doc_edit', ['id' => $doc->getId()]);
        }

        // ========================
        // VIEW DATA (MIDI + effecten)
        // ========================

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
                // @unlink($tmpPath);

            } catch (Throwable $e) {
                $midiInfo[$track->getTrackId()] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Load target effect parameter data (met ranges uit config)
        $allPresets = $effectSettingsRepo->findAll();

        $allEffectPresetsMap = [];
        foreach ($allPresets as $preset) {
            $effectName = $preset->getName();
            $params = [];

            // Volledige config JSON (bv. {"cutoffFrequency":{"range":[10,20000],"value":20000}, ...})
            $config = $preset->getConfig();

            foreach ($preset->getKeysValues() as $kv) {
                if ($kv->getType() === EffectSettingsKeyValue::TYPE_NAME) {
                    $effectName = $kv->getValue() ?? $effectName;
                }

                if ($kv->getType() === EffectSettingsKeyValue::TYPE_PARAM) {
                    $key   = $kv->getKeyName();
                    $range = null;

                    if (is_array($config)
                        && array_key_exists($key, $config)
                        && is_array($config[$key])
                        && isset($config[$key]['range'])
                        && is_array($config[$key]['range'])
                    ) {
                        // Zorg dat we altijd [min, max] als "platte" array hebben
                        $range = array_values($config[$key]['range']);
                    }

                    $params[] = [
                        'id'    => $kv->getId(),
                        'key'   => $key,
                        'range' => $range,
                    ];
                }
            }

            $allEffectPresetsMap[$preset->getId()] = [
                'presetId'   => $preset->getId(),
                'effectName' => $effectName,
                'params'     => $params,
            ];
        }

        return $this->render('Document/edit.html.twig', [
            'document' => $doc,
            'form'     => $form->createView(),
            'assets'   => $this->assetRepo->findForDocument($doc),
            'midiInfo' => $midiInfo,
            'allEffectPresetsMap' => $allEffectPresetsMap,
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
        $json = $this->payloadBuilder->buildPayloadJson($doc);

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

    /**
     * @throws FilesystemException
     */
    #[Route('documents/{id}/bundle.zip', name: 'doc_bundle_download', methods: ['GET'])]
    public function downloadBundleZip(
        Document $doc,
        AssetRepository $assetRepo,
        AssetStorage $assetStorage
    ): BinaryFileResponse {
        // 1) JSON payload (dezelfde als je API)
        $json = $this->payloadBuilder->buildPayloadJson($doc);

        // 2) Tijdelijk ZIP-bestand aanmaken
        $tmpZipPath = tempnam(sys_get_temp_dir(), 'set_bundle_');
        if ($tmpZipPath === false) {
            throw new \RuntimeException('Kon geen tijdelijk bestand aanmaken voor ZIP.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Kon ZIP-archief niet openen.');
        }

        // 2a) JSON toevoegen als set.json
        $zip->addFromString('set.json', $json);

        // 3) Alle MIDI-assets voor dit document toevoegen
        $assets   = $assetRepo->findForDocument($doc);
        $tmpFiles = []; // lokale tempbestanden om na afloop op te ruimen

        foreach ($assets as $asset) {
            // Filter alleen .mid / .midi (op basis van oorspronkelijke bestandsnaam)
            $origName = $asset->getOriginalName() ?? '';
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['mid', 'midi'], true)) {
                continue;
            }

            // Maak een lokaal tijdelijk bestand van deze asset via je bestaande helper
            $localPath = $assetStorage->createLocalTempFile($asset);
            if (!is_file($localPath)) {
                // defensief: sla deze over als er iets misgaat
                continue;
            }

            $tmpFiles[] = $localPath;

            // In de ZIP willen we nette paden, bv. assets/bass.mid
            // Desnoods fallback naar een generieke naam
            $safeName = $origName !== '' ? $origName : ('midi_' . $asset->getId() . '.mid');

            $zip->addFile($localPath, $safeName);
        }

        // ZIP sluiten zodat hij klaar is voor download
        $zip->close();

        // 4) Tijdelijke MIDI-tempfiles opruimen (de ZIP is nu compleet)
        foreach ($tmpFiles as $tmp) {
            @unlink($tmp);
        }

        // 5) Download-response opbouwen
        $filenameBase = $doc->getSlug() ?: ('set-' . $doc->getId());
        $downloadName = sprintf('%s-bundle.zip', $filenameBase);

        $response = new BinaryFileResponse($tmpZipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $downloadName
        );
        $response->headers->set('Content-Type', 'application/zip');

        // Zorg dat het tijdelijke ZIP-bestand na versturen wordt verwijderd
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('api/documents/{id}/bundle.zip', name: 'api_doc_bundle_download', methods: ['GET'])]
    public function apiDownloadBundleZip(
        Document $doc,
        AssetRepository $assetRepo,
        AssetStorage $assetStorage
    ): BinaryFileResponse {
        // We hergebruiken gewoon de bestaande method
        return $this->downloadBundleZip($doc, $assetRepo, $assetStorage);
    }

    #[Route('api/published-sets', name: 'api_published_sets', methods: ['GET'])]
    public function apiPublishedSets(DocumentRepository $repo): Response
    {
        $published = $repo->findBy(['published' => true], ['title' => 'ASC']);

        $data = [];
        foreach ($published as $doc) {
            $data[] = [
                'id' => $doc->getId(),
                'slug' => $doc->getSlug(),
                'title' => $doc->getTitle(),
                'setVersion' => (int) ($doc->getHeadVersion()?->getVersionNr() ?? 0),
                'bundleUrl' => $this->generateUrl(
                    'api_doc_bundle_download',
                    ['id' => $doc->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ];
        }

        return $this->json($data);
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

    private function newPartId(): string
    {
        return 'prt_'.(new Ulid())->toBase32();
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'set';
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
