<?php

namespace App\Config;

class CommonConfig
{
    public static function getCommonConfig()
    {
        return [
            "cache_path" => BASE_PATH . "/runtime"
        ];
    }
}
