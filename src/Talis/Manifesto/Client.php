<?php

namespace Talis\Manifesto;

require_once dirname(__FILE__) . '/common.inc.php';

class Client {

    protected $clientId;
    protected $clientSecret;

    /**
     * @var Talis\Persona\Client\Tokens
     */
    protected $personaClient;

    /**
     * @var array
     */
    protected $personaConnectValues = array();

    /**
     * @var string
     */
    protected $manifestoBaseUrl;

    /**
     * @var \Guzzle\Http\Client
     */
    protected $httpClient;
    /**
     * @param string $manifestoBaseUrl
     * @param array $personaConnectValues
     */
    public function __construct($manifestoBaseUrl, $personaConnectValues = array())
    {
        $this->manifestoBaseUrl = $manifestoBaseUrl;
        $this->personaConnectValues = $personaConnectValues;
    }

    /**
     * @param array $personaConnectValues
     */
    public function setPersonaConnectValues($personaConnectValues)
    {
        $this->personaConnectValues = $personaConnectValues;
    }

    /**
     * @return string
     */
    public function getManifestoBaseUrl()
    {
        return $this->manifestoBaseUrl;
    }

    /**
     * @param string $manifestoBaseUrl
     */
    public function setManifestoBaseUrl($manifestoBaseUrl)
    {
        $this->manifestoBaseUrl = $manifestoBaseUrl;
    }

    /**
     * For mocking
     * @return Talis\Persona\Client\Tokens
     */
    protected function getPersonaClient()
    {
        if(!isset($this->personaClient))
        {
            $this->personaClient = new Talis\Persona\Client\Tokens($this->personaConnectValues);
        }
        return $this->personaClient;
    }

    /**
     * Allows PersonaClient override, if PersonaClient has been initialized elsewhere
     * @param Talis\Persona\Client\Tokens $personaClient
     */
    public function setPersonaClient(\Talis\Persona\Client\Tokens $personaClient)
    {
        $this->personaClient = $personaClient;
    }

    /**
     * For mocking
     * @return \Guzzle\Http\Client
     */
    protected function getHTTPClient()
    {
        if(!$this->httpClient)
        {
            $this->httpClient = new \Guzzle\Http\Client();
        }
        return $this->httpClient;
    }

    /**
     * Create an archive generation job request
     *
     * @param Manifest $manifest
     * @param string $clientId
     * @param string $clientSecret
     * @throws \Exception|\Guzzle\Http\Exception\ClientErrorResponseException
     * @throws Exceptions\ManifestValidationException
     * @throws Exceptions\UnauthorisedAccessException
     * @throws Exceptions\ArchiveException
     * @return \Talis\Manifesto\Archive
     */
    public function requestArchive(Manifest $manifest, $clientId, $clientSecret)
    {
        $archiveLocation = $this->manifestoBaseUrl . '/1/archives';
        $manifestDocument = json_encode($manifest->generateManifest());

        try
        {
            $client = $this->getHTTPClient();
            $headers = $this->getHeaders($clientId, $clientSecret);

            $request = $client->post($archiveLocation, $headers, $manifestDocument);

            $response = $request->send();

            if($response->getStatusCode() == 202)
            {
                $archive = new \Manifesto\Archive();
                $archive->loadFromJson($response->getBody(true));
                return $archive;
            }
            else
            {
                throw new \Manifesto\Exceptions\ArchiveException($response->getStatusCode(), $response->getBody(true));
            }
        }
        /** @var \Guzzle\Http\Exception\ClientErrorResponseException $e */
        catch(\Guzzle\Http\Exception\ClientErrorResponseException $e)
        {
            $response = $e->getResponse();
            $error = $this->processErrorResponseBody($response->getBody(true));
            switch($response->getStatusCode())
            {
                case 400:
                    throw new \Talis\Manifesto\Exceptions\ManifestValidationException($error['message'], $error['error_code'], $e);
                case 403:
                case 401:
                    throw new \Talis\Manifesto\Exceptions\UnauthorisedAccessException($error['message'], $error['error_code'], $e);
                    break;
                case 404:
                    throw new \Talis\Manifesto\Exceptions\ArchiveException('Misconfigured Manifesto base url', 404);
                    break;
                default:
                    throw $e;
            }
        }

    }

    /**
     * Generate a pre signed URL via Manifesto API
     * @param string $jobId
     * @param string $clientId
     * @param string $clientSecret
     * @return string
     * @throws \Exception|\Guzzle\Http\Exception\ClientErrorResponseException
     * @throws Exceptions\GenerateUrlException
     */
    public function generateUrl($jobId, $clientId, $clientSecret)
    {
        $url = $this->manifestoBaseUrl . '/1/archives/'.$jobId.'/generateUrl';
        try
        {
            $client = $this->getHTTPClient();
            $headers = $this->getHeaders($clientId, $clientSecret);

            $request = $client->post($url, $headers);

            $response = $request->send();

            if($response->getStatusCode() == 200)
            {
                $body = $response->getBody(true);
                if(!empty($body))
                {
                    $body = json_decode($response->getBody(true));
                }
                return $body->url;
            } else
            {
                throw new \Manifesto\Exceptions\GenerateUrlException($response->getStatusCode, $response->getBody(true));
            }

        } catch(\Guzzle\Http\Exception\ClientErrorResponseException $e){
            $response = $e->getResponse();
            $error = $this->processErrorResponseBody($response->getBody(true));
            switch($response->getStatusCode())
            {
                case 401:
                    throw new \Talis\Manifesto\Exceptions\UnauthorisedAccessException($error['message'], $error['error_code'], $e);
                    break;
                case 404:
                    throw new \Talis\Manifesto\Exceptions\GenerateUrlException('Missing archive', 404);
                    break;
                default:
                    throw $e;
            }
        }
    }

    /**
     * Setup the header array for any request to Manifesto
     * @param string $clientId
     * @param string $clientSecret
     * @return array
     */
    protected function getHeaders($clientId, $clientSecret)
    {
        $arrPersonaToken = $this->getPersonaClient()->obtainNewToken($clientId, $clientSecret);
        $personaToken = $arrPersonaToken['access_token'];
        $headers = array(
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$personaToken
        );
        return $headers;
    }

    protected function processErrorResponseBody($responseBody)
    {

        $error = array('error_code'=>null, 'message'=>null);
        $response = json_decode($responseBody, true);

        if(isset($response['error_code']))
        {
            $error['error_code'] = $response['error_code'];
        }

        if(isset($response['message']))
        {
            $error['message'] = $response['message'];
        }

        return $error;
    }
}