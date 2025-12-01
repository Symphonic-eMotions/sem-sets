<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\PayloadBlockRepository;
use RuntimeException;

final class PayloadBlockFactory
{
    public function __construct(
        private readonly PayloadBlockRepository $blocks
    ) {
    }

    /**
     * Bouwt een payload-blok op basis van zijn naam.
     *
     * @param string $name       Naam van het blok, bv. "host.midiFile.v1"
     * @param array<string,mixed> $overrides  Waarden om het template mee te overriden
     *
     * @return array<string,mixed>
     */
    public function build(string $name, array $overrides = []): array
    {
        $block = $this->blocks->findOneByName($name);

        if (!$block) {
            throw new RuntimeException(sprintf('PayloadBlock "%s" niet gevonden.', $name));
        }

        $base = $block->getPayload();

        if ($overrides === []) {
            return $base;
        }

        return $this->deepMerge($base, $overrides);
    }

    /**
     * Diepe merge: arrays worden recursief samengevoegd, andere waarden overschrijven.
     *
     * @param array<string,mixed> $base
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function deepMerge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && array_key_exists($key, $base) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                // Let op: hier mag value ook null of 0 zijn, dat is juist gewenst.
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
