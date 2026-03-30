<?php

namespace App\Tests\Integration;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BirdIdentificationTest extends WebTestCase
{
    private const TEST_SPECIES = "Common kingfisher";

    public function testIdentifyNotAvailablePublicly(): void
    {
        $client = static::createClient();

        $client->request('GET', '/identify');
        $this->assertResponseRedirects("/login");

        $client->request('POST', '/identify');
        $this->assertResponseRedirects("/login");
    }

    public function testKingfisherImageIsLabelledAsKingfisher(): void
    {
        $client = static::createClient();

        // Identify as an user, to be able to access identification
        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('test@example.com');
        $client->loginUser($user);

        $client->request('GET', '/identify');

        $uploadedFile = new UploadedFile(
            path: __DIR__.'/../assets/sample_image_kingfisher.jpg',
            originalName: 'sample_image_kingfisher.jpg',
            mimeType: 'image/jpeg',
        );

        $csrfToken = $client->getCrawler()->filter('input[id="identify_picture_form__token"]')->attr('value');
        $client->request(
            method: 'POST',
            uri: '/identify',
            parameters: [
                'identify_picture_form' => [
                    '_token' => $csrfToken,
                ]
            ],
            files: ['identify_picture_form' => ['picture' => $uploadedFile]],
        );

        $this->assertResponseIsSuccessful();
        $res = $client->getCrawler()->html();
        $this->assertSelectorTextSame('[data-test="identify_species_result"]', self::TEST_SPECIES,
            'Sample image should be identified properly',
        );
    }
}

