<?php

namespace App\Service;

use Imagine\Image\ImageInterface;

class DetectionModel extends AbstractMLModel
{
    public const CLASS_BIRD = 14;
    private float $confidenceThreshold = 0.25;

    public function setConfidenceThreshold(float $threshold): self
    {
        $this->confidenceThreshold = $threshold;

        return $this;
    }

    public function run(ImageInterface $image): ?array
    {
        $letterboxed = $this->letterboxImage($image);
        $preprocessed = $this->preprocess($letterboxed);

        $results = $this->getModel()->predict(['images' => $preprocessed]);
        $detections = $results['output0'][0];
        unset($this->model);

        $bestBox = null;
        $bestScore = 0.0;

        foreach ($detections as $det) {
            [$x1, $y1, $x2, $y2, $confidence, $classId] = $det;

            if (self::CLASS_BIRD != (int) round($classId)) {
                continue;
            }

            if ($confidence < max($this->confidenceThreshold, $bestScore)) {
                continue;
            }

            $bestScore = $confidence;
            $bestBox = [$x1, $y1, $x2, $y2];
        }

        if (null == $bestBox) {
            return null;
        }

        [$x1, $y1, $x2, $y2] = $bestBox;

        $originalW = $image->getSize()->getWidth();
        $originalH = $image->getSize()->getHeight();
        $scale = min($this->modelInputSize / $originalW, $this->modelInputSize / $originalH);
        $scaledW = (int) ($originalW * $scale);
        $scaledH = (int) ($originalH * $scale);
        $padX = ($this->modelInputSize - $scaledW) / 2;
        $padY = ($this->modelInputSize - $scaledH) / 2;

        return [
            max(0, ($x1 - $padX) / $scale),
            max(0, ($y1 - $padY) / $scale),
            min($originalW, ($x2 - $padX) / $scale),
            min($originalH, ($y2 - $padY) / $scale),
        ];
    }
}
