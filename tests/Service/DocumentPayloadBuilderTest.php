<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DocumentTrack;
use App\Service\DocumentPayloadBuilder;
use App\Tests\Support\DatabaseWebTestCase;

final class DocumentPayloadBuilderTest extends DatabaseWebTestCase
{
    public function testBuildPayloadJsonIncludesInstrumentVolumeForApiCompatibility(): void
    {
        $this->createPayloadBlocks();
        $user = $this->createUser();
        $doc = $this->createDocument($user);

        $track = (new DocumentTrack())
            ->setTrackId('track-1')
            ->setLevels([1, 0])
            ->setTrackVolume(-6.5);

        $doc->addTrack($track);

        $this->entityManager->persist($track);
        $this->entityManager->flush();

        /** @var DocumentPayloadBuilder $builder */
        $builder = static::getContainer()->get(DocumentPayloadBuilder::class);

        $payload = json_decode($builder->buildPayloadJson($doc), true, 512, JSON_THROW_ON_ERROR);
        $trackConfig = $payload['instrumentsConfig'][0];

        self::assertSame(-6.5, $trackConfig['trackVolume']);
        self::assertSame(-6.5, $trackConfig['instrumentVolume']);
    }
}

