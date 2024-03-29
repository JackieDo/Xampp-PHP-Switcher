<?php

namespace PHPSwitcher\Support;

class Handler extends Application
{
    protected $currentStoragePath;
    protected $currentVersion;

    public function __construct()
    {
        if (! is_file(getenv('XPHP_APP_DIR') . '\settings.ini')) {
            $this->requireInstall();
        }

        parent::__construct();

        $this->currentStoragePath = readlink($this->paths['phpDir']);

        if ($this->currentStoragePath == $this->paths['phpDir']) {
            $this->requireInstall();
        }

        $this->currentVersion = get_version_phpdir($this->paths['phpDir']);
    }

    public function showVersion($version = null)
    {
        if ($version == 'current' || (empty($version) && count($this->repository->versions) == 1)) {
            $version = $this->currentVersion;
            $message = 'The current PHP build has the following information:';
        } else {
            $version = $this->getVersionOrList($version, 'Choose one of the following builds to show details:');
            $message = 'The PHP build you require has the following information:';
        }

        if (! array_key_exists($version, $this->repository->versions)) {
            Console::terminate('Sorry! Do not found any PHP version that you entered.', 1);
        }

        $architecture     = $this->repository->versions[$version]['architecture'];
        $isBuiltInVersion = $this->isBuiltInVersion($version);

        Console::line($message);
        Console::breakline();
        Console::line('Version      : ' . $version . ' ('. (($isBuiltInVersion) ? 'Built-in' : 'Add-on') . ' version)');
        Console::line('Storage path : ' . $this->repository->versions[$version]['storagePath']);
        Console::line('Compiler     : ' . $this->repository->versions[$version]['compiler']);
        Console::line('Architecture : ' . $architecture . ' bit (' . (($architecture == '32') ? 'x86' : 'x64') . ')');
        Console::line('Build date   : ' . $this->repository->versions[$version]['buildDate']);
        Console::line('Zend version : ' . $this->repository->versions[$version]['zendVersion']);

        Console::breakline();
        Console::hrline();
        Console::terminate('Your request is completed.');
    }

    public function listVersions()
    {
        $totalVersions = count($this->repository->versions);

        if ($totalVersions == 1) {
            Console::line('There is only one PHP build in the repository as follows:');
        } else {
            Console::line('There are ' . $totalVersions . ' PHP builds in repository as follows:');
        }

        Console::breakline();

        $maxLenVersion = max(array_map(function($item) {
            return strlen($item);
        }, array_keys($this->repository->versions)));

        $maxLenStoragePath = max(array_map(function($item) {
            return strlen($item['storagePath']);
        }, $this->repository->versions));

        $count = 0;

        foreach ($this->repository->versions as $version => $info) {
            $isBuiltInVersion = $this->isBuiltInVersion($version);

            $col_1_content = str_pad(++$count, strlen($totalVersions), ' ', STR_PAD_LEFT) . '.  ';
            $col_2_content = 'Version ' . str_pad($version, $maxLenVersion + 2);
            $col_3_content = '-  Stored at: ' . str_pad($info['storagePath'], $maxLenStoragePath + 2);

            Console::line($col_1_content, false);
            Console::line($col_2_content, false);
            Console::line($col_3_content, false);

            if ($isBuiltInVersion) {
                Console::line('-  Built-in', false);
            } else {
                Console::line('-  Add-on', false);
            }

            if ($this->isCurrentVersion($version)) {
                Console::line(', in use');
            } else {
                Console::breakline();
            }
        }

        Console::breakline();
        Console::hrline();
        Console::terminate('Your request is completed.');
    }

    public function addVersion($source = null)
    {
        // Compatibility verification
        if (! $source) {
            Console::line('Please provide the path to new Xampp PHP directory you want to add.');
            $source = Console::ask('Enter the path');
            Console::breakline();
        }

        if (! maybe_phpdir($source)) {
            Console::line('The directory you provided is not PHP directory.');
            Console::line('Cancel the adding process.');
            Console::breakline();
            Console::terminate(null, 1);
        }

        $architecture = get_architecture_phpdir($source);

        if ($architecture != $this->repository->architecture) {
            Console::line('The directory that you provided is not compatible with your Xampp.');
            Console::line('Cancel the adding process.');
            Console::breakline();
            Console::terminate(null, 1);
        }

        $version = get_version_phpdir($source);

        if (array_key_exists($version, $this->repository->versions)) {
            Console::line('The directory you provided contains the PHP version you already have.');
            Console::line('No need to add this version.');
            Console::breakline();
            Console::terminate();
        }

        $needBuildConfig    = false;
        $phpMajorVersion    = get_major_phpversion($version);
        $configFile         = str_replace('{{php_major_version}}', $phpMajorVersion, $this->paths['httpdXamppPHP']);
        $configTemplateFile = str_replace('{{php_major_version}}', $phpMajorVersion, $this->paths['httpdXamppTemplate']);
        $configSourceFile   = realpath($source . '\..\apache\conf\extra\httpd-xampp.conf');

        if (! is_file($configFile)) {
            $needBuildConfig = true;

            if (is_file($configTemplateFile)) {
                $configContent = @file_get_contents($configTemplateFile);
                $configContent = str_replace('{{xamppDir}}', unixstyle_path($this->paths['xamppDir']), $configContent);
            } elseif (is_file($configSourceFile)) {
                $configContent = @file_get_contents($configSourceFile);
                $configContent = preg_replace('/(\"|\')\/xampp/', '${1}' . unixstyle_path($this->paths['xamppDir']), $configContent);
            } else {
                Console::line('This PHP version is not supported yet.');
                Console::line('Cancel the adding process.');
                Console::breakline();
                Console::terminate();
            }
        }

        // Adding confirmation
        $compiler    = get_compiler_phpdir($source);
        $buildDate   = get_builddate_phpdir($source);
        $zendVersion = get_zendversion_phpdir($source);

        Console::line('The PHP build you provided has the following information:');
        Console::breakline();
        Console::line('Version      : ' . $version);
        Console::line('Compiler     : ' . $compiler);
        Console::line('Architecture : ' . $architecture . ' bit (' . (($architecture == '32') ? 'x86' : 'x64') . ')');
        Console::line('Build date   : ' . $buildDate);
        Console::line('Zend version : ' . $zendVersion);
        Console::breakline();

        if (! Console::confirm('Do you want to continue the adding process?')) {
            Console::breakline();
            Console::terminate('The action is canceled by user.');
        }

        Console::breakline();
        Console::hrline();
        Console::line('Start adding new PHP build.');
        Console::breakline();

        // Copy into repository
        $message = 'Copying directory of new PHP build into the repository...';
        Console::line($message, false);

        $importResult = $this->repository->importBuild($source, [
            'version'      => $version,
            'architecture' => $architecture,
            'compiler'     => $compiler,
            'buildDate'    => $buildDate,
            'zendVersion'  => $zendVersion
        ], false);

        if ($importResult['error_code'] != 0) {
            Console::line('Failed', true, max(77 - strlen($message), 1));
            Console::breakline();
            Console::terminate(null, 1);
        }

        Console::line('Successful', true, max(73 - strlen($message), 1));

        // Standardize paths
        $message = 'Standardize paths in new PHP build to work correctly with your Xampp...';
        Console::line($message, false);

        $storagePath   = $importResult['data']['storagePath'];
        $storageConfig = $importResult['data']['storageConfig'];

        if ($storageConfig['RepoImporting']['PathStandardized']) {
            $standardizePattern     = '/' . preg_quote($storageConfig['RepoImporting']['PathStandardized'], '/') . '/i';
            $standardizeReplacement = $this->paths['xamppDir'];
        } else {
            $standardizePattern     = '/([\!\=\'\"\n]{1}[\s]?)(\w\:)?\\\\xampp/i';
            $standardizeReplacement = '${1}' . $this->paths['xamppDir'];
        }

        $standardizeActions = [
            [
                'type'        => 'replace',
                'pattern'     => $standardizePattern,
                'replacement' => $standardizeReplacement,
                'files'       => $this->prepareStandardizeList()
            ],
            [
                'type'        => 'replace',
                'pattern'     => '/' . preg_quote($this->paths['xamppDir'] . '\php/Text/Highlighter/generate.bat', '/') . '/i',
                'replacement' => '"' . $this->paths['xamppDir'] . '\php\Text\Highlighter\generate.bat"',
                'files'       => ['Text\Highlighter\generate.bat']
            ]
        ];

        if (! $this->repository->editBuild($version, $standardizeActions, false)) {
            Console::line('Failed', true, max(77 - strlen($message), 1));
            Console::breakline();
            Console::line('Removing recently added PHP build...');
            $this->repository->removeBuild($version, false);
            Console::terminate(null, 1);
        }

        $storageConfig['RepoImporting']['PathStandardized'] = $this->paths['xamppDir'];
        $this->repository->saveStorageConfig($storagePath, $storageConfig);
        Console::line('Successful', true, max(73 - strlen($message), 1));

        // Create httpd-xamm-php{{php_major_version}}.conf
        if ($needBuildConfig) {
            $message = 'Creating the "httpd-xampp.conf" file specific to the new PHP build...';
            Console::line($message, false);

            @file_put_contents($configFile, $configContent);

            Console::line('Successful', true, max(73 - strlen($message), 1));
        }

        // Notify adding result
        Console::breakline();
        Console::line('New PHP build has been added to the repository at: ' . $storagePath);

        // Ask to add more
        Console::breakline();
        Console::hrline();

        $addMore = Console::confirm('Do you want to add another PHP build?');

        if ($addMore) {
            Console::breakline();
            $this->addVersion();
        }

        // Exit
        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    public function removeVersion($version = null)
    {
        $version = $this->getVersionOrList($version, 'You can choose one of the following builds to remove:', false);

        if (! array_key_exists($version, $this->repository->versions)) {
            Console::terminate('Sorry! Do not found any PHP version that you entered.', 1);
        }

        if ($this->isCurrentVersion($version)) {
            Console::terminate('You are running on this PHP version, so you cannot remove it.', 1);
        }

        if ($this->isBuiltInVersion($version)) {
            Console::terminate('This is the original version of Xampp, does not allow removal.', 1);
        }

        $confirmRemove = Console::confirm('Are you sure you want to remove PHP version "' . $version . '" ?');
        Console::breakline();

        if (! $confirmRemove) {
            Console::terminate('Cancel the action.');
        }

        Console::hrline();
        Console::line('Start removing PHP version "' . $version . '" from repository.');
        Console::breakline();

        // Delete PHPBIN file
        $message = 'Deleting main PHP binary file of the build...';
        Console::line($message, false);

        $storagePath  = $this->repository->versions[$version]['storagePath'];
        $removePHPBin = @unlink($storagePath . '\php.exe');

        if (! $removePHPBin) {
            Console::line('Failed', true, max(77 - strlen($message), 1));
            Console::breakline();
            Console::terminate(null, 1);
        }

        Console::line('Successful', true, max(73 - strlen($message), 1));

        // Delete folder
        $message = 'Deleting the directory of the build...';
        Console::line($message, false);

        $removeDir = $this->repository->removeBuild($version, false);

        if (! $removeDir) {
            Console::line('Failed', true, max(77 - strlen($message), 1));
            Console::breakline();
            Console::line('You must delete directory "' . $storagePath . '" manually.');
        } else {
            Console::line('Successful', true, max(73 - strlen($message), 1));
        }

        // Ask to remove more
        Console::breakline();
        Console::hrline();

        $removeMore = Console::confirm('Do you want to remove another PHP version');

        if ($removeMore) {
            Console::breakline();
            $this->removeVersion();
        }

        // Exit
        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    public function switchVersion($version = null)
    {
        $version = $this->getVersionOrList($version, 'You can choose one of the following builds to switch to:', false);

        if (! array_key_exists($version, $this->repository->versions)) {
            Console::terminate('Sorry! Do not found any PHP version that you entered.', 1);
        }

        if ($this->isCurrentVersion($version)) {
            Console::terminate('You are running PHP ' . $this->currentVersion . ', so you don\'t need to do anymore.');
        }

        $switchConfirm = Console::confirm('Are you sure you want to switch to PHP version "' . $version . '" ?');
        Console::breakline();

        if (! $switchConfirm) {
            Console::terminate('Cancel the action.');
        }

        Console::hrline();
        Console::line('Start switching to PHP version ' . $version);
        Console::breakline();

        // Stop Apache if necessary
        $apacheRunning = $this->isApacheRunning();
        if ($apacheRunning) {
            $this->stopApache(false);
        }

        // Create symbolic link
        $message = 'Creating symbolic link to corresponding PHP build in repository...';
        Console::line($message, false);

        $resultMap = $this->repository->mapToUse($version, $this->paths['phpDir']);

        if (! $resultMap) {
            Console::line('Failed', true, max(77 - strlen($message), 1));
            Console::breakline();
            Console::terminate('The switching process has failed.', 1);
        }

        Console::line('Successful', true, max(73 - strlen($message), 1));

        // Update httpd-xampp.conf
        $message = 'Updating the "httpd-xampp.conf" file to corresponding PHP build...';
        Console::line($message, false);

        $httpdXamppPHP = str_replace('{{php_major_version}}', get_major_phpversion($version), $this->paths['httpdXamppPHP']);
        $fileUpdated   = @file_put_contents($this->paths['httpdXampp'], 'Include "' . relative_path($this->paths['apacheDir'], $httpdXamppPHP, '/') . '"' . PHP_EOL);

        if (! $fileUpdated) {
            Console::line('Failed', true, max(77 - strlen($message), 1));
            Console::breakline();
            Console::terminate('The switching process has failed.', 1);
        }

        Console::line('Successful', true, max(73 - strlen($message), 1));

        // Restart Apache if necessary
        if ($apacheRunning) {
            $this->startApache(false);
        }

        // Update current version info
        $this->currentVersion     = $version;
        $this->currentStoragePath = $this->repository->versions[$version]['storagePath'];

        // Show result of task
        Console::breakline();
        Console::line('The version switching is completed.');
        Console::line('You are running PHP ' . $version);

        Console::breakline();
        Console::hrline();
        Console::terminate('Your request is completed.');
    }

    private function requireInstall()
    {
        Console::line('Xampp PHP Switcher has not been integrated into Xampp.');
        Console::line('Run command "xphp install" in Administartor mode to integrate it.');
        Console::terminate(null, 1);
    }

    private function getVersionOrList($version = null, $message = null, $includeCurrent = true)
    {
        if (is_phpversion($version)) {
            return $version;
        }

        $message = $message ?: 'Please choose one of the following versions:';

        if (! $includeCurrent) {
            Console::line('You are running PHP ' . $this->currentVersion, false);

            if ($this->isBuiltInVersion($this->currentVersion)) {
                Console::line(' (Built-in version)', false);
            } else {
                Console::line(' (Add-on version)', false);
            }

            Console::line(', stored at ' . $this->currentStoragePath);
            Console::breakline();
        }

        $options     = $this->makeVersionList($includeCurrent);
        $totalOption = count($options);

        if ($totalOption == 0) {
            Console::terminate('There are no other PHP builds to use.');
        }

        Console::line($message);
        Console::breakline();

        $maxLenVersion = max(array_map(function($item) {
            return strlen($item);
        }, array_keys($this->repository->versions)));

        $maxLenStoragePath = max(array_map(function($item) {
            return strlen($item['storagePath']);
        }, $this->repository->versions));

        foreach ($options as $optionId => $version) {
            $storagePath = $this->repository->versions[$version]['storagePath'];

            $col_1_content = str_pad('[' . $optionId . ']', strlen($totalOption) + 2, ' ', STR_PAD_LEFT) . '  ';
            $col_2_content = 'Version ' . str_pad($version, $maxLenVersion + 2);
            $col_3_content = '-  Stored at: ' . str_pad($storagePath, $maxLenStoragePath + 2);

            Console::line($col_1_content, false);
            Console::line($col_2_content, false);
            Console::line($col_3_content, false);

            if ($this->isBuiltInVersion($version)) {
                Console::line('-  Built-in', false);
            } else {
                Console::line('-  Add-on', false);
            }

            if ($this->isCurrentVersion($version)) {
                Console::line(', in use');
            } else {
                Console::breakline();
            }
        }

        Console::breakline();

        $selection = -1;
        $repeat    = 0;

        while ($selection <= 0 || $selection > $totalOption) {
            if ($repeat == 4) {
                Console::terminate('You have entered an incorrect format many times.', 1);
            }

            if ($repeat == 0) {
                $selection = Console::ask('Please pick an option (type ordinal number, or leave it blank to exit)');
            } else {
                Console::line('You have entered an incorrect format.');
                $selection = Console::ask('Please pick an option again (type ordinal number, or leave it blank to exit)');
            }

            Console::breakline();

            if (is_null($selection)) {
                Console::line('Xampp PHP Switcher is terminating on demand...');
                Console::terminate(null, 0, true);
            }

            $selection = ((int) $selection);
            $repeat++;
        }

        return $options[$selection];
    }

    private function makeVersionList($includeCurrent = true)
    {
        $options  = [];
        $startNum = 1;

        foreach ($this->repository->versions as $version => $info) {
            if ($includeCurrent) {
                $options[$startNum++] = $version;
            } else {
                if (! $this->isCurrentVersion($version)) {
                    $options[$startNum++] = $version;
                }
            }
        }

        return $options;
    }

    private function isCurrentVersion($version)
    {
        return $this->currentVersion === $version;
    }

    private function isBuiltInVersion($version)
    {
        return (! $this->repository->versions[$version]['isAddOnBuild']);
    }

    private function prepareStandardizeList()
    {
        $list     = [];
        $listFile = $this->paths['srcDir'] . '\need_standardize.lst';

        if (is_file($listFile)) {
            $autodetect = ini_get('auto_detect_line_endings');

            ini_set('auto_detect_line_endings', '1');

            $list = file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            ini_set('auto_detect_line_endings', $autodetect);
        }

        return $list;
    }
}
