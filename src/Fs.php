<?php
declare(strict_types=1);

namespace Vertilia\Router;

/**
 * Filesystem-related methods
 */
class Fs
{
    /**
     * Normalizes path by removing empty and dot (.) dirs, resolving parent
     * (..) dirs and removing starting slash.
     *
     * @param string $path path to normalize
     * @return string
     *
     * @assert('') = ''
     * @assert('/') = ''
     * @assert('/etc/hosts') = 'etc/hosts'
     * @assert('.././/tmp/../home//admin/./.ssh') = 'home/admin/.ssh'
     */
    public static function normalizePath(string $path = null): string
    {
        $dirs = [];
        foreach (\explode('/', $path === null ? '' : $path) as $d) {
            if (\strlen($d) and $d != '.') {
                if ($d == '..') {
                    \array_pop($dirs);
                } else {
                    $dirs[] = $d;
                }
            }
        }

        return \implode('/', $dirs);
    }
}
