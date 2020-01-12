<?php

if (! function_exists('copy_dir')) {
    function copy_dir($source, $dest) {
        // Make the destination directory if not exist
        if (! is_dir($dest)) {
            if (! mkdir($dest)) {
                return false;
            }
        }

        // open the source directory
        if (! $handle = opendir($source)) {
            return false;
        }

        // Loop through the files in source directory
        while($item = readdir($handle)) {
            if (($item != '.') && ($item != '..')) {
                $realpath = realpath($source . DIRECTORY_SEPARATOR . $item);

                if (is_dir($realpath)) {
                    // Recursively calling custom copy function for sub directory
                    if (! copy_dir($realpath, $dest . DIRECTORY_SEPARATOR . $item)) {
                        return false;
                    }
                } else {
                    $resultCopy = copy($realpath, $dest . DIRECTORY_SEPARATOR . $item);

                    if (! $resultCopy) {
                        return false;
                    }

                    unset($resultCopy);
                }
            }
        }

        closedir($handle);

        return true;
    }
}

if (! function_exists('undir')) {
    function undir($dirPath) {
        if (! $handle = opendir($dirPath)) {
            return false;
        }

        while($item = readdir($handle)) {
            if (($item != '.') && ($item != '..')) {
                $realpath = realpath($dirPath . DIRECTORY_SEPARATOR . $item);

                if (is_dir($realpath)) {
                    // Recursively calling custom copy function for sub directory
                    if (! undir($realpath)) {
                        return false;
                    }
                } else {
                    $removed = unlink($realpath);

                    if (! $removed) {
                        return false;
                    }

                    unset($removed);
                }
            }
        }

        closedir($handle);

        if (! rmdir($dirPath)) {
            return false;
        }

        return true;
    }
}

if (! function_exists('maybe_phpdir')) {
    function maybe_phpdir($dirPath) {
        return (is_dir($dirPath)) && (is_file($dirPath . DIRECTORY_SEPARATOR . 'php.exe'));
    }
}

if (! function_exists('get_version_phpdir')) {
    function get_version_phpdir($dirPath) {
        if (! maybe_phpdir($dirPath)) {
            return null;
        }

        return exec('"' . $dirPath . DIRECTORY_SEPARATOR . 'php.exe" -n -r "echo PHP_VERSION;"');
    }
}

if (! function_exists('get_platform_phpdir')) {
    function get_platform_phpdir($dirPath) {
        if (! maybe_phpdir($dirPath)) {
            return null;
        }

        $platform = 'x86';

        exec('"' . $dirPath . DIRECTORY_SEPARATOR . 'php.exe" -n -v', $outputArr);

        foreach ($outputArr as $line) {
            $matches = null;

            preg_match("/x86|x64/", $line, $matches);

            if ($matches[0]) {
                $platform = $matches[0];
                break;
            }
        }

        return $platform;
    }
}

if (! function_exists('is_phpversion')) {
    function is_phpversion($string) {
        return preg_match('/^\d+\.\d+\.\d+(-extra)?$/', $string);
    }
}

if (! function_exists('get_major_phpversion')) {
    function get_major_phpversion($version) {
        if (! is_phpversion($version)) {
            return null;
        }

        return explode('.', $version)[0];
    }
}

if (! function_exists('get_minor_phpversion')) {
    function get_minor_phpversion($version) {
        if (! is_phpversion($version)) {
            return null;
        }

        return explode('.', $version)[1];
    }
}

if (! function_exists('get_release_phpversion')) {
    function get_release_phpversion($version) {
        if (! is_phpversion($version)) {
            return null;
        }

        return explode('.', $version)[2];
    }
}

if (! function_exists('get_extra_phpversion')) {
    function get_extra_phpversion($version) {
        if (! is_phpversion($version)) {
            return null;
        }

        $splitParts = explode('-', $version);

        if (count($splitParts) <= 1) {
            return null;
        }

        return $splitParts[1];
    }
}

if (! function_exists('maybe_path')) {
    function maybe_path($string) {
        return (bool) preg_match('/^[^\"\<\>\?\*\|]+$/', $string);
    }
}

if (! function_exists('winstyle_path')) {
    function winstyle_path($path) {
        return str_replace('/', '\\', $path);
    }
}

if (! function_exists('unixstyle_path')) {
    function unixstyle_path($path) {
        return str_replace('\\', '/', $path);
    }
}

if (! function_exists('absolute_path')) {
    /**
     * Return absolute path from input path.
     * This function is an alternative to realpath() function for non-existent paths.
     *
     * @param  string $path         The input path
     * @param  string $separator    The directory separator wants to use in the results
     *
     * @return string
     */
    function absolute_path($path, $separator = DIRECTORY_SEPARATOR) {
        // Normalize directory separators
        $path = str_replace(['/', '\\'], $separator, $path);

        // Store root part of path
        $root = null;
        while (is_null($root)) {
            // Check if path start with a separator (UNIX)
            if (substr($path, 0, 1) === $separator) {
                $root = $separator;
                $path = substr($path, 1);
                break;
            }

            // Check if path start with drive letter (WINDOWS)
            preg_match('/^[a-z]:/i', $path, $matches);
            if (isset($matches[0])) {
                $root = $matches[0] . $separator;
                $path = substr($path, 2);
                break;
            }

            $path = getcwd() . $separator . $path;
        }

        // Get and filter empty sub paths
        $subPaths = array_filter(explode($separator, $path), 'strlen');

        $absolutes = [];
        foreach ($subPaths as $subPath) {
            if ('.' === $subPath) {
                continue;
            }

            if ('..' === $subPath) {
                array_pop($absolutes);
                continue;
            }

            $absolutes[] = $subPath;
        }

        return $root . implode($separator, $absolutes);
    }
}

if (! function_exists('relative_path')) {
    /**
     * Return relative path from source directory to destination
     *
     * @param  string $from         The path of source directory
     * @param  string $to           The path of file or directory to be compare
     * @param  string $separator    The directory separator wants to use in the results
     *
     * @return string
     */
    function relative_path($from, $to, $separator = DIRECTORY_SEPARATOR) {
        $fromParts  = explode($separator, absolute_path($from, $separator));
        $toParts    = explode($separator, absolute_path($to, $separator));
        $diffFromTo = array_diff($fromParts, $toParts);
        $diffToFrom = array_diff($toParts, $fromParts);

        if ($diffToFrom === $toParts) {
            return implode($separator, $toParts);
        }

        return str_repeat('..' . $separator, count($diffFromTo)) . implode($separator, $diffToFrom);
    }
}
