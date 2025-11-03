<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Asset;
use App\Entity\Document;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use function preg_replace;

final class AssetStorage
{
    public function __construct(
        private readonly FilesystemOperator $uploadsStorage,
        private readonly EntityManagerInterface $em
    ) {}

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
}
