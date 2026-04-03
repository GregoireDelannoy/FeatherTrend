<?php

namespace App\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;

class ImageIdentificationService
{
    public function __construct(
        private DetectionModel $detectionModel,
        private ClassificationModel $classificationModel,
        private Imagine $imagine = new Imagine(),
    ) {
    }

    public function identify(string $picturePath): array
    {
        $photo = $this->imagine->open($picturePath);
        $detectionResult = $this->detectionModel->run($photo);

        if (null !== $detectionResult) {
            $cropped = $this->cropImage($photo, $detectionResult);
        } else {
            $cropped = $photo;
        }

        $classificationResult = $this->classificationModel->run($cropped);

        return [
            'probability' => $classificationResult->getProbability(),
            'species' => $classificationResult->getSpecies(),
            'imageThumbnail' => base64_encode($cropped->get('png')),
        ];
    }

    private function cropImage(mixed $image, array $coordinates): mixed
    {
        [$x1, $y1, $x2, $y2] = $coordinates;
        $cropW = $x2 - $x1;
        $cropH = $y2 - $y1;

        return $image->copy()->crop(new Point((int) $x1, (int) $y1), new Box((int) $cropW, (int) $cropH));
    }
}
