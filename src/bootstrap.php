<?php

ini_set('display_errors', true);

date_default_timezone_set('Europe/Prague');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (0 === error_reporting()) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/../vendor/autoload.php';
