<?php

require_once __DIR__.'/support/Switcher.php';
require_once __DIR__.'/support/Installer.php';

set_time_limit(0);

if (! getenv('XPHP_APP_DIR')) {
    echo PHP_EOL;
    echo 'This script does not accept running as a standalone application.' . PHP_EOL;
    echo 'Please run application from command "xphp"';
    echo PHP_EOL;
    exit(1);
}

$banner = PHP_EOL
    . "###################################################################################" . PHP_EOL
    . "#  Xampp PHP Switcher, switches between PHP versions for Xampp on Windows OS      #" . PHP_EOL
    . "#---------------------------------------------------------------------------------#" . PHP_EOL
    . "#  Author: Jackie Do <anhvudo@gmail.com>                                          #" . PHP_EOL
    . "#---------------------------------------------------------------------------------#" . PHP_EOL
    . "#  License: MIT (c) Jackie Do <anhvudo@gmail.com>                                 #" . PHP_EOL
    . "###################################################################################" . PHP_EOL . PHP_EOL;

if (isset($_SERVER['argv'][1])) {
    echo $banner;

    if ($_SERVER['argv'][1] == 'install') {
        $installer = new Installer;
        $installer->install();
        exit;
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

        case 'currentInfo':
            $witcher->currentInfo();
            break;

        case 'showInfo':
            $witcher->showInfo($_SERVER['argv'][2]);
            break;

        case 'switchVersion':
            $witcher->switchVersion($_SERVER['argv'][2]);
            break;

        default:
            break;
    }

    exit;
}

exit;
