<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Document;
use App\Entity\DocumentTrack;
use PHPUnit\Framework\TestCase;

final class DocumentTrackTest extends TestCase
{
    public function testGetLoopLengthMigratesLegacyData(): void
    {
        $doc = new Document();
        $doc->setTimeSignature('4/4');

        $track = new DocumentTrack();
        $track->setDocument($doc);

        // Reflection to set the private property since there's no setLoopLength for legacy raw data 
        // that doesn't immediately normalize. Actually, let's check if we can use setLoopLength.
        // Wait, setLoopLength normalizes. To test the getter's migration, we need raw legacy data in the property.
        
        $refl = new \ReflectionClass(DocumentTrack::class);
        $prop = $refl->getProperty('loopLength');
        $prop->setAccessible(true);
        
        // Legacy format: array of bars
        $prop->setValue($track, [4, 8]);

        $expected = [
            ['offset' => 0, 'length' => 16],  // 4 bars * 4 beats
            ['offset' => 16, 'length' => 32], // 8 bars * 4 beats
        ];

        self::assertSame($expected, $track->getLoopLength());
    }

    public function testSetLoopLengthNormalizesInput(): void
    {
        $doc = new Document();
        $doc->setTimeSignature('4/4');

        $track = new DocumentTrack();
        $track->setDocument($doc);

        // Test with new format
        $input = [
            ['offset' => 10, 'length' => 20],
        ];
        $track->setLoopLength($input);
        self::assertSame($input, $track->getLoopLength());

        // Test with legacy CSV string
        $track->setLoopLength('4,8');
        $expected = [
            ['offset' => 0, 'length' => 16],
            ['offset' => 16, 'length' => 32],
        ];
        self::assertSame($expected, $track->getLoopLength());
    }
}
