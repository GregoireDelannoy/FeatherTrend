<?php

namespace App\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use OnnxRuntime\Model;

class DetectionModel
{
    public const MODEL_PATH = __DIR__.'/../../ml_models/yolo26m.onnx';
    public const MODEL_INPUT_SIZE = 640;
    public const MODEL_CLASS_BIRD = 14;

    public static function run(ImageInterface $image): ?array
    {
        $letterboxed = ImageService::letterboxWithGreyMargins($image, self::MODEL_INPUT_SIZE);
        $preprocessed = ImageService::preprocess($letterboxed);

        $detectionModel = new Model(self::MODEL_PATH);
        $results = $detectionModel->predict(['images' => $preprocessed]);
        $detections = $results['output0'][0];
        unset($detectionModel);

        $bestBox = null;
        $bestScore = 0.0;
        $confidenceThreshold = 0.25;

        foreach ($detections as $det) {
            [$x1, $y1, $x2, $y2, $confidence, $classId] = $det;

            if (self::MODEL_CLASS_BIRD != (int) round($classId)) {
                continue;
            }

            if ($confidence < max($confidenceThreshold, $bestScore)) {
                continue;
            }

            $bestScore = $confidence;
            $bestBox = [$x1, $y1, $x2, $y2];
        }

        if (null == $bestBox) {
            return null;
        }

        // The box coordinates are in letterboxed space; map them back to original image space.
        $originalW = $image->getSize()->getWidth();
        $originalH = $image->getSize()->getHeight();
        $scale = min(self::MODEL_INPUT_SIZE / $originalW, self::MODEL_INPUT_SIZE / $originalH);
        $scaledW = (int) ($originalW * $scale);
        $scaledH = (int) ($originalH * $scale);
        $padX = (self::MODEL_INPUT_SIZE - $scaledW) / 2;
        $padY = (self::MODEL_INPUT_SIZE - $scaledH) / 2;

        [$x1, $y1, $x2, $y2] = $bestBox;

        return [
            max(0, ($x1 - $padX) / $scale),
            max(0, ($y1 - $padY) / $scale),
            min($originalW, ($x2 - $padX) / $scale),
            min($originalH, ($y2 - $padY) / $scale),
        ];
    }
}

class ClassificationModel
{
    public const MODEL_PATH = __DIR__.'/../../ml_models/regnet_z_4g_eu-common.onnx';
    public const MODEL_METADATA = __DIR__.'/../../ml_models/regnet_z_4g_eu-common.onnx_metadata.json';
    public const MODEL_INPUT_SIZE = 384;

    public static function run(ImageInterface $image): array
    {
        $classificationModel = new Model(self::MODEL_PATH);
        $modelMetadata = json_decode(file_get_contents(self::MODEL_METADATA), true);

        $boxed = ImageService::letterboxWithGreyMargins($image, self::MODEL_INPUT_SIZE);
        $preprocessed = ImageService::preprocess($boxed);
        $results = $classificationModel->predict(['x' => $preprocessed]);
        $probabilities = $results['output'][0];

        $maxProbability = max($probabilities);
        $maxProbabilityIndex = array_search($maxProbability, $probabilities);
        $species = array_search($maxProbabilityIndex, $modelMetadata['class_to_idx']);

        return [$maxProbability, $species];
    }
}

class ImageService
{
    public static function letterboxWithGreyMargins(ImageInterface $image, int $newSize): ImageInterface
    {
        $size = $image->getSize();
        $iw = $size->getWidth();
        $ih = $size->getHeight();

        $scale = min($newSize / $iw, $newSize / $ih);
        $nw = (int) ($iw * $scale);
        $nh = (int) ($ih * $scale);

        $resized = $image->copy();
        $resized->resize(new Box($nw, $nh), ImageInterface::FILTER_CUBIC);

        $palette = new RGB();
        $imagine = new Imagine();
        $newImage = $imagine->create(new Box($newSize, $newSize), $palette->color([128, 128, 128]));

        $newImage->paste($resized, new Point((int) (($newSize - $nw) / 2), (int) (($newSize - $nh) / 2)));

        return $newImage;
    }

    public static function preprocess(ImageInterface $boxed): array
    {
        $width = $boxed->getSize()->getWidth();
        $height = $boxed->getSize()->getHeight();

        $channels = [[], [], []]; // R, G, B

        for ($x = 0; $x < $height; ++$x) {
            // Add an column array for each passing line
            $channels[0][] = [];
            $channels[1][] = [];
            $channels[2][] = [];
            for ($y = 0; $y < $width; ++$y) {
                $color = $boxed->getColorAt(new Point($y, $x));
                $channels[0][$x][] = $color->getValue(\Imagine\Image\Palette\Color\RGB::COLOR_RED) / 255.0;
                $channels[1][$x][] = $color->getValue(\Imagine\Image\Palette\Color\RGB::COLOR_GREEN) / 255.0;
                $channels[2][$x][] = $color->getValue(\Imagine\Image\Palette\Color\RGB::COLOR_BLUE) / 255.0;
            }
        }

        return [
            $channels,
        ];
    }

    public static function identifyPicture(string $picturePath): array
    {
        $imagine = new Imagine();
        $photo = $imagine->open($picturePath);

        $birdBox = DetectionModel::run($photo);

        if (null != $birdBox) {
            [$ox1, $oy1, $ox2, $oy2] = $birdBox;
            $cropW = $ox2 - $ox1;
            $cropH = $oy2 - $oy1;
            $cropped = $photo->copy()->crop(new Point($ox1, $oy1), new Box($cropW, $cropH));
        } else {
            $cropped = $photo;
        }

        [$maxProbability, $species] = ClassificationModel::run($cropped);

        return [
            'probability' => $maxProbability,
            'species' => $species,
            'imageThumbnail' => base64_encode($cropped->get('png')),
        ];
    }
}
