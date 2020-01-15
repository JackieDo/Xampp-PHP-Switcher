<?php

require __DIR__.'/vendor/autoload.php';
set_time_limit(0);

if (! getenv('XPHP_APP_DIR')) {
    Console::breakline();
    Console::line('This script does not accept running as a standalone application.');
    Console::line('Please run application from command "xphp"');
    Console::terminate(null, 1, true);
}

$banner = PHP_EOL
    . "###################################################################################" . PHP_EOL
    . "#  Xampp PHP Switcher, switches between PHP versions for Xampp on Windows OS      #" . PHP_EOL
    . "#---------------------------------------------------------------------------------#" . PHP_EOL
    . "#  Author: Jackie Do <anhvudo@gmail.com>                                          #" . PHP_EOL
    . "#---------------------------------------------------------------------------------#" . PHP_EOL
    . "#  License: MIT (c) Jackie Do <anhvudo@gmail.com>                                 #" . PHP_EOL
    . "###################################################################################" . PHP_EOL;

if (isset($_SERVER['argv'][1])) {
    Console::line($banner);

    if ($_SERVER['argv'][1] == 'install') {
        $installer = new Installer;
        $installer->install();
        Console::terminate(null, 0, true);
    }

    $witcher = new Switcher;

    switch ($_SERVER['argv'][1]) {
        case 'listVersions':
            $witcher->listVersions();
            break;

        case 'addVersion':
            $witcher->addVersion($_SERVER['argv'][2]);
            break;

        case 'removeVersion':
            $witcher->removeVersion($_SERVER['argv'][2]);
            break;

        case 'showVersion':
            $witcher->showVersion($_SERVER['argv'][2]);
            break;

        case 'switchVersion':
            $witcher->switchVersion($_SERVER['argv'][2]);
            break;

        default:
            break;
    }

    Console::terminate(null, 0, true);
}

Console::terminate(null, 0, true);
