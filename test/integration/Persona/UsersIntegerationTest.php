<?php

namespace test\integration\Persona;

use Talis\Persona\Client\Users;
use Talis\Persona\Client\Tokens;
use test\TestBase;

class UsersIntegerationTest extends TestBase
{
    /**
     * @var Talis\Persona\Client\Users
     */
    private $personaClientUser;

    /**
     * @var Talis\Persona\Client\Tokens
     */
    private $personaClientTokens;
    private $clientId;
    private $clientSecret;

    public function setUp()
    {
        parent::setUp();
        $personaConf = $this->getPersonaConfig();
        $this->clientId = $personaConf['oauthClient'];
        $this->clientSecret = $personaConf['oauthSecret'];

        $this->personaClientUser = new Users(
            [
                'userAgent' => 'integrationtest',
                'persona_host' => $personaConf['host'],
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $this->personaClientUser->setLogger(new \Psr\Log\NullLogger());

        $this->personaClientTokens = new Tokens(
            [
                'userAgent' => 'integrationtest',
                'persona_host' => $personaConf['host'],
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $this->personaClientTokens->setLogger(new \Psr\Log\NullLogger());
    }

    public function testCreateUserThenGetUserByGupid()
    {
        $tokenDetails = $this->personaClientTokens->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $gupid = uniqid('trapdoor:');
        $email = uniqid() . '@example.com';
        $userCreate = $this->personaClientUser->createUser(
            $gupid,
            ['name' => 'Sarah Connor', 'email' => $email],
            $token
        );

        $user = $this->personaClientUser->getUserByGupid($userCreate['gupids'][0], $token);

        $this->assertEquals($userCreate['guid'], $user['guid']);
        $this->assertCount(1, $user['gupids']);
        $this->assertEquals($gupid, $user['gupids'][0]);
        $this->assertEquals('Sarah Connor', $user['profile']['name']);
        $this->assertEquals($email, $user['profile']['email']);
    }

    public function testCreateUserThenGetUserByGuids()
    {
        $tokenDetails = $this->personaClientTokens->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $gupid = uniqid('trapdoor:');
        $email = uniqid() . '@example.com';
        $userCreate = $this->personaClientUser->createUser(
            $gupid,
            ['name' => 'Sarah Connor', 'email' => $email],
            $token
        );

        $users = $this->personaClientUser->getUserByGuids(
            [$userCreate['guid']],
            $token
        );

        $this->assertCount(1, $users);
        $this->assertEquals($userCreate['guid'], $users[0]['guid']);
        $this->assertCount(1, $users[0]['gupids']);
        $this->assertEquals($gupid, $users[0]['gupids'][0]);
        $this->assertEquals('Sarah Connor', $users[0]['profile']['name']);
        $this->assertEquals($email, $users[0]['profile']['email']);
    }

    public function testCreateUserThenPatchUser()
    {
        $tokenDetails = $this->personaClientTokens->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $gupid = uniqid('trapdoor:');
        $email = uniqid() . '@example.com';
        $userCreate = $this->personaClientUser->createUser(
            $gupid,
            ['name' => 'Sarah Connor', 'email' => $email],
            $token
        );

        $email = uniqid() . '@example.com';
        // Update user
        $this->personaClientUser->updateUser(
            $userCreate['guid'],
            ['name' => 'John Connor', 'email' => $email],
            $token
        );

        $user = $this->personaClientUser->getUserByGupid(
            $userCreate['gupids'][0],
            $token
        );

        $this->assertEquals($userCreate['guid'], $user['guid']);
        $this->assertCount(1, $user['gupids']);
        $this->assertEquals($gupid, $user['gupids'][0]);
        $this->assertEquals('John Connor', $user['profile']['name']);
        $this->assertEquals($email, $user['profile']['email']);
    }

    public function testCreateUserThenAddGupidToUser()
    {
        $tokenDetails = $this->personaClientTokens->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $gupid = uniqid('trapdoor:');
        $email = uniqid() . '@example.com';
        $userCreate = $this->personaClientUser->createUser(
            $gupid,
            ['name' => 'Sarah Connor', 'email' => $email],
            $token
        );

        // Update gupid
        $anotherGupid = uniqid('trapdoor:');
        $this->personaClientUser->addGupidToUser(
            $userCreate['guid'],
            $anotherGupid,
            $token
        );

        $user = $this->personaClientUser->getUserByGupid($anotherGupid, $token);

        $this->assertEquals($userCreate['guid'], $user['guid']);
        $this->assertCount(2, $user['gupids']);
        $this->assertContains($gupid, $user['gupids']);
        $this->assertContains($anotherGupid, $user['gupids']);
        $this->assertEquals('Sarah Connor', $user['profile']['name']);
        $this->assertEquals($email, $user['profile']['email']);
    }

    public function testGetUserByGupidInvalidTokenThrowsException()
    {
        $this->setExpectedException(
            'Exception',
            'Did not retrieve successful response code'
        );

        $personaClient = new Users(
            [
                'userAgent' => 'integrationtest',
                'persona_host' => 'persona',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $personaClient->getUserByGupid('123', '456');
    }

    public function testGetUserByGupidThrowsNotFoundExceptionWhenUserNotFound()
    {
        $this->setExpectedException('Talis\Persona\Client\NotFoundException');

        $tokenDetails = $this->personaClientTokens->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $this->personaClientUser->getUserByGupid('trapdoor:notfound', $token);
    }

    public function testGetUserByGuidsInvalidTokenThrowsException()
    {
        $this->setExpectedException(
            'Exception',
            'Error finding user profiles: Did not retrieve successful ' .
                'response code from persona: 401'
        );

        $this->personaClientUser->getUserByGuids(['123'], '456');
    }
}
