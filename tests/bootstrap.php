<?php

$testingEnvironment = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'synergie-test-config-'.getmypid().'.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

if (file_exists($testingEnvironment['APP_CONFIG_CACHE'])) {
    throw new RuntimeException('Refusing to run tests because the isolated test config cache already exists.');
}

foreach ($testingEnvironment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
