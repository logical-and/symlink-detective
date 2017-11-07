<?php

namespace SymlinkDetective;

class Helper
{
    public static function shiftPathLeft($path)
    {
        // Cannot be shifted
        if (false === strpos($path, '/') and false === strpos($path, '\\')) {
            return false;
        }

        $result = dirname($path);

        return $result != $path ? $result : false;
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
}
