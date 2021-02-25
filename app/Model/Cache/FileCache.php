<?php

namespace App\Model\Cache;

use App\Config\CommonConfig;
use App\Model\Cache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class FileCache extends CacheInterface
{
    private static $instance;
    public $cache;
    public $list;

    private function __construct()
    {
        $config      = CommonConfig::getCommonConfig();
        $this->cache = new FilesystemAdapter('', 60 * 60, $config['cache_path']);
    }

    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone()
    {
    }

    public function set(string $key, callable $callback = null)
    {
        $this->list[$key] = $callback;
        return $this->get($key, $callback);
    }

    public function get(string $key, callable $callback = null)
    {
        $this->list[$key] = $callback ?: $this->list[$key];
        if ($this->list[$key]) {
            $value = $this->cache->get($key, $this->list[$key]);
        }

        return $value ?? null;
    }

    public function delete($key)
    {
        $this->cache->delete($key);
    }

    public function getExample($key)
    {
        $value = FileCache::getInstance()->get('my_cache_key', function (ItemInterface $item) {
            $item->expiresAfter(3600);
            $value = 'foobar';
            return $value;
        });
    }
}
