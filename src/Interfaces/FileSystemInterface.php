<?php

declare(strict_types=1);

namespace Pike\Interfaces;

interface FileSystemInterface {
    /**
     * @param string $path
     * @param string $content
     * @return int|false
     */
    public function write(string $path, string $content);
    /**
     * @param string $path
     * @return string|false
     */
    public function read(string $path);
    /**
     * @param string $path
     * @return bool
     */
    public function unlink(string $path): bool;
    /**
     * @param string $path
     * @param string $destPath
     * @return bool
     */
    public function copy(string $path, string $destPath): bool;
    /**
     * @param string $oldPath
     * @param string $newPath
     * @return string|false
     */
    public function rename(string $oldPath, string $newPath);
    /**
     * @see $this:rename
     */
    public function move(string $oldPath, string $newPath): bool;
    /**
     * @param string $path
     * @param int $perms = 0755
     * @param bool $recursive = true
     * @return bool
     */
    public function mkDir(string $path,
                          int $perms = 0755,
                          bool $recursive = true): bool;
    /**
     * @param string $path
     * @param resource $context = null
     * @return bool
     */
    public function rmDir(string $path, $context = null): bool;
    /**
     * @param string $path
     * @return bool
     */
    public function isFile(string $path): bool;
    /**
     * @param string $path
     * @return bool
     */
    public function isDir(string $path): bool;
    /**
     * @param string $path
     * @param string $filterPattern = '*'
     * @param int $flags = GLOB_ERR
     * @return string[]|false
     */
    public function readDir(string $path,
                            string $filterPattern = '*',
                            int $flags = GLOB_ERR);
    /**
     * @param string $path
     * @param string $filterRegexp = '/.<zeroOrMore>/'
     * @return mixed[]
     * @throws \UnexpectedValueException|\InvalidArgumentException
     */
    public function readDirRecursive(string $path,
                                     string $filterRegexp = '/.*/'): array;
    /**
     * @param string $path
     * @return int|false
     */
    public function lastModTime(string $path);
}
