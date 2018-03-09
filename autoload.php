<?php
// Autoload GW\DailyThought namespace
spl_autoload_register(function ($class_name) {
    $parts = explode('\\', strtolower($class_name));

    $supported = array('gw');
    if (count($parts) > 2 && $parts[0] == 'gw' && $parts[1] == 'contentlock') {
        $filename = implode('/', array_merge(array(__DIR__, 'lib'), $parts)) . '.php';
        include_once $filename;
    }
});
