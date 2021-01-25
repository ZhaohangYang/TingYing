<?php

namespace App\Model\Cache;

interface CacheInterface
{
    public function get();
    public function delete();
}
