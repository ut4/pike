<?php

namespace Pike;

class FileSystem implements FileSystemInterface {
    /**
     * @param string $path
     * @param string $content
     * @return int|false
     */
    public function write($path, $content) {
        return file_put_contents($path, $content, LOCK_EX);
    }
    /**
     * @param string $path
     * @param boolean $che = true
     * @return string
     */
    public function read($path) {
        return @file_get_contents($path);
    }
    /**
     * @param string $path
     * @return string|false
     */
    public function unlink($path) {
        return @unlink($path);
    }
    /**
     * @param string $path
     * @param string $destPath
     * @return bool
     */
    public function copy($path, $destPath) {
        return @copy($path, $destPath);
    }
    /**
     * @param string $path
     * @param int $perms = 0755
     * @param bool $recursive = true
     * @return bool
     */
    public function mkDir($path, $perms = 0755, $recursive = true) {
        return @mkdir($path, $perms, $recursive);
    }
    /**
     * @param string $path
     * @param resource $context = null
     * @return bool
     */
    public function rmDir($path, $context = null) {
        return @rmdir($path);
    }
    /**
     * @param string $path
     * @return bool
     */
    public function isFile($path) {
        return is_file($path);
    }
    /**
     * @param string $path
     * @return bool
     */
    public function isDir($path) {
        return is_dir($path);
    }
    /**
     * @param string $path
     * @param string $filter = '*'
     * @param int $flags = GLOB_ERR
     * @return string[]|false
     */
    public function readDir($path, $filter = '*', $flags = GLOB_ERR) {
        return glob(rtrim($path, '/') . '/' . $filter, $flags);
    }
    /**
     * @param string $path
     * @return int|false
     */
    public function lastModTime($path) {
        return @filemtime($path);
    }
    /**
     * 'foo/bar\baz' -> 'foo/bar/baz/'
     *
     * @param string $path
     * @return string
     */
    public static function normalizePath($path) {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
