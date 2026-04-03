<?php

namespace App\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;

class ImageService
{
    public function letterboxImage(ImageInterface $image, int $newSize): ImageInterface
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

    public function preprocess(ImageInterface $boxed): array
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
}
