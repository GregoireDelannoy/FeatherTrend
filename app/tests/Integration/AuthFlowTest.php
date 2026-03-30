<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use App\Entity\User;

class AuthFlowTest extends WebTestCase
{
    private const TEST_EMAIL = 'newuser@example.com';
    private const TEST_PASSWORD = 'secret123';

    private function assertNotLoggedIn(KernelBrowser $client): void
    {
        $this->assertEmpty(
            $client->getCookieJar()->get('MOCKSESSID'),
            'No session cookie before registering'
        );
        $this->assertStringNotContainsString(self::TEST_EMAIL, $client->getResponse()->getContent(),
            'Not logged in yet, user email should not be in the page'
        );
    }

    private function assertLoggedIn(KernelBrowser $client): void
    {
        $this->assertNotEmpty(
            $client->getCookieJar()->get('MOCKSESSID'),
            'Session cookie should exist after registering (app is configured for automatic login after register)',
        );
        $this->assertSelectorTextContains('[data-tests="logged_in_user_email"]', self::TEST_EMAIL,
            'If logged-in, user email should be displayed in the top header',
        );
    }

    private function ensureUserDoesNotExist(string $email): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user) {
            $logger = static::getContainer()->get('logger');
            $logger->warning("Deleting test user \"$email\" before register test");
            $em->remove($user);
            $em->flush();
        }
    }

    public function testRegisterThenLogout(): void
    {
        $client = static::createClient();

        $this->ensureUserDoesNotExist(self::TEST_EMAIL);

        // Register new user
        $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
        $this->assertNotLoggedIn($client);

        $client->submitForm('Register', [
            'registration_form[email]' => self::TEST_EMAIL,
            'registration_form[plainPassword]' => self::TEST_PASSWORD,
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();

        $this->assertLoggedIn($client);

        // Logout
        $client->request('GET', '/logout');
        $this->assertResponseRedirects();
        $client->followRedirect();

        $this->assertStringNotContainsString(self::TEST_EMAIL, $client->getResponse()->getContent(),
            'Logged out, user email should not be in the page anymore'
        );
    }

    public function testLoginThenLogout(): void
    {
        $client = static::createClient();

        // Login with previously registered user
        $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $this->assertNotLoggedIn($client);

        $csrfToken = $client->getCrawler()->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/login', [
            '_username' => self::TEST_EMAIL,
            '_password' => self::TEST_PASSWORD,
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();

        $this->assertLoggedIn($client);

        // Logout
        $client->request('GET', '/logout');
        $this->assertResponseRedirects();
        $client->followRedirect();

        $this->assertStringNotContainsString(self::TEST_EMAIL, $client->getResponse()->getContent(),
            'Logged out, user email should not be in the page anymore'
        );
    }
}
