<?php

namespace App\Tests\Unit;

use App\Service\AbstractMLModel;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use PHPUnit\Framework\TestCase;

class TestableMLModel extends AbstractMLModel
{
    public function __construct(
        protected string $modelPath,
        protected string $metadataPath,
        protected int $modelInputSize,
    ) {
    }

    // Expose protected methods for testing
    public function testLetterboxImage(ImageInterface $image): ImageInterface
    {
        return $this->letterboxImage($image);
    }

    public function testPreprocess(ImageInterface $image): array
    {
        return $this->preprocess($image);
    }

    public function run(ImageInterface $image)
    {
        return null;
    }
}

class AbstractMLModelServiceTest extends TestCase
{
    private TestableMLModel $model;
    private Imagine $imagine;
    private RGB $rgb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new TestableMLModel('', '', 40);
        $this->imagine = new Imagine();
        $this->rgb = new RGB();
    }

    public function testLetterboxHorizontalRedImage(): void
    {
        $image = $this->imagine->create(new Box(1000, 500), $this->rgb->color([255, 0, 0]));
        $letterbox = $this->model->testLetterboxImage($image);

        $this->assertEquals($letterbox->getSize(), new Box(40, 40), 'Letterbox img is 40x40px');
        $this->assertEquals($letterbox->getColorAt(new Point(20, 20)), $this->rgb->color([255, 0, 0]), 'Letterbox img center is red');
        $this->assertEquals($letterbox->getColorAt(new Point(0, 20)), $this->rgb->color([255, 0, 0]), 'Letterbox img center left is red');
        $this->assertEquals($letterbox->getColorAt(new Point(20, 0)), $this->rgb->color([128, 128, 128]), 'Letterbox img center top is grey');
    }

    public function testLetterboxVerticalRedImage(): void
    {
        $image = $this->imagine->create(new Box(500, 1000), $this->rgb->color([255, 0, 0]));
        $letterbox = $this->model->testLetterboxImage($image);

        $this->assertEquals($letterbox->getSize(), new Box(40, 40), 'Letterbox img is 40x40px');
        $this->assertEquals($letterbox->getColorAt(new Point(20, 20)), $this->rgb->color([255, 0, 0]), 'Letterbox img center is red');
        $this->assertEquals($letterbox->getColorAt(new Point(0, 20)), $this->rgb->color([128, 128, 128]), 'Letterbox img center left is grey');
        $this->assertEquals($letterbox->getColorAt(new Point(20, 0)), $this->rgb->color([255, 0, 0]), 'Letterbox img center top is red');
    }

    public function testPreprocessImage(): void
    {
        $image = $this->imagine->create(new Box(2, 2));
        $red = $this->imagine->create(new Box(1, 1), $this->rgb->color([255, 0, 0]));
        $green = $this->imagine->create(new Box(1, 1), $this->rgb->color([0, 255, 0]));
        $blue = $this->imagine->create(new Box(1, 1), $this->rgb->color([0, 0, 255]));
        $black = $this->imagine->create(new Box(1, 1), $this->rgb->color([0, 0, 0]));
        $image->paste($red, new Point(0, 0));
        $image->paste($green, new Point(1, 0));
        $image->paste($blue, new Point(0, 1));
        $image->paste($black, new Point(1, 1));

        $array = $this->model->testPreprocess($image);

        // We expect an array of one image with each RGB channel describing a matrix of the image size.
        // Channel values should be normalized from [0-255] to [0-1]
        $this->assertEquals($array, [[
            [ // red
                [1, 0],
                [0, 0],
            ],
            [ // green
                [0, 1],
                [0, 0],
            ],
            [ // blue
                [0, 0],
                [1, 0],
            ],
        ]]);
    }
}
