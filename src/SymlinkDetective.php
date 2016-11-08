<?php


use Webmozart\PathUtil\Path;

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
        $targetPath = Path::canonicalize($targetPath);

        // Determine initial script
        if (!$initialScript = self::getInitialScript()) {
            throw new RuntimeException('Initial script cannot be determined!');
        }

        // Determine common roots now

        if (!$commonPath = self::detectCommonRoots($initialScript, $targetPath)) {
            throw new RuntimeException("No common roots of \"$targetPath\" with path \"$initialScript\"");
        }

        $relativePath = ltrim(str_replace($commonPath, '', $targetPath), '\\/');

        // Start investigation
        // clean target from left to right, and add to initial script path which cleans from right to left, example:
        // initial script /var/www/site/public/index.php ~> /var/www/site/public ~> /var/www/site
        // relative target path library/asset/scripts ~> library/asset ~> library/asset

        $found              = [];
        $relativePathSearch = $relativePath;

        while ($relativePathSearch) {
            $initialScriptSearch = $initialScript;

            while ($initialScriptSearch) {
                // first action, since script is a file
                $initialScriptSearch = self::shiftPathLeft($initialScriptSearch);

                if ($initialScriptSearch == $commonPath) {
                    break;
                }

                $tryPath = $initialScriptSearch . DIRECTORY_SEPARATOR . $relativePathSearch;
                if (is_dir($tryPath) or is_file($tryPath)) {
                    $found[] = [
                        'path'              => $tryPath,
                        'relativePath'      => $relativePathSearch,
                        'initialScriptPath' => $initialScriptSearch
                    ];
                }
            }

            $relativePathSearch = self::shiftPathRight($relativePathSearch);
        }

        if (!$found) {
            if (!$skipNoFound) {
                throw new RuntimeException("File \"$targetPath\" is not found in path \"$initialScript\"");
            }
            else {
                return !$append ? $targetPath : Path::canonicalize($targetPath . $append);
            }
        }

        // echo "initial script - $initialScript" . PHP_EOL;
        // echo "determine script - $targetPath" . PHP_EOL;
        // echo "common path $commonPath" . PHP_EOL;
        // echo "relative path $relativePath" . PHP_EOL;
        // echo "found paths " . var_export($found, true) . PHP_EOL;

        return !$append ?
            self::chooseBestSearchResult($found) :
            Path::canonicalize(self::chooseBestSearchResult($found) . $append);
    }

    /**
     * As there can be many found results, determine what's better
     * @param array $found
     * @return mixed
     */
    protected static function chooseBestSearchResult(array $found)
    {
        // no determination yet, first is the best
        return $found[ 0 ][ 'path' ];
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
            if (0 === strpos($filename, DIRECTORY_SEPARATOR) and is_file($filename)
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

    /**
     * Helper, for /var/www/site1 and /var/www/site2 common root is /var/www
     *
     * @param $path1
     * @param $path2
     * @return bool|string
     */
    public static function detectCommonRoots($path1, $path2)
    {
        $commonPath = $path2;
        while ($commonPath) {
            // go to previous slash
            $commonPath = self::shiftPathLeft($commonPath);

            if ($commonPath and 0 === strpos($path1, $commonPath)) {
                return $commonPath;
            }
        }

        return false;
    }

    public static function shiftPathLeft($path)
    {
        // Cannot be shifted
        if (false === strpos($path, '/') and false === strpos($path, '\\')) {
            return false;
        }

        return dirname($path);
    }

    public static function shiftPathRight($path)
    {
        $pos = strpos($path, '/');
        if (false === $pos) {
            $pos = strpos($path, '\\');

            if (false === $pos) {
                // Cannot be shifted
                return false;
            }
        }

        return ltrim(substr($path, $pos), '\\/');
    }
}