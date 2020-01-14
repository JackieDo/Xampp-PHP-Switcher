<?php

require_once __DIR__ . '/helpers.php';

if (! defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

class VersionRepository
{
    const STORAGE_NAME_PATTERN = '{{version}}';

    protected $debug        = true;
    protected $architecture = null;
    protected $location     = null;
    protected $versions     = [];

    public function __construct($architecture, $location = null, $debugStatus = true)
    {
        $this->setDebug($debugStatus);
        $this->setArchitecture($architecture);
        $this->setLocation($location);
    }

    public function __get($propertyName)
    {
        if (property_exists(__CLASS__, $propertyName)) {
            $reflection = new ReflectionProperty(__CLASS__, $propertyName);

            if ($reflection->isPrivate()) {
                return null;
            }

            return $this->$propertyName;
        }

        return null;
    }

    public function setArchitecture($architecture)
    {
        $this->architecture = $architecture;

        return $this;
    }

    public function setLocation($dirPath = null)
    {
        if (! empty($dirPath)) {
            $dirPath = strval($dirPath);

            if (! is_dir($dirPath)) {
                @mkdir($dirPath, 0755, true);
            }

            $this->location = realpath($dirPath);

            return $this->loadVersions();
        }

        return $this;
    }

    public function setDebug($status)
    {
        if (is_bool($status)) {
            $this->debug = $status;
        }

        return $this;
    }

    public function buildStoragePath($version)
    {
        if (is_null($this->location)) {
            if ($this->debug) {
                throw new Exception('The "location" property has not been set.');
            }

            return null;
        }

        $storageName = str_replace('{{version}}', $version, self::STORAGE_NAME_PATTERN);
        $storagePath = $this->location . DS . $storageName;

        return $storagePath;
    }

    public function mapToUse($version, $usePath)
    {
        $targetSymlink = $this->versions[$version]['storagePath'];

        if ($usePath == readlink($usePath)) {
            if ($this->debug) {
                throw new Exception('The parameter "usePath" is currently a real directory.');
            }

            return false;
        }

        $output = $status = null;
        exec('rd "' . $usePath . '"', $output, $status);

        if ($status == 1) {
            if ($this->debug) {
                throw new Exception('Cannot remove old symbolic link.');
            }

            return false;
        }

        $output = $status = null;
        exec('mklink /J "' . $usePath . '" "' . $targetSymlink . '"', $output, $status);

        if ($status == 1) {
            if ($this->debug) {
                throw new Exception('The creating symbolic link has failed.');
            }

            return false;
        }

        return true;
    }

    public function import($source, $predefined = [], $validityCheck = true)
    {
        if ($validityCheck) {
            if (! maybe_phpdir($source)) {
                if ($this->debug) {
                    throw new Exception('The directory "' . $source . '" is not a PHP directory.');
                }

                return [
                    'error' => 1,
                    'data'  => []
                ];
            }
        }

        $architecture = (array_key_exists('architecture', $predefined)) ? $predefined['architecture'] : get_architecture_phpdir($source);
        if ($validityCheck) {
            if (!$this->architecture != $architecture) {
                if ($this->debug) {
                    throw new Exception('The build at "' . $source . '" is not compatible with this storage architecture');
                }

                return [
                    'error' => 2,
                    'data'  => []
                ];
            }
        }

        $version = (array_key_exists('version', $predefined)) ? $predefined['version'] : get_version_phpdir($source);
        if ($validityCheck) {
            if (array_key_exists($version, $this->versions)) {
                if ($this->debug) {
                    throw new Exception('Version of the build at "' . $source . '" coincides with the existing version.');
                }

                return [
                    'error' => 3,
                    'data'  => []
                ];
            }
        }

        $compiler    = (array_key_exists('compiler', $predefined)) ? $predefined['compiler'] : get_compiler_phpdir($source);
        $buildDate   = (array_key_exists('buildDate', $predefined)) ? $predefined['buildDate'] : get_builddate_phpdir($source);
        $zendVersion = (array_key_exists('zendVersion', $predefined)) ? $predefined['zendVersion'] : get_zendversion_phpdir($source);
        $storagePath = $this->buildStoragePath($version);

        if (! $storagePath) {
            return [
                'error' => 4,
                'data'  => []
            ];
        }

        exec('xcopy /E /I /H /Y /Q "' . $source . '" "' . $storagePath . '"', $output, $status);

        if ($status != 0) {
            if ($this->debug) {
                throw new Exception('The copy process has failed.');
            }

            return [
                'error' => 5,
                'data'  => []
            ];
        }

        $repoSettings = [
            'BuildInfo'     => [],
            'RepoImporting' => []
        ];

        if (is_file($storagePath . '\.repo')) {
            $repoSettings = @parse_ini_file($storagePath . '\.repo', true);
        }

        $repoSettings['BuildInfo']['Version']        = $version;
        $repoSettings['BuildInfo']['Architecture']   = $architecture;
        $repoSettings['BuildInfo']['Compiler']       = $compiler;
        $repoSettings['BuildInfo']['BuildDate']      = $buildDate;
        $repoSettings['BuildInfo']['ZendVersion']    = $zendVersion;
        $repoSettings['RepoImporting']['AddOnBuild'] = '1';

        @create_ini_file($storagePath . '\.repo', $repoSettings, true);

        $this->versions[$version] = [
            'storagePath'  => $storagePath,
            'architecture' => $architecture,
            'compiler'     => $compiler,
            'buildDate'    => $buildDate,
            'zendVersion'  => $zendVersion,
            'isAddOnBuild' => true
        ];

        return [
            'error' => 0,
            'data'  => [
                'storagePath'  => $storagePath,
                'repoSettings' => $repoSettings
            ]
        ];
    }

    public function remove($version, $validityCheck = true)
    {
        if ($validityCheck) {
            if (! $this->has($version)) {
                if ($this->debug) {
                    throw new Exception('The version "' . $version . '" does not exist.');
                }

                return false;
            }

            if (! $this->isAddOnBuild($version)) {
                if ($this->debug) {
                    throw new Exception('The version "' . $version . '" is not an imported build.');
                }

                return false;
            }
        }

        $storagePath = $this->versions[$version]['storagePath'];
        exec('rd /Q /S "' . $storagePath . '"', $output, $status);

        if ($status != 0) {
            if ($this->debug) {
                throw new Exception('The process of deleting the directory "' . $storagePath . '" has an error');
            }

            return false;
        }

        unset($this->versions[$version]);

        return true;
    }

    public function edit($version, $actions = [], $validityCheck = true)
    {
        if ($validityCheck) {
            if (! $this->has($version)) {
                if ($this->debug) {
                    throw new Exception('The version "' . $version . '" does not exist.');
                }

                return false;
            }

            if (! $this->isAddOnBuild($version)) {
                if ($this->debug) {
                    throw new Exception('The version "' . $version . '" is not an imported build. No editing allowed.');
                }

                return false;
            }
        }

        $storagePath = $this->versions[$version]['storagePath'];

        foreach ($actions as $action) {
            switch ($action['type']) {
                case 'new':
                    foreach ($action['files'] as $file) {
                        $filePath = $storagePath . DS . $file;

                        if (! is_file($filePath)) {
                            @file_put_contents($filePath, $action['content']);
                        }
                    }

                    break;

                case 'remove':
                    foreach ($action['files'] as $file) {
                        $filePath = $storagePath . DS . $file;

                        if (is_file($filePath)) {
                            @unlink($filePath);
                        }
                    }

                    break;

                case 'replace':
                    foreach ($action['files'] as $file) {
                        $filePath = $storagePath . DS . $file;

                        if (is_file($filePath)) {
                            if (! array_key_exists('pattern', $action) || is_null($action['pattern']) || trim($action['pattern']) === '') {
                                @file_put_contents($filePath, $action['replacement']);
                            } else {
                                $fileContent = @preg_replace($action['pattern'], $action['replacement'], @file_get_contents($filePath));
                                @file_put_contents($filePath, $fileContent);
                            }
                        }
                    }

                    break;

                default:
                    break;
            }
        }

        return true;
    }

    public function has($version)
    {
        return array_key_exists($version, $this->versions);
    }

    public function isAddOnBuild($version)
    {
        return $this->versions[$version]['isAddOnBuild'];
    }

    private function loadVersions()
    {
        $location = $this->location;

        // Open repository location
        if (! $handle = @opendir($location)) {
            if ($this->debug) {
                throw new Exception('Could not open the directory of repository.');
            }

            return $this;
        }

        $avaiableVersions = [];

        // Read all matching directories that contain PHP build
        while ($item = readdir($handle)) {
            if (($item == '.') || ($item == '..') || ($item == 'php') || (!maybe_phpdir($location . DS . $item))) {
                continue;
            }

            $storagePath    = $location . DS . $item;
            $needUpdateInfo = true;
            $repoSettings       = [
                'BuildInfo' => []
            ];

            if (is_file($storagePath . '\.repo')) {
                $repoSettings   = @parse_ini_file($storagePath . '\.repo', true);
                $needUpdate = false;
            }

            $hasBuildInfo = array_key_exists('BuildInfo', $repoSettings);

            if (!$hasBuildInfo || !array_key_exists('Version', $repoSettings['BuildInfo'])) {
                $repoSettings['BuildInfo']['Version'] = get_version_phpdir($storagePath);
                $needUpdateInfo = true;
            }

            if (!$hasBuildInfo || !array_key_exists('Architecture', $repoSettings['BuildInfo'])) {
                $repoSettings['BuildInfo']['Architecture'] = get_architecture_phpdir($storagePath);
                $needUpdateInfo = true;
            }

            if ($repoSettings['BuildInfo']['Architecture'] != $this->architecture) {
                continue;
            }

            if (!$hasBuildInfo || !array_key_exists('Compiler', $repoSettings['BuildInfo'])) {
                $repoSettings['BuildInfo']['Compiler'] = get_compiler_phpdir($storagePath);
                $needUpdateInfo = true;
            }

            if (!$hasBuildInfo || !array_key_exists('BuildDate', $repoSettings['BuildInfo'])) {
                $repoSettings['BuildInfo']['BuildDate'] = get_builddate_phpdir($storagePath);
                $needUpdateInfo = true;
            }

            if (!$hasBuildInfo || !array_key_exists('ZendVersion', $repoSettings['BuildInfo'])) {
                $repoSettings['BuildInfo']['ZendVersion'] = get_zendversion_phpdir($storagePath);
                $needUpdateInfo = true;
            }

            if ($needUpdateInfo) {
                @create_ini_file($storagePath . '\.repo', $repoSettings, true);
            }

            $avaiableVersions[$repoSettings['BuildInfo']['Version']] = [
                'storagePath'  => $storagePath,
                'architecture' => $repoSettings['BuildInfo']['Architecture'],
                'compiler'     => $repoSettings['BuildInfo']['Compiler'],
                'buildDate'    => $repoSettings['BuildInfo']['BuildDate'],
                'zendVersion'  => $repoSettings['BuildInfo']['ZendVersion'],
                'isAddOnBuild' => (bool) $repoSettings['RepoImporting']['AddOnBuild']
            ];
        }

        uksort($avaiableVersions, 'strnatcmp');

        $this->versions = $avaiableVersions;

        closedir($handle);

        return $this;
    }
}
