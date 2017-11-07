<?php

use SymlinkDetective\Helper;
use Webmozart\PathUtil\Path;

// Bootstrap classes
if (!class_exists(Helper::class)) {
    require_once __DIR__ . '/SymlinkDetective/Helper.php';
}

class SymlinkDetective
{

    /**
     * @param string $targetPath Path to determine real path (works not like realpath()), it's always better to pass file to
     * this argument with unique name (to prevent of finding another file with the same name)
     * @param string $append Add if you want get directory, even if you pas to the first arg you can do it like:
     * (__FILE__, '/../../') and it will return directory path as result
     * @param bool $skipNoFound If no files found just return the requested file (caution, other exceptions will be thrown)
     * @return string
     */
    public static function detectPath($targetPath, $append = '', $skipNoFound = true)
    {
        $targetPath = self::canonicalizePath($targetPath);

        // Determine initial script
        if (!$initialScript = self::getInitialScript()) {
            throw new RuntimeException('Initial script cannot be determined!');
        }

        // Determine common roots now

        if (!$commonPath = Helper::detectCommonRoots($initialScript, $targetPath)) {
            throw new RuntimeException("No common roots of \"$targetPath\" with path \"$initialScript\"");
        }

        // Determine the check method
        $method = 1;
        if (is_file($targetPath) or is_dir($targetPath)) {
            $dummyFile = '.symlink-detective.' . uniqid() . '~';
            if (is_file($targetPath)) {
                $checkTargetPath = self::canonicalizePath(dirname($targetPath) . DIRECTORY_SEPARATOR . $dummyFile);
                $realRelativeTargetPath = pathinfo($targetPath, PATHINFO_BASENAME);
            }
            else {
                $checkTargetPath = self::canonicalizePath($targetPath . DIRECTORY_SEPARATOR . $dummyFile);
                $realRelativeTargetPath = '';
            }
            touch($checkTargetPath);
            if (is_file($checkTargetPath)) {
                $method = 2;
            }
        }

        if (2 == $method) {
            /** @noinspection PhpUndefinedVariableInspection */
            $relativePath = ltrim(str_replace($commonPath, '', $checkTargetPath), '\\/');
        }
        else {
            $relativePath = ltrim(str_replace($commonPath, '', $targetPath), '\\/');
        }

        // Start investigation
        // clean target from left to right, and add to initial script path which cleans from right to left, example:
        // initial script /var/www/site/public/index.php ~> /var/www/site/public ~> /var/www/site
        // relative target path library/asset/scripts ~> asset/scripts ~> scripts

        $found              = [];
        $foundPaths         = [];
        $relativePathSearch = $relativePath;

        while ($relativePathSearch) {
            $initialScriptSearch = $initialScript;

            while ($initialScriptSearch) {
                // first action, since script is a file
                $initialScriptSearch = Helper::shiftPathLeft($initialScriptSearch);

                $tryPath = self::canonicalizePath($initialScriptSearch . DIRECTORY_SEPARATOR . $relativePathSearch);
                if (2 == $method) {
                    /** @noinspection PhpUndefinedVariableInspection */
                    $foundPath = self::canonicalizePath(dirname($tryPath) . DIRECTORY_SEPARATOR . $realRelativeTargetPath);
                }
                else {
                    $foundPath = $tryPath;
                }
                if (!in_array($foundPath, $foundPaths) and (is_dir($tryPath) or is_file($tryPath))) {
                    if (2 == $method) {
                        $found[] = [
                            'path'              => $foundPath,
                            'checkPath'         => $tryPath,
                            'relativePath'      => $relativePathSearch,
                            'initialScriptPath' => $initialScriptSearch
                        ];
                    }
                    else {
                        $found[] = [
                            'path'         => $tryPath,
                            'relativePath'      => $relativePathSearch,
                            'initialScriptPath' => $initialScriptSearch
                        ];
                    }

                    // Do not add twice
                    $foundPaths[] = $foundPath;
                }

                if ($initialScriptSearch == $commonPath) {
                    break;
                }
            }

            $relativePathSearch = Helper::shiftPathRight($relativePathSearch);
        }

        if (2 == $method) {
            /** @noinspection PhpUndefinedVariableInspection */
            unlink($checkTargetPath);
        }

        if (!$found) {
            if (!$skipNoFound) {
                throw new RuntimeException("File \"$targetPath\" is not found in path \"$initialScript\"");
            }
            else {
                return !$append ? $targetPath : self::canonicalizePath($targetPath . $append);
            }
        }

        // echo "initial script - $initialScript" . PHP_EOL;
        // echo "determine script - $targetPath" . PHP_EOL;
        // echo "common path $commonPath" . PHP_EOL;
        // echo "relative path $relativePath" . PHP_EOL;
        // echo "found paths " . var_export($found, true) . PHP_EOL;

        return !$append ?
            self::chooseBestSearchResult($targetPath, $found) :
            self::canonicalizePath(self::chooseBestSearchResult($targetPath, $found) . $append);
    }

    /**
     * Remove relative dots in path, ie `/here/is/some/path/../` would be `/here/si/some`.
     * What's important it doesn't use realpath() function
     * @param $path
     * @return string
     */
    public static function canonicalizePath($path)
    {
        return Path::canonicalize($path);
    }

    /**
     * As there can be many found results, determine what's better
     *
     * @param $targetPath
     * @param array $foundPaths
     * @return mixed
     */
    protected static function chooseBestSearchResult($targetPath, array $foundPaths)
    {
        $found = null;
        $lastRoots = null;
        foreach ($foundPaths as $i => $result) {
            // Init the check
            if (!$lastRoots) {
                $lastRoots = Helper::detectCommonRoots($targetPath, $result['path']);
                // Only if found
                if ($lastRoots) {
                    $found = $i;
                }
            }
            // Compare roots
            else {
                $commonRoots = Helper::detectCommonRoots($targetPath, $result['path']);
                // The less similar the better
                if ($commonRoots and strlen($commonRoots) < strlen($lastRoots)) {
                    $lastRoots = $commonRoots;
                    $found = $i;
                }
            }
        }

        if (!is_null($foundPaths[ $found ]) and !empty($foundPaths[ $found ])) {
            return $foundPaths[ $found ][ 'path' ];
        }

        // the last resort
        return $foundPaths[ 0 ][ 'path' ];
    }

    /**
     * Get initial script, the first executed script
     *
     * @return bool|string false if not found for some reason
     */
    public static function getInitialScript()
    {
        $found = false;

        // Method 1: SCRIPT_FILENAME
        if (!empty($_SERVER[ 'SCRIPT_FILENAME' ])) {
            $filename = $_SERVER[ 'SCRIPT_FILENAME' ];

            // Is path absolute? If so, we're done
            if (
                // Unix
                (0 === strpos($filename, DIRECTORY_SEPARATOR) or
                    // Windows
                    ('\\' == DIRECTORY_SEPARATOR and 1 === strpos($filename, ':'))
                ) and is_file($filename)
            ) {
                $found = $filename;
            } else {
                foreach (array_filter([
                    getcwd(),
                    !empty($_SERVER[ 'PWD' ]) ? $_SERVER[ 'PWD' ] : false
                ]) as $pwd) {
                    $path = rtrim($pwd, '\\/') . DIRECTORY_SEPARATOR . $filename;

                    if (is_file($path)) {
                        $found = $path;
                    }
                }
            }
        }

        // Method 2: stacktrace
        if (!$found) {
            foreach (array_reverse(debug_backtrace()) as $trace) {
                if (!empty($trace[ 'file' ])) {
                    $found = $trace[ 'file' ];
                    break;
                }
            }
        }

        return $found;
    }
}