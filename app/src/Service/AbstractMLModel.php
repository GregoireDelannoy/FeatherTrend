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
        protected int $modelInputSize,
    ) {
    }

    protected function getModel(): Model
    {
        if (!isset($this->model)) {
            $this->model = new Model($this->modelPath);
        }

        return $this->model;
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

        return $newImage;
    }

    protected function preprocess(ImageInterface $boxed): array
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

    abstract public function run(ImageInterface $image);
}
