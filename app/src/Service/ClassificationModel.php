<?php

namespace App\Service;

use App\Model\ClassificationResult;
use Imagine\Image\ImageInterface;

class ClassificationModel extends AbstractMLModel
{
    public function run(ImageInterface $image): ClassificationResult
    {
        $modelMetadata = json_decode(file_get_contents($this->metadataPath), true);

        $boxed = $this->letterboxImage($image);
        $preprocessed = $this->preprocess($boxed);
        $results = $this->model->predict(['x' => $preprocessed]);
        unset($this->model);
        $probabilities = $results['output'][0];

        $maxProbability = max($probabilities);
        $maxProbabilityIndex = array_search($maxProbability, $probabilities);
        $species = array_search($maxProbabilityIndex, $modelMetadata['class_to_idx']);

        return new ClassificationResult($maxProbability, $species);
    }
}
