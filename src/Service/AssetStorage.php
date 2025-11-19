<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Asset;
use App\Entity\Document;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Throwable;
use function preg_replace;

final class AssetStorage
{
    public function __construct(
        private readonly FilesystemOperator $uploadsStorage,
        private readonly EntityManagerInterface $em
    ) {}

    /**
     * @throws FilesystemException
     */
    public function store(
        Document $doc,
        string $originalName,
        string $mime,
        int $size,
        string $binary,
        ?User $user
    ): Asset {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'file.mid';
        $baseDir = sprintf('sets/%d/assets', $doc->getId());
        $path = $this->uniquePath($baseDir, $safe);

        $this->uploadsStorage->write($path, $binary);

        $a = new Asset();
        $a->setDocument($doc);
        $a->setOriginalName($originalName);
        $a->setMimeType($mime ?: 'application/octet-stream');
        $a->setSize($size);
        $a->setStoragePath($path);
        $a->setCreatedBy($user);

        $this->em->persist($a);
        $this->em->flush();

        return $a;
    }

    /**
     * @throws FilesystemException
     */
    private function uniquePath(string $dir, string $filename): string
    {
        // split naam.ext
        $dot = strrpos($filename, '.');
        $name = $dot !== false ? substr($filename, 0, $dot) : $filename;
        $ext  = $dot !== false ? substr($filename, $dot) : '';

        $candidate = "$dir/$filename";
        $i = 2;
        while ($this->uploadsStorage->fileExists($candidate)) {
            $candidate = sprintf('%s/%s-%d%s', $dir, $name, $i, $ext);
            $i++;
        }
        return $candidate;
    }

    public function openReadStream(Asset $asset)
    {
        try {
            return $this->uploadsStorage->readStream($asset->getStoragePath());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @throws FilesystemException
     */
    public function delete(Asset $asset): void
    {
        // 1) fysiek bestand weg
        if ($this->uploadsStorage->fileExists($asset->getStoragePath())) {
            $this->uploadsStorage->delete($asset->getStoragePath());
        }
        // 2) DB record weg
        $this->em->remove($asset);
        $this->em->flush();
    }

    /**
     * Maakt een tijdelijk lokaal bestand van de asset-inhoud en geeft het pad terug.
     *
     * Let op: het temp-bestand wordt niet automatisch opgeruimd.
     * Je kunt zelf eventueel na gebruik unlink() aanroepen.
     *
     * @throws FilesystemException
     */
    public function createLocalTempFile(Asset $asset): string
    {
        $path = $asset->getStoragePath();

        if (!$this->uploadsStorage->fileExists($path)) {
            throw new RuntimeException(sprintf(
                'Bestand "%s" bestaat niet in uploadsStorage.',
                $path
            ));
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'midi_');
        if ($tmpFile === false) {
            throw new RuntimeException('Kon geen tijdelijk bestand aanmaken voor MIDI-analyse.');
        }

        // Simpelste variant: hele bestand in memory lezen en wegschrijven
        $contents = $this->uploadsStorage->read($path);
        if ($contents === "") {
            throw new RuntimeException(sprintf(
                'Kon inhoud van "%s" niet lezen uit uploadsStorage.',
                $path
            ));
        }

        if (file_put_contents($tmpFile, $contents) === false) {
            throw new RuntimeException(sprintf(
                'Kon tijdelijk bestand "%s" niet schrijven.',
                $tmpFile
            ));
        }

        return $tmpFile;
    }

}
