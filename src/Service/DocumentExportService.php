<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Repository\AssetRepository;
use League\Flysystem\FilesystemException;
use ZipArchive;
use RuntimeException;

class DocumentExportService
{
    public function __construct(
        private readonly DocumentPayloadBuilder $payloadBuilder,
        private readonly AssetRepository $assetRepo,
        private readonly AssetStorage $assetStorage
    ) {
    }

    /**
     * Creates a temporary ZIP file containing the document JSON and MIDI assets.
     * 
     * @return string Path to the temporary zip file
     * @throws RuntimeException If zip creation fails
     * @throws FilesystemException If asset storage access fails
     */
    public function createBundleZip(Document $doc): string
    {
        // 1) JSON payload
        $json = $this->payloadBuilder->buildPayloadJson($doc);

        // 2) Create temp ZIP file
        $tmpZipPath = tempnam(sys_get_temp_dir(), 'set_bundle_');
        if ($tmpZipPath === false) {
            throw new RuntimeException('Kon geen tijdelijk bestand aanmaken voor ZIP.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Kon ZIP-archief niet openen.');
        }

        // 2a) Add JSON as set.json
        $zip->addFromString('set.json', $json);

        // 3) Add MIDI assets
        $assets = $this->assetRepo->findForDocument($doc);
        $tmpFiles = [];

        foreach ($assets as $asset) {
            $origName = $asset->getOriginalName() ?? '';
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['mid', 'midi'], true)) {
                continue;
            }

            $localPath = $this->assetStorage->createLocalTempFile($asset);
            if (!is_file($localPath)) {
                continue;
            }

            $tmpFiles[] = $localPath;

            $safeName = $origName !== '' ? $origName : ('midi_' . $asset->getId() . '.mid');
            $zip->addFile($localPath, $safeName);
        }

        $zip->close();

        // 4) Cleanup temporary midi files
        foreach ($tmpFiles as $tmp) {
            @unlink($tmp);
        }

        return $tmpZipPath;
    }
}
