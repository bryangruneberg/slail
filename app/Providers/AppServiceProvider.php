<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    const SLAIL_CONFIG_DEFAULT_FILENAME = __DIR__ . '/../config.json';

    public function boot()
    {
        $this->readConfiguration();
    }

    public function register()
    {

    }

    public function readConfiguration() 
    {
        if(getenv('SLAIL_TOKEN')) {
            return;
        }

        $SLAIL_CONFIG_FILENAME = AppServiceProvider::SLAIL_CONFIG_DEFAULT_FILENAME;

        if(isset($_SERVER['HOME'])) {
            if(file_exists( $_SERVER['HOME'] . DIRECTORY_SEPARATOR . ".slail.json")) {
                $SLAIL_CONFIG_FILENAME = $_SERVER['HOME'] . DIRECTORY_SEPARATOR . ".slail.json";
            }
        } else if(function_exists("posix_getuid") && function_exists("posix_getpwuid")) {
            $user = posix_getpwuid(posix_getuid());
            if($user && isset($user['dir'])) {
                if(file_exists( $user['dir'] . DIRECTORY_SEPARATOR . ".slail.json")) {
                    $SLAIL_CONFIG_FILENAME = $user['dir'] . DIRECTORY_SEPARATOR . ".slail.json";
                }
            }
        }

        if (!file_exists($SLAIL_CONFIG_FILENAME)) {
            throw new Exception("No config.json found in the root directory - see documentation for details");
        }

        $slailConfig = json_decode(file_get_contents($SLAIL_CONFIG_FILENAME));
        if (json_last_error() > 0) {
            throw new Exception(sprintf("Unable to decode config.json: %s",
                json_last_error_msg()));
        }

        if (empty($slailConfig->token)) {
            throw new Exception('"token" does not exist in config.json');
        }

        putenv('SLAIL_TOKEN=' . $slailConfig->token);
    }
}
