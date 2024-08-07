<?php

namespace Talis\Persona\Client;

use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Talis\Persona\Client\ClientVersionCache;

abstract class Base implements LoggerAwareInterface
{
    use ClientVersionCache;
    use LoggerAwareTrait;

    const LOGGER_NAME = 'PERSONA';
    const PERSONA_API_VERSION = '3';

    /**
     * Configuration object
     * @var Array
     */
    protected $config = null;

    /**
     * @var \Doctrine\Common\Cache\CacheProvider
     */
    private $cacheBackend;

    /**
     * @var string
     */
    private $phpVersion;

    /**
     * Constructor
     *
     * @param array $config An array of options with the following keys: <pre>
     *      persona_host: (string) the persona host you'll be making requests to (e.g. 'http://localhost')
     *      userAgent: Consuming application user agent string @since 2.0.0
     *            examples: rl/1723-9095ba4, rl/5.2, rl, rl/5, rl/5.2 (php/5.3; linux/2.5)
     *      cacheBackend: (Doctrine\Common\Cache\CacheProvider) cache storage
     *      cacheKeyPrefix: (string) optional prefix to append to the cache keys
     *      cacheDefaultTTL: (integer) optional cache TTL value
     * @throws \InvalidArgumentException If any of the required config parameters are missing
     * @throws \InvalidArgumentException If the user agent format is invalid
     */
    public function __construct(array $config)
    {
        $this->checkConfig($config);
        $this->config = $config;
        $this->config['persona_oauth_route'] = '/oauth/tokens';

        $userAgentPattern = '' .
            '/^[a-z0-9\-\._]+' .             // name of application
            '(\/' .                          // optional version beginning with /
            '[^\s]+' .                       // anything but whitespace
            ')?' .
            '( \([^\)]+\))?$/i';             // comment surrounded by round brackets

        $isValidUserAgent = preg_match(
            $userAgentPattern,
            $config['userAgent']
        );

        if ($isValidUserAgent == false) {
            throw new \InvalidArgumentException(
                "user agent format is not valid ({$config['userAgent']})"
            );
        }

        $this->logger = $this->get($config, 'logger', null);
        $this->cacheBackend = $config['cacheBackend'];
        $this->phpVersion = phpversion();
    }

    /**
     * Checks the supplied config, verifies that all required parameters are present and
     * contain a non null value;
     *
     * @param array $config the configuration options to validate
     * @throws \InvalidArgumentException If the config is invalid
     */
    protected function checkConfig(array $config)
    {
        $requiredProperties = [
            'userAgent',
            'persona_host',
            'cacheBackend',
        ];

        foreach ($requiredProperties as $requiredProperty) {
            if (!isset($config[$requiredProperty])) {
                throw new \InvalidArgumentException(
                    "Configuration missing $requiredProperty"
                );
            }
        }
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        if ($this->logger == null) {
            $this->logger = new Logger(self::LOGGER_NAME);
        }

        return $this->logger;
    }

    /**
     * Returns a unique id for tracing this request.
     * @return string
     */
    protected function getRequestId()
    {
        $requestId = null;
        if (array_key_exists('HTTP_X_REQUEST_ID', $_SERVER)) {
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'];
        }
        if ($requestId === null && array_key_exists('xid', $_GET)) {
            $requestId = $_GET['xid'];
        }

        return empty($requestId) ? uniqid() : $requestId;
    }

    /**
     * Create a http client
     * @param string $host host to send a request to
     * @return \GuzzleHttp\Client http client
     */
    protected function getHTTPClient($host)
    {
        return new \GuzzleHttp\Client(['base_uri' => $host]);
    }

    /**
     * Create a HTTP request with a predefined set of headers
     * @param string $url url to request
     * @param array $opts options
     * @return mixed http request
     */
    protected function createRequest($url, array $opts)
    {
        $version = $this->getClientVersion();
        $opts = array_merge(
            [
                'headers' => [
                    'Cache-Control' => 'max-age=0, no-cache',
                    'User-Agent' => "{$this->config['userAgent']}"
                        . "persona-php-client/{$version} "
                        . "(php/{$this->phpVersion})",
                    'X-Request-ID' => $this->getRequestId(),
                    'X-Client-Version' => $version,
                    'X-Client-Language' => 'php',
                    'X-Client-Consumer' => $this->config['userAgent'],
                ],
                'method' => 'GET',
                'expectResponse' => true,
                'addContentType' => true,
                'parseJson' => true,
            ],
            $opts
        );

        $body = isset($opts['body']) ? $opts['body'] : null;

        if (isset($opts['bearerToken'])) {
            $opts['headers']['Authorization'] = "Bearer {$opts['bearerToken']}";
        }

        if ($body != null && $opts['addContentType']) {
            $opts['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        $request = new \GuzzleHttp\Psr7\Request(
            $opts['method'],
            $url,
            $opts['headers'],
            $body
        );

        return $request;
    }

    /**
     * Perform the request according to the $curlOptions.
     *
     * @param string $url request url
     * @param array $opts configuration / options:
     *      timeout: (30 seconds) HTTP timeout
     *      body: optional HTTP body
     *      headers: optional HTTP headers
     *      method: (default GET) HTTP method
     *      expectResponse: (default true) parse the http response
     *      addContentType: (default true) add type application/x-www-form-urlencoded
     *      parseJson: (default true) parse the response as JSON
     * @return array|null|string response body
     * @throws NotFoundException If the http status was a 404
     * @throws \Exception If response not 200 and valid JSON
     */
    protected function performRequest($url, array $opts = [])
    {
        $client = $this->getHTTPClient($this->config['persona_host']);
        $request = $this->createRequest($url, $opts);

        try {
            $response = $client->send($request, [
                \GuzzleHttp\RequestOptions::TIMEOUT => isset($opts['timeout']) ? $opts['timeout'] : 30,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $exception) {
            if ($exception->hasResponse()) {
                $status = $exception->getResponse()->getStatusCode();
            } else {
                $status = -1;
            }

            if ($status === 404) {
                throw new NotFoundException();
            }

            throw new \Exception(
                "Did not retrieve successful response code from persona: ${status}",
                $status
            );
        }

        return $this->parseResponse($url, $response, $opts);
    }

    /**
     * Parse the response from Persona.
     * @param string $url url
     * @param \Psr\Http\Message\ResponseInterface $response response from persona
     * @param array $opts options
     * @return string|array
     */
    protected function parseResponse($url, \Psr\Http\Message\ResponseInterface $response, array $opts)
    {
        $parseJson = $this->get($opts, 'parseJson', true) === true;
        $expectResponse = $this->get($opts, 'expectResponse', true) === true;
        $expectedResponseCode = $expectResponse ? 200 : 204;
        $statusCode = $response->getStatusCode();

        if ($statusCode !== $expectedResponseCode) {
            $this->getLogger()->error(
                'Did not retrieve expected response code',
                ['opts' => $opts, 'url' => $url, 'response' => $response]
            );

            throw new \Exception(
                'Did not retrieve expected response code from persona',
                $statusCode
            );
        }

        // Not expecting a body to be returned
        if ($expectResponse === false) {
            return null;
        }

        $responseBody = (string) $response->getBody();

        if ($parseJson === false) {
            return $responseBody;
        }

        $json = json_decode($responseBody, true);

        if (empty($json)) {
            $this->getLogger()->error(
                "Could not parse response {$response} as JSON"
            );

            throw new \Exception(
                "Could not parse response from persona as JSON {$responseBody}"
            );
        }

        return $json;
    }

    /**
     * Get a value from a array, or return a default if the key doesn't exist.
     * @param array $array array to find the value within
     * @param string $key key to find the value from
     * @param mixed $default value to return when key doesn't exist
     * @return mixed
     */
    protected function get(array $array, $key, $default = null)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        return $default;
    }

    /**
     * Retrieve the cache backend
     * @return \Doctrine\Common\Cache\CacheProvider
     */
    protected function getCacheBackend()
    {
        return $this->cacheBackend;
    }

    /**
     * Return Persona host from the configuration object
     * @return string
     */
    protected function getPersonaHost()
    {
        return $this->config['persona_host'] . '/' . self::PERSONA_API_VERSION;
    }

    /**
     * Attempts to find an access token based on the current request.
     * It first looks at $_SERVER headers for a Bearer, failing that
     * it checks the $_GET and $_POST for the access_token param.
     * If it can't find one it throws an exception.
     *
     * @return string access token
     * @throws \Exception Missing or invalid access token
     */
    protected function getTokenFromRequest()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }

            $withoutPrefix = strtolower(substr($key, 5));
            $removedUnderscores = str_replace('_', ' ', $withoutPrefix);
            $header = str_replace(' ', '-', ucwords($removedUnderscores));
            $headers[$header] = $value;
        }

        if (isset($headers['Bearer'])) {
            if (!preg_match('/Bearer\s(\S+)/', $headers['Bearer'], $matches)) {
                throw new \Exception('Malformed auth header');
            }

            return $matches[1];
        }

        if (isset($_GET['access_token'])) {
            return $_GET['access_token'];
        }

        if (isset($_POST['access_token'])) {
            return $_POST['access_token'];
        }

        $this->getLogger()->error('No OAuth token supplied in headers, GET or POST');
        throw new \Exception('No OAuth token supplied');
    }
}
