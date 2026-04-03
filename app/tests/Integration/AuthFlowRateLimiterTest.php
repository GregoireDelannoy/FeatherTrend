<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthFlowRateLimiterTest extends WebTestCase
{
    private const TEST_EMAIL_DOMAIN = '@example.com';
    private const TEST_PASSWORD = 'secret123';

    public function testRegisterThenLogout(): void
    {
        $client = static::createClient();

        // Register new user
        $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Register', [
            'registration_form[email]' => uniqid().self::TEST_EMAIL_DOMAIN,
            'registration_form[plainPassword]' => self::TEST_PASSWORD,
        ]);
        $this->assertResponseRedirects();

        // Logout
        $client->request('GET', '/logout');
        $this->assertResponseRedirects();

        $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Register', [
            'registration_form[email]' => uniqid().self::TEST_EMAIL_DOMAIN,
            'registration_form[plainPassword]' => self::TEST_PASSWORD,
        ]);
        $this->assertResponseStatusCodeSame(429);

        // Ensure we waited the rate limit out, not to bother the other tests
        sleep(1);
    }
}
