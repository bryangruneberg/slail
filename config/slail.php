<?php

const SLAIL_CONFIG_FILENAME = __DIR__ . '/../config.json';

if (!file_exists(SLAIL_CONFIG_FILENAME)) {
    throw new Exception("No config.json found in the root directory - see documentation for details");
}

$slailConfig = json_decode(file_get_contents(SLAIL_CONFIG_FILENAME));
if (json_last_error() > 0) {
    throw new Exception(sprintf("Unable to decode config.json: %s",
      json_last_error_msg()));
}

if (empty($slailConfig->token)) {
    throw new Exception('"token" does not exist in config.json');
}

return [
  'token' => $slailConfig->token,
];