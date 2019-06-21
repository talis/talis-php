<?php

use Talis\Persona\Client\OAuthClients;
use Talis\Persona\Client\InvalidPayloadException;
use Talis\Persona\Client\InvalidConfigurationException;

$appRoot = dirname(dirname(dirname(__DIR__)));
if (!defined('APPROOT')) {
    define('APPROOT', $appRoot);
}

require_once $appRoot . '/test/unit/TestBase.php';

class OAuthClientsTest extends TestBase
{
    // Get oauth client tests
    public function testGetOAuthClientEmptyClientIdThrowsException()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid clientId');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->getOAuthClient('', '');
    }

    public function testGetOAuthClientEmptyTokenThrowsException()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid token');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->getOAuthClient('123', '');
    }

    public function testGetOAuthClientThrowsExceptionWhenClientNotFound()
    {
        $this->setExpectedException('Exception', 'Did not retrieve successful response code');
        $mockClient = $this->getMock('Talis\Persona\Client\OAuthClients', ['personaGetOAuthClient'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('personaGetOAuthClient')
            ->will($this->throwException(new Exception('Did not retrieve successful response code')));

        $mockClient->getOAuthClient('123', '456');
    }

    public function testGetOAuthClientReturnsClientWhenGupidFound()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\OAuthClients', ['personaGetOAuthClient'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $expectedResponse = [
            'rate_limit' => 1000,
            'rate_duration' => 1800,
            'rate_expires' => 1433516934,
            'call_count' => 0,
            'scope' => [
                'su'
            ]
        ];
        $mockClient->expects($this->once())
            ->method('personaGetOAuthClient')
            ->will($this->returnValue($expectedResponse));

        $client = $mockClient->getOAuthClient('123', '456');
        $this->assertEquals($expectedResponse['rate_limit'], $client['rate_limit']);
        $this->assertEquals($expectedResponse['rate_duration'], $client['rate_duration']);
        $this->assertEquals($expectedResponse['rate_expires'], $client['rate_expires']);
        $this->assertEquals($expectedResponse['call_count'], $client['call_count']);
        $this->assertEquals($expectedResponse['scope'], $client['scope']);
    }

    public function testUpdateOAuthClientEmptyGuid()
    {
        $this->setExpectedException('Exception', 'Invalid guid');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('', [], '987');
    }

    public function testUpdateOAuthClientInvalidGuid()
    {
        $this->setExpectedException('Exception', 'Invalid guid');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient([], [], '987');
    }

    public function testUpdateOAuthClientEmptyProperties()
    {
        $this->setExpectedException('Exception', 'Invalid properties');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', [], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesKeys()
    {
        $this->setExpectedException('Exception', 'Invalid properties');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['INVALID' => []], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesScopeKeys1()
    {
        $this->setExpectedException('Exception', 'Invalid properties');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => []], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesScopeKeys2()
    {
        $this->setExpectedException('Exception', 'Invalid properties');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => ['blah' => '']], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesScopeKeys3()
    {
        $this->setExpectedException('Exception', 'Invalid properties');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => ['blah' => '', '$add' => 'test']], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesScopeKeys4()
    {
        $this->setExpectedException('Exception', 'Invalid properties');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient(
            '123',
            [
                'scope' => [
                    'blah' => '',
                    '$remove' => 'remove-scope',
                    '$add' => 'add-scope'
                ]
            ],
            '987'
        );
    }

    public function testUpdateOAuthClientsEmptyToken()
    {
        $this->setExpectedException('Exception', 'Invalid token');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => ['$add' => 'additional-scope']], '');
    }

    public function testUpdateOAuthClientsInvalidToken()
    {
        $this->setExpectedException('Exception', 'Invalid token');
        $personaClient = new OAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => ['$add' => 'additional-scope']], ['']);
    }

    public function testUpdateOAuthClientPutFails()
    {
        $this->setExpectedException('Exception', 'Could not retrieve OAuth response code');
        $mockClient = $this->getMock('Talis\Persona\Client\OAuthClients', ['personaPatchOAuthClient'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('personaPatchOAuthClient')
            ->will($this->throwException(new Exception('Could not retrieve OAuth response code')));

        $mockClient->updateOAuthClient('guid', ['scope' => ['$add' => 'additional-scope']], '123');
    }

    public function testUpdateOAuthClientPutSucceeds()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\OAuthClients', ['personaPatchOAuthClient'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);

        $expectedResponse = []; // 204 has no content
        $mockClient->expects($this->once())
            ->method('personaPatchOAuthClient')
            ->will($this->returnValue($expectedResponse));

        $this->assertEquals(
            $expectedResponse,
            $mockClient->updateOAuthClient(
                '123',
                ['scope' => ['$add' => 'additional-scope']],
                '123'
            )
        );
    }

    public function testRegenerateSecretNon200Exception()
    {
        $oauthClient = $this->getMock(
            'Talis\Persona\Client\OAuthClients',
            ['performRequest'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'persona_admin_host' => 'http://localhost:85',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $oauthClient->expects($this->once())
            ->method('performRequest')
            ->with(
                'http://localhost:85/1/clients/clientId/generatesecret',
                [
                    'method' => 'PATCH',
                    'bearerToken' => 'token',
                    'expectResponse' => true,
                ]
            )
            ->will(
                $this->throwException(
                    new \Exception('Did not retrieve successful response code')
                )
            );

        $this->setExpectedException(
            'Exception',
            'Did not retrieve successful response code'
        );
        $oauthClient->regenerateSecret('clientId', 'token');
    }

    public function testRegenerateSecretInvalidResponsePayload()
    {
        $oauthClient = $this->getMock(
            'Talis\Persona\Client\OAuthClients',
            ['performRequest'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'persona_admin_host' => 'http://localhost:85',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $oauthClient->expects($this->once())
            ->method('performRequest')
            ->with(
                'http://localhost:85/1/clients/clientId/generatesecret',
                [
                    'method' => 'PATCH',
                    'bearerToken' => 'token',
                    'expectResponse' => true,
                ]
            )
            ->willReturn(['invalid' => 'body']);

        $this->setExpectedException(
            'Talis\Persona\Client\InvalidPayloadException',
            'invalid payload format from persona'
        );
        $oauthClient->regenerateSecret('clientId', 'token');
    }

    public function testRegenerateSecretHappyPath()
    {
        $oauthClient = $this->getMock(
            'Talis\Persona\Client\OAuthClients',
            ['performRequest'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'persona_admin_host' => 'http://localhost:85',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $oauthClient->expects($this->once())
            ->method('performRequest')
            ->with(
                'http://localhost:85/1/clients/clientId/generatesecret',
                [
                    'method' => 'PATCH',
                    'bearerToken' => 'token',
                    'expectResponse' => true,
                ]
            )
            ->willReturn(['secret' => 'new secret']);

        $secret = $oauthClient->regenerateSecret('clientId', 'token');
        $this->assertEquals('new secret', $secret);
    }
}
