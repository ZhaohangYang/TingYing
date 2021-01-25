<?php

namespace App\Model\Cache;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use App\Model\Cache\CacheInterface;
use App\Config\CommonConfig;

class FileCache extends CacheInterface
{
    private static $instance;
    public $cache;
    public $list;

    private function __construct()
    {
        $config = CommonConfig::getCommonConfig();
        $this->cache = new FilesystemAdapter('', 60 * 60, $config['cache_path']);
    }

    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    //克隆方法私有化，防止复制实例
    private function __clone()
    {
    }

    public function get(string $key, callable $callback = null)
    {
        $callback = $this->list[$key] ?? $callback;
        if (!$callback) {
            return null;
        }
        $this->list[$key] = $callback;
        $value = $this->cache->get($key, $callback);
        return $value;
    }

    public function delete()
    {
    }
}
