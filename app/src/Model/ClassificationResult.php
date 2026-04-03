<?php

namespace App\Model;

class ClassificationResult
{
    public function __construct(
        private float $probability,
        private string $species,
    ) {
    }

    public function getProbability(): float
    {
        return $this->probability;
    }

    public function getSpecies(): string
    {
        return $this->species;
    }
}
