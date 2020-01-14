<?php

require_once __DIR__.'/Application.php';
require_once __DIR__.'/helpers.php';

if (! defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

class Installer extends Application
{
    public function __construct()
    {
        parent::__construct(false);

        if (is_file($this->paths['appDir'] . '\settings.ini')) {
            Console::breakline();
            Console::line('Xampp PHP Switcher is already integrated into Xampp.');
            Console::line('No need to do it again.');
            exit;
        }
    }

    public function install()
    {
        Console::line('Welcome to Xampp PHP Switcher installer.');
        Console::line('This installer will perform following tasks:');
        Console::breakline();

        Console::line('1. Register path into Windows Path Environment Variable, so you can call this app anywhere later.');
        Console::line('2. Create a sample file "httpd-xampp.conf.tpl" to serve for adding new PHP build later.');
        Console::line('3. Improve current "httpd-xampp.conf" file to easily configure Xampp every time you switches PHP version.');
        Console::line('4. Move directory of the current PHP build into the repository and create symbolic link to it.');
        Console::breakline();

        $continue = Console::confirm('Do you agree to continue?');

        if (! $continue) {
            Console::terminate(null, 1);
        }

        Console::breakline();

        $this->askInstallConfig();

        Console::hrline();
        Console::line('Start intergrating Xampp PHP Switcher into your Xampp.');
        Console::breakline();

        $this->registerPath();
        $this->editHttpdXampp();
    }

    private function askInstallConfig()
    {
        $phpDir = dirname(PHP_BINARY);

        Console::line('First, provide the path to your Xampp directory for Xampp PHP Switcher.');

        if (is_file($phpDir . '\..\xampp-control.exe')) {
            $detectedXamppDir = realpath($phpDir . '\..\\');
            Console::line('Xampp PHP Switcher has detected that directory "' . $detectedXamppDir . '" could be your Xampp directory.');
            Console::breakline();
            $confirmXamppDir = Console::confirm('Is that the actual path to your Xampp directory?');
            Console::breakline();
        }

        $xamppDir = (isset($detectedXamppDir) && $confirmXamppDir) ? $detectedXamppDir : $this->tryGetXamppDir();
        $this->setting->set('DirectoryPaths', 'Xampp', $xamppDir);
        $this->paths['xamppDir'] = $xamppDir;

        if (is_file($xamppDir . '\apache\bin\httpd.exe')) {
            $this->paths['apacheDir'] = $xamppDir . '\apache';
        } else {
            Console::line('Next, because Xampp PHP Switcher does not detect the path to the Apache directory, you need to provide it manually.');
            Console::breakline();
            $apacheDir = $this->tryGetApacheDir();
            $this->setting->set('DirectoryPaths', 'Apache', $apacheDir);
            $this->paths['apacheDir'] = $apacheDir;
        }

        if (is_file($xamppDir . '\php\php.exe')) {
            $this->paths['phpDir'] = $xamppDir . '\php';
        } else {
            Console::line('Next, because Xampp PHP Switcher does not detect the path to the PHP directory, you need to provide it manually.');
            Console::breakline();
            $phpDir = $this->tryGetPHPDir();
            $this->setting->set('DirectoryPaths', 'PHP', $phpDir);
            $this->paths['phpDir'] = $phpDir;
        }

        if (! $this->setting->save()) {
            Console::breakline();
            Console::line('Installation settings cannot be saved.');
            Console::terminate('Cancel the installation.', 1);
        }

        $this->setting->reloadSettings();
        $this->initAdditional();
    }

    protected function registerPath($askConfirm = true, $question = null)
    {
        $result = parent::registerPath(false);

        if (! $result) {
            Console::breakline();
            Console::line('Don\'t worry. This does not affect the installation process.');
            Console::line('You can register the path manually after installation.');
            Console::line('See [https://helpdeskgeek.com/windows-10/add-windows-path-environment-variable/] to know how to add Windows Path Environment Variable.');
        }
    }

    private function editHttpdXampp()
    {
        // Detecting informations of current PHP
        $message = 'Detecting informations of current PHP build...';
        Console::line($message, false);

        $version      = get_version_phpdir($this->paths['phpDir']);
        $majorVersion = get_major_phpversion($version);
        $repoSettings = [
            'BuildInfo' => [
                'Version'      => $version,
                'Architecture' => get_architecture_phpdir($this->paths['phpDir']),
                'Compiler'     => get_compiler_phpdir($this->paths['phpDir']),
                'BuildDate'    => get_builddate_phpdir($this->paths['phpDir']),
                'ZendVersion'  => get_zendversion_phpdir($this->paths['phpDir']),
            ],
            'RepoImporting' => [
                'PathStandardized' => $this->paths['xamppDir']
            ]
        ];

        @create_ini_file($this->paths['phpDir'] . '\.repo', $repoSettings, true);
        Console::line('Successful', true, max(73 - strlen($message), 1));

        // Create sample file
        $message = 'Creating sample file "httpd-xampp.conf.tpl"...';
        Console::line($message, false);

        $httpdXampp         = $this->paths['httpdXampp'];
        $httpdXamppPHP      = str_replace('{{php_major_version}}', $majorVersion, $this->paths['httpdXamppPHP']);
        $httpdXamppTemplate = $this->paths['httpdXamppTemplate'];

        if (is_file($httpdXamppPHP)) {
            $originContent    = @file_get_contents($httpdXamppPHP);
            $httpdXamppCloned = true;
        } else {
            $originContent    = @file_get_contents($httpdXampp);
            $httpdXamppCloned = false;
        }

        if (! is_file($httpdXamppTemplate)) {
            $templateDir = dirname($httpdXamppTemplate);

            if (! is_dir($templateDir)) {
                @mkdir($templateDir, 0755, true);
            }

            @file_put_contents($httpdXamppTemplate, str_replace('php' . $majorVersion, 'php{{php_major_version}}', $originContent));
        }

        Console::line('Successful', true, max(73 - strlen($message), 1));

        // Backup and improve httpd-xampp.conf
        $message = 'Backing up and improve the "httpd-xampp.conf" file...';
        Console::line($message, false);

        $backupFile = dirname($httpdXampp) . '\backup\httpd-xampp.conf';

        if (! is_file($backupFile)) {
            $backupDir = dirname($backupFile);

            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            @file_put_contents($backupFile, $originContent);
        }

        if (! $httpdXamppCloned) {
            @file_put_contents($httpdXamppPHP, $originContent);
        }

        $newContent = 'Include "' . relative_path($this->paths['apacheDir'], $httpdXamppPHP, '/') . '"' . PHP_EOL;
        @file_put_contents($httpdXampp, $newContent);
        Console::line('Successful', true, max(73 - strlen($message), 1));

        // Create temporary files to continue config in batch file
        @file_put_contents($this->paths['tmpDir'] . '\.phpdir', $this->paths['phpDir']);
        @file_put_contents($this->paths['tmpDir'] . '\.phprepo', $this->versionRepository->buildStoragePath($version));
    }

    private function tryGetXamppDir()
    {
        $xamppDir = '';

        $repeat = 0;
        while (! is_file(rtrim($xamppDir, '\\/') . '\xampp-control.exe')) {
            if ($repeat == 4) {
                Console::line('You have not provided correct information multiple times.');
                Console::terminate('Cancel the installation.', 1);
            }

            if ($repeat == 0) {
                $xamppDir = Console::ask('Enter the path to your Xampp directory');
            } else {
                Console::line('The path provided is not the path to the actual Xampp directory.');
                $xamppDir = Console::ask('Please provide it again');
            }

            Console::breakline();
            $xamppDir = str_replace('/', DS, $xamppDir);
            $repeat++;
        }

        return $xamppDir;
    }

    private function tryGetApacheDir()
    {
        $apacheDir = '';

        $repeat = 0;
        while (! is_file(rtrim($apacheDir, '\\/') . '\bin\httpd.exe')) {
            if ($repeat == 4) {
                Console::line('You have not provided correct information multiple times.');
                Console::terminate('Cancel the installation.', 1);
            }

            if ($repeat == 0) {
                $apacheDir = Console::ask('Enter the path to your Apache directory');
            } else {
                Console::line('The path provided is not the path to the actual Apache directory.');
                $apacheDir = Console::ask('Please provide it again');
            }

            Console::breakline();
            $apacheDir = str_replace('/', DS, $apacheDir);
            $repeat++;
        }

        return $apacheDir;
    }

    private function tryGetPHPDir()
    {
        $phpDir = '';

        $repeat = 0;
        while (! is_file(rtrim($phpDir, '\\/') . '\php.exe')) {
            if ($repeat == 4) {
                Console::line('You have not provided correct information multiple times.');
                Console::terminate('Cancel the installation.', 1);
            }

            if ($repeat == 0) {
                $phpDir = Console::ask('Enter the path to your PHP directory');
            } else {
                Console::line('The path provided is not the path to the actual PHP directory.');
                $phpDir = Console::ask('Please provide it again');
            }

            Console::breakline();
            $phpDir = str_replace('/', DS, $phpDir);
            $repeat++;
        }

        return $phpDir;
    }
}
