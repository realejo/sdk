<?php

namespace Realejo\Sdk\Cache;

use Laminas\Cache\StorageFactory;
use RuntimeException;
use Laminas\Cache\Storage\Adapter\Filesystem;

class CacheService
{
    /**
     * @var Filesystem
     */
    protected $cache;

    protected $cacheDir;

    /**
     * Configura o cache
     *
     * @param string $class
     * @return Filesystem
     */
    public function getFrontend($class = '')
    {
        $cacheService = new self();

        $path = $this->getCachePath($class);

        if (!empty($path)) {
            // Configura o cache
            $cacheService->cache = StorageFactory::factory(
                [
                    'adapter' => [
                        'name' => 'filesystem',
                        'options' => [
                            'cache_dir' => $path,
                            'namespace' => $this->getNamespace($class),
                            'dir_level' => 0,
                        ],
                    ],
                    'plugins' => [
                        // Don't throw exceptions on cache errors
                        'exception_handler' => [
                            'throw_exceptions' => false
                        ],
                        'Serializer'
                    ],
                    'options' => [
                        'ttl' => 86400
                    ]
                ]
            );
        }

        return $cacheService->cache;
    }

    /**
     * Retorna o padrão do namespace a ser usado no cache
     *
     * @param string $class
     *
     * @return string
     */
    public function getNamespace(string $class): string
    {
        return str_replace(['_', '\\', '/'], '.', strtolower($class));
    }

    /**
     * Apaga o cache de consultas do model
     */
    public function clean(): void
    {
        // Apaga o cache
        $this->getFrontend()->flush();
    }

    /**
     * Retorna a pasta raiz de todos os caches
     * @return string
     */
    public function getCacheRoot(): string
    {
        $cacheRoot = $this->cacheDir;

        // Verifica se a pasta de cache existe
        if ($cacheRoot === null) {
            if (defined('APPLICATION_DATA') === false) {
                throw new RuntimeException('A pasta raiz do data não está definido em APPLICATION_DATA');
            }
            $cacheRoot = APPLICATION_DATA . '/cache';
        }

        // Verifica se a pasta do cache existe
        if (!file_exists($cacheRoot)) {
            $oldUMask = umask(0);
            if (!mkdir($cacheRoot, 0777, true) && !is_dir($cacheRoot)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $cacheRoot));
            }
            umask($oldUMask);
        }

        // retorna a pasta raiz do cache
        return realpath($cacheRoot);
    }

    /**
     * Retorna a pasta de cache para o model baseado no nome da classe
     * Se a pasta não existir ela será criada
     *
     * @param string $class Nome da classe a ser usada
     *
     * @return string
     */
    public function getCachePath($class = ''): string
    {
        // Define a pasta de cache
        $cachePath = $this->getCacheRoot() . '/' . str_replace(['_', '\\'], '/', strtolower($class));

        // Verifica se a pasta do cache existe
        if (!file_exists($cachePath)) {
            $oldumask = umask(0);
            if (!mkdir($cachePath, 0777, true) && !is_dir($cachePath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $cachePath));
            }
            umask($oldumask);
        }

        if (!is_writable($cachePath)) {
            throw new RuntimeException("Pasta $cachePath nẽo tem permissão de escrita");
        }

        // Retorna a pasta de cache
        return realpath($cachePath);
    }

    /**
     * Ignora o backend e apaga os arquivos do cache. inclui as sub pastas.
     * Serão removidos apenas os arquivos de cache e não as pastas
     *
     * @param string $path
     */
    public function completeCleanUp($path): void
    {
        if (is_dir($path)) {
            $results = scandir($path);
            foreach ($results as $result) {
                if ($result === '.' || $result === '..') {
                    continue;
                }

                if (is_file($path . '/' . $result)) {
                    unlink($path . '/' . $result);
                }

                if (is_dir($path . '/' . $result)) {
                    $this->completeCleanUp($path . '/' . $result);
                }
            }
        }
    }

    public function setCacheDir($cacheDir): void
    {
        $this->cacheDir = $cacheDir;
    }
}
