<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SpeciesControllerTest extends WebTestCase
{
    public function testGetSpeciesList(): void
    {
        $client = static::createClient();
        $client->request('GET', '/species');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getInternalResponse()->getContent(), true);
        $this->assertEquals(count($response), 2, 'Number of species returned in array');

        // TODO: use objects (or interfaces?) as description for the returned format, and use that in the tests
        $fixtureSpecies = array_find($response, function (array $value) {
            return 2 == $value['id'];
        });
        $this->assertArraysAreEqualIgnoringOrder([
            'id' => 2,
            'scientific_name' => 'Gypaetus barbatus',
            'common_name' => 'Bearded Vulture',
            'count' => 2,
        ], $fixtureSpecies);
    }

    public function testGetMonthlySameMonthDifferentYears(): void
    {
        $client = static::createClient();
        $client->request('GET', '/species/1/monthly');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getInternalResponse()->getContent(), true);
        $this->assertEquals(count($response), 12, '12 months in a year');

        $this->assertEquals($response[0]['count'], 3);
        for ($month = 1; $month < 12; ++$month) {
            $this->assertEquals($response[$month]['count'], 0);
        }
    }

    public function testGetMonthlyDifferentMonthDifferentYears(): void
    {
        $client = static::createClient();
        $client->request('GET', '/species/2/monthly');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getInternalResponse()->getContent(), true);
        $this->assertEquals(count($response), 12, '12 months in a year');

        $this->assertEquals($response[0]['count'], 0);
        $this->assertEquals($response[3]['count'], 1);
        $this->assertEquals($response[4]['count'], 1);
    }
}
