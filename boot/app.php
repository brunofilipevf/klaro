<?php

require ABSPATH . '/boot/autoload.php';
require ABSPATH . '/boot/config.php';
require ABSPATH . '/boot/secure.php';

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
