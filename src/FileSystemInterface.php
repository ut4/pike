<?php

namespace Pike;

interface FileSystemInterface {
    /**
     * @param string $path
     * @param string $content
     * @return int|false
     */
    public function write($path, $content);
    /**
     * @param string $path
     * @return string|false
     */
    public function read($path);
    /**
     * @param string $path
     * @return bool
     */
    public function unlink($path);
    /**
     * @param string $path
     * @param string $destPath
     * @return bool
     */
    public function copy($path, $destPath);
    /**
     * @param string $path
     * @return int|false
     */
    public function mkDir($path);
    /**
     * @param string $path
     * @return bool
     */
    public function rmDir($path);
    /**
     * @param string $path
     * @return bool
     */
    public function isFile($path);
    /**
     * @param string $path
     * @return bool
     */
    public function isDir($path);
    /**
     * @param string $path
     * @return string[]|false
     */
    public function readDir($path);
    /**
     * @param string $path
     * @return int|false
     */
    public function lastModTime($path);
}
