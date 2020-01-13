<?php

require_once __DIR__.'/Console.php';
require_once __DIR__.'/Setting.php';
require_once __DIR__.'/VersionRepository.php';
require_once __DIR__.'/helpers.php';

if (! defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

class Application
{
    protected $versionRepository = null;
    protected $setting           = null;
    protected $paths             = [];

    public function __construct($initAdditional = true)
    {
        Console::setDefaultMessages(['terminate' => 'Xampp PHP Switcher is terminating...']);

        $this->setting = new Setting;

        $this->defineAppPaths();

        if ($initAdditional) {
            $this->initAdditional();
        }
    }

    protected function stopApache($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Do you want to stop Apache?';
            $confirm  = Console::confirm($question);
        } else {
            $confirm = true;
        }

        if ($confirm) {
            $message = 'Stopping Apache Httpd...';
            Console::line($message, false);
            self::powerExec('"' . $this->paths['xamppDir'] . '\apache_stop.bat"', '-w -i -n');
            Console::line('Successful', true, max(73 - strlen($message), 1));
        }
    }

    protected function startApache($askConfirm = true, $question = null)
    {
        if (self::isApacheRunning()) {
            self::restartApache($askConfirm, ($question ? str_replace('start', 'restart', $question) : null));
        } else {
            if ($askConfirm) {
                $question = $question ?: 'Do you want to start Apache?';
                $confirm  = Console::confirm($question);
            } else {
                $confirm = true;
            }

            if ($confirm) {
                $message = 'Starting Apache Httpd...';
                Console::line($message, false);
                self::powerExec('"' . $this->paths['xamppDir'] . '\apache_start.bat"', '-i -n');
                Console::line('Successful', true, max(73 - strlen($message), 1));
            }
        }
    }

    protected function restartApache($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Do you want to restart Apache?';
            $confirm  = Console::confirm($question);
        } else {
            $confirm = true;
        }

        if ($confirm) {
            Console::breakline();

            self::stopApache(false);
            self::startApache(false);
        }
    }

    protected function registerPath($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Do you want to change the path of this app to "' . $this->paths['appDir'] . '"?';
            $confirm  = Console::confirm($question);
        } else {
            $confirm = true;
        }

        if ($confirm) {
            $message = 'Registering application\'s path into Windows Path Environment...';
            Console::line($message, false);

            self::powerExec('cscript "' . $this->paths['pathRegister'] . '" "' .$this->paths['appDir']. '"', '-w -i -e -n', $outputVal, $exitCode);

            if ($exitCode == 0) {
                Console::line('Successful', true, max(73 - strlen($message), 1));
                return true;
            }

            Console::line('Failed', true, max(77 - strlen($message), 1));
            return false;
        }
    }

    protected function initAdditional()
    {
        if (!$this->paths['xamppDir'] || !$this->paths['apacheDir'] || !$this->paths['phpDir']) {
            $this->detectXamppPaths();
        }

        $this->paths['httpdXampp']    = $this->paths['apacheDir'] . '\conf\extra\httpd-xampp.conf';
        $this->paths['httpdXamppPHP'] = $this->paths['apacheDir'] . '\conf\extra\httpd-xampp-php{{php_major_version}}.conf';

        $this->versionRepository = new VersionRepository(get_architecture_phpdir($this->paths['phpDir']), $this->paths['xamppDir'] . '\phpRepository', false);

        return $this;
    }

    protected function powerExec($command, $arguments = null, &$outputArray = null, &$statusCode = null)
    {
        if (is_file($this->paths['powerExecutor'])) {
            if (is_array($arguments)) {
                $arguments = '"' . trim(implode('" "', $arguments)) . '"';
            } elseif (is_string($arguments)) {
                $arguments = trim($arguments);
            } else {
                $arguments = trim(strval($arguments));
            }

            $outputArray = $statusCode = null;

            return exec('cscript //NoLogo "' . $this->paths['powerExecutor'] . '" ' . $arguments . ' ' . $command, $outputArray, $statusCode);
        }

        $message     = 'Cannot find the "' . $this->paths['powerExecutor'] . '" implementer.';
        $outputArray = [$message];
        $statusCode  = 1;

        return $message;
    }

    protected function isApacheRunning()
    {
        $lastRow = exec('tasklist /NH /FI "IMAGENAME eq httpd.exe" 2>nul', $output, $status);

        if ($lastRow == 'INFO: No tasks are running which match the specified criteria.') {
            return false;
        }

        return true;
    }

    private function defineAppPaths()
    {
        $appDir = realpath(getenv('XPHP_APP_DIR'));
        $tmpDir = getenv('XPHP_TMP_DIR');

        $this->paths['appDir']             = $appDir;
        $this->paths['tmpDir']             = $tmpDir;
        $this->paths['httpdXamppTemplate'] = $appDir . '\templates\xampp_config\httpd-xampp.conf.tpl';
        $this->paths['pathRegister']       = $appDir . '\support\PathRegister.vbs';
        $this->paths['powerExecutor']      = $appDir . '\support\PowerExec.vbs';
        $this->paths['needBeStandardized'] = $appDir . '\support\NeedBeStandardized.php';

        if (! is_dir($this->paths['tmpDir'])) {
            @mkdir($this->paths['tmpDir'], 0755, true);
        }
    }

    private function detectXamppPaths()
    {
        // Force reload settings
        $this->setting->reloadSettings();

        $xamppDir  = realpath($this->setting->get('DirectoryPaths', 'Xampp'));
        $apacheDir = $this->setting->get('DirectoryPaths', 'Apache');
        $phpDir    = $this->setting->get('DirectoryPaths', 'Php');

        // define Xampp directory path
        if (! $xamppDir || ! is_file($xamppDir . '\xampp-control.exe')) {
            Console::breakline();

            $message = 'Cannot find Xampp directory.' . PHP_EOL;
            $message .= 'Please check the configuration path to the Xampp directory in file "' . $this->paths['appDir'] . '\settings.ini".';

            Console::terminate($message, 1);
        }

        $this->paths['xamppDir'] = $xamppDir;

        // define Apache directory path
        if (! $apacheDir) {
            $apacheDir = $xamppDir . '\apache';
        }

        if (! is_file($apacheDir . '\bin\httpd.exe')) {
            Console::breakline();

            $message = 'Cannot find Apache directory.' . PHP_EOL;
            $message .= 'Please check the configuration path to the Apache directory in file "' . $this->paths['appDir'] . '\settings.ini".';

            Console::terminate($message, 1);
        }

        $this->paths['apacheDir'] = $apacheDir;

        // define PHP directory path
        if (! $phpDir) {
            $phpDir = $xamppDir . '\php';
        }

        if (! is_file($phpDir . '\php.exe')) {
            Console::breakline();

            $message = 'Cannot find PHP directory.' . PHP_EOL;
            $message .= 'Please check the configuration path to the PHP directory in file "' . $this->paths['appDir'] . '\settings.ini".';

            Console::terminate($message, 1);
        }

        $this->paths['phpDir'] = $phpDir;

        return $this;
    }
}
