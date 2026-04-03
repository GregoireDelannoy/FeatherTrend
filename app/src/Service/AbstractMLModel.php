<?php

namespace App\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use OnnxRuntime\Model;

abstract class AbstractMLModel
{
    protected Model $model;

    public function __construct(
        protected string $modelPath,
        protected string $metadataPath,
        protected int $modelInputSize,
    ) {
        $this->model = new Model($this->modelPath);
    }

    protected function letterboxImage(ImageInterface $image): ImageInterface
    {
        $size = $image->getSize();
        $iw = $size->getWidth();
        $ih = $size->getHeight();

        $scale = min($this->modelInputSize / $iw, $this->modelInputSize / $ih);
        $nw = (int) ($iw * $scale);
        $nh = (int) ($ih * $scale);

        $resized = $image->copy();
        $resized->resize(new Box($nw, $nh), ImageInterface::FILTER_CUBIC);

        $palette = new RGB();
        $imagine = new Imagine();
        $newImage = $imagine->create(new Box($this->modelInputSize, $this->modelInputSize), $palette->color([128, 128, 128]));

        $newImage->paste($resized, new Point((int) (($this->modelInputSize - $nw) / 2), (int) (($this->modelInputSize - $nh) / 2)));
        unset($resized);

        return $newImage;
    }

    protected function preprocess(ImageInterface $boxed): array
    {
        if (!$boxed instanceof \Imagine\Gd\Image) {
            throw new \RuntimeException('GD driver expected for preprocessing.');
        }
        $gdImage = $boxed->getGdResource();

        $width = imagesx($gdImage);
        $height = imagesy($gdImage);

        $r = $g = $b = [];

        for ($x = 0; $x < $height; ++$x) {
            $rRow = $gRow = $bRow = [];
            for ($y = 0; $y < $width; ++$y) {
                $rgb = imagecolorat($gdImage, $y, $x);
                $rRow[] = (($rgb >> 16) & 0xFF) / 255.0;
                $gRow[] = (($rgb >> 8) & 0xFF) / 255.0;
                $bRow[] = ($rgb & 0xFF) / 255.0;
            }
            $r[] = $rRow;
            $g[] = $gRow;
            $b[] = $bRow;
        }

        return [[$r, $g, $b]];
    }

    abstract public function run(ImageInterface $image);
}
