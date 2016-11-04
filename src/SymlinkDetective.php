<?php


use Webmozart\PathUtil\Path;

class SymlinkDetective
{

    public static function detectPath($targetPath)
    {
        $targetPath = Path::canonicalize($targetPath);

        // Determine initial script
        if (!$initialScript = self::getInitialScript()) {
            throw new RuntimeException('Initial script cannot be determined!');
        }

        // Determine common roots now

        if (!$commonPath = self::detectCommonRoots($initialScript, $targetPath)) {
            throw new RuntimeException("File \"$targetPath\" is not found in path \"$initialScript\"");
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
            throw new RuntimeException("File \"$targetPath\" is not found in path \"$initialScript\"");
        }

        return $targetPath;
    }

    protected static function chooseBestSearchResult(array $found)
    {
        // no determination yet, first is the best
        return $found[ 0 ][ 'path' ];
    }

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

    public function detectCommonRoots($path1, $path2)
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
