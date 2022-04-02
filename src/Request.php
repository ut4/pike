<?php

declare(strict_types=1);

namespace Pike;

class Request {
    /** @var string */
    public $path;
    /** @var string */
    public $method;
    /** @var object */
    public $params;
    /** @var object */
    public $body;
    /** @var object */
    public $files;
    /** @var array<string, string> */
    public $serverVars;
    /** @var array<string, string> */
    public $queryVars;
    /** @var array<string, string> */
    public $cookies;
    /** @var ?object {myCtx: mixed, name: ?string} */
    public $routeInfo;
    /** @var ?string */
    public $name;
    /** @var mixed */
    public $myData;
    /** @var mixed */
    public $user;
    /**
     * @param string $path
     * @param string $method = 'GET'
     * @param ?object $body = new \stdClass
     * @param ?object $files = new \stdClass
     * @param ?array $serverVars = null
     * @param ?array $queryVars = null
     * @param ?array $cookies = null
     */
    public function __construct(string $path,
                                string $method = 'GET',
                                ?object $body = null,
                                ?object $files = null,
                                ?array $serverVars = null,
                                ?array $queryVars = null,
                                ?array $cookies = null) {
        $this->path = $path !== '' ? $path : '/';
        $this->method = $method;
        $this->body = $body ?? new \stdClass;
        $this->files = $files ?? new \stdClass;
        $this->params = new \stdClass;
        $this->serverVars = $serverVars ?? [];
        $this->queryVars = $queryVars ?? [];
        $this->cookies = $cookies ?? [];
    }
    /**
     * @param string $key
     * @param ?string $default = null
     * @return ?string
     */
    public function queryVar(string $key, ?string $default = null): ?string {
        return $this->queryVars[$key] ?? $default;
    }
    /**
     * @param string $key e.g. 'SERVER_NAME'
     * @param mixed $default = null
     * @return mixed
     */
    public function attr(string $key, $default = null) {
        return $this->serverVars[$key] ?? $default;
    }
    /**
     * @param string $key
     * @param ?string $default = null
     * @return ?string
     */
    public function cookie(string $key, ?string $default = null): ?string {
        return $this->cookies[$key] ?? $default;
    }
    /**
     * @param string $name
     * @param ?string $default = null
     * @return ?string
     */
    public function header(string $name, ?string $default = null): ?string {
        // x-requested-with / X-Requested-With -> X_REQUESTED_WITH
        $key = str_replace('-', '_', strtoupper($name));
        return $this->attr(
            // https://github.com/symfony/http-foundation/blob/5139321b2b54dd2859540c9dbadf6fddf63ad1a5/ServerBag.php#L28
            (!in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)
                ? 'HTTP_'
                : '') . $key,
            $default
        );
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param ?string $fullUrl = $_SERVER['REQUEST_URI']
     * @param ?string $baseUrl = null
     * @return \Pike\Request
     * @throws \Pike\PikeException
     */
    public static function createFromGlobals(?string $fullUrl = null,
                                             ?string $baseUrl = null): Request {
        $method = $_SERVER['REQUEST_METHOD'];
        $body = null;
        $files = null;
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0) {
            if (($json = file_get_contents('php://input'))) {
                if (($body = json_decode($json)) === null)
                    throw new PikeException('Invalid json input',
                                            PikeException::BAD_INPUT);
                elseif (!is_object($body))
                    throw new PikeException('Input json must be an object',
                                            PikeException::BAD_INPUT);
            }
        } else {
            $body = (object) $_POST;
            $files = (object) $_FILES;
        }
        $urlPath = explode('?', !$fullUrl ? $_SERVER['REQUEST_URI'] : $fullUrl)[0];
        return new Request(
            !$baseUrl || ($baseless = substr($urlPath, strlen($baseUrl) - 1)) === false
                ? $urlPath
                : $baseless,
            $method,
            $body,
            $files,
            $_SERVER,
            $_GET,
            $_COOKIE
        );
    }
}
