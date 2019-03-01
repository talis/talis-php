<?php

namespace Talis\Persona\Client;

trait ClientVersionCache
{
    const COMPOSER_VERSION_CACHE_KEY = 'composer_version';
    const COMPOSER_VERSION_CACHE_TTL_SEC = 3600; // 1 hour

    /**
     * Retrieve the Persona client version
     * @return string Persona client version
     */
    protected function getClientVersion()
    {
        $version = $this->getVersionFromCache();

        if (empty($version)) {
            $version = $this->getVersionFromComposeFile();
            $this->saveClientVersion($version);
        }

        return $version;
    }

    /**
     * Parse this package version from the composer.json file
     * @return string package version, or 'unknown'
     */
    private function getVersionFromComposeFile()
    {
        $version = 'unknown';
        $composerFileContent = file_get_contents(
            __DIR__ . '/../../../../composer.json'
        );

        if (is_string($composerFileContent)) {
            $composer = json_decode($composerFileContent, true);

            if (isset($composer['version'])) {
                $version = $composer['version'];
            }
        }

        return $version;
    }

    /**
     * Save the client version to cache
     * @param string $version version to cache
     */
    private function saveClientVersion($version)
    {
        try {
            $cacheBackend->save(
                self::COMPOSER_VERSION_CACHE_KEY,
                $version,
                self::COMPOSER_VERSION_CACHE_TTL_SEC
            );
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                'unable to save client version to cache',
                [
                    'version' => $version,
                    'exception' => $e,
                ]
            );
        }
    }

    /**
     * Load the version from cache
     * @return string|null version string
     */
    private function getVersionFromCache()
    {
        $cacheBackend = $this->getCacheBackend();

        try {
            return $cacheBackend->fetch(self::COMPOSER_VERSION_CACHE_KEY);
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                'cannot get version from cache',
                [
                    'exception' => $e,
                ]
            );
        }

        return null;
    }

    /**
     * Retrieve the cache backend
     * @return \Doctrine\Common\Cache\CacheProvider
     */
    abstract protected function getCacheBackend();

    /**
     * Retrieve logger
     * @return Logger|\Psr\Log\LoggerInterface
     */
    abstract protected function getLogger();
}