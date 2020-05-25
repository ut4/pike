<?php

declare(strict_types=1);

namespace Pike;

class FileSystem implements FileSystemInterface {
    /**
     * @param string $path
     * @param string $content
     * @return int|false
     */
    public function write(string $path, string $content) {
        return file_put_contents($path, $content, LOCK_EX);
    }
    /**
     * @param string $path
     * @param boolean $che = true
     * @return string|false
     */
    public function read(string $path) {
        return file_get_contents($path);
    }
    /**
     * @param string $path
     * @return bool
     */
    public function unlink(string $path): bool {
        return unlink($path);
    }
    /**
     * @param string $path
     * @param string $destPath
     * @return bool
     */
    public function copy(string $path, string $destPath): bool {
        return copy($path, $destPath);
    }
    /**
     * @param string $path
     * @param int $perms = 0755
     * @param bool $recursive = true
     * @return bool
     */
    public function mkDir(string $path,
                          int $perms = 0755,
                          bool $recursive = true): bool {
        return mkdir($path, $perms, $recursive);
    }
    /**
     * @param string $path
     * @param resource $context = null
     * @return bool
     */
    public function rmDir(string $path, $context = null): bool {
        return rmdir($path, $context);
    }
    /**
     * @param string $path
     * @return bool
     */
    public function isFile(string $path): bool {
        return is_file($path);
    }
    /**
     * @param string $path
     * @return bool
     */
    public function isDir(string $path): bool {
        return is_dir($path);
    }
    /**
     * @param string $path
     * @param string $filterPattern = '*'
     * @param int $flags = GLOB_ERR
     * @return string[]|false
     */
    public function readDir(string $path,
                            string $filterPattern = '*',
                            $flags = GLOB_ERR) {
        return glob(rtrim($path, '/') . '/' . $filterPattern, $flags);
    }
    /**
     * @param string $path
     * @param string $filterRegexp = '/.<zeroOrMore>/'
     * @return mixed[]
     * @throws \UnexpectedValueException|\InvalidArgumentException
     */
    public function readDirRecursive(string $path,
                                     string $filterRegexp = '/.*/',
                                     int $flags = \FilesystemIterator::CURRENT_AS_PATHNAME): array {
        // @allow \UnexpectedValueException
        $dir = new \RecursiveDirectoryIterator($path, $flags);
        // @allow \InvalidArgumentException
        $files = new \RegexIterator(new \RecursiveIteratorIterator($dir),
                                    $filterRegexp);
        return iterator_to_array($files, false);
    }
    /**
     * @param string $path
     * @return int|false
     */
    public function lastModTime(string $path) {
        return filemtime($path);
    }
    /**
     * 'foo/bar\baz/' -> 'foo/bar/baz'
     *
     * @param string $path
     * @return string
     */
    public static function normalizePath(string $path): string {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
