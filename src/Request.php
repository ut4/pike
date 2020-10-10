<?php

declare(strict_types=1);

namespace Pike;

class Request {
    /** @var string */
    public $path;
    /** @var string */
    public $method;
    /** @var object */
    public $body;
    /** @var object */
    public $files;
    /** @var object */
    public $params;
    /** @var ?object {myCtx: mixed, name: ?string} */
    public $routeInfo;
    /** @var ?string */
    public $name;
    /** @var mixed */
    public $myData;
    /** @var mixed */
    public $user;
    /** @var array */
    private $serverVars;
    /**
     * @param string $path
     * @param string $method = 'GET'
     * @param object $body = new \stdClass
     * @param object $files = new \stdClass
     * @param array $serverVars = []
     */
    public function __construct(string $path,
                                string $method = 'GET',
                                object $body = null,
                                object $files = null,
                                array $serverVars = null) {
        $this->path = urldecode($path !== '' ? $path : '/');
        $this->method = $method;
        $this->body = $body ?? new \stdClass;
        $this->files = $files ?? new \stdClass;
        $this->params = new \stdClass;
        $this->serverVars = $serverVars ?? [];
    }
    /**
     * @param string $key
     * @param ?string $default = null
     * @return ?string
     */
    public function queryVar(string $key, ?string $default = null): ?string {
        return $_GET[$key] ?? $default;
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
        return $_COOKIE[$key] ?? $default;
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
     * @param string $baseUrl
     * @param string $urlPath = substr($_SERVER['REQUEST_URI'], strlen($baseUrl) - 1)
     * @return \Pike\Request
     * @throws \Pike\PikeException
     */
    public static function createFromGlobals(string $baseUrl,
                                             string $urlPath = null): Request {
        $method = $_SERVER['REQUEST_METHOD'];
        $body = null;
        $files = null;
        if ($method === 'POST' || $method === 'PUT') {
            if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
                if (!($json = file_get_contents('php://input')))
                    $body = new \stdClass;
                elseif (($body = json_decode($json)) === null)
                    throw new PikeException('Invalid json input', PikeException::BAD_INPUT);
            } else {
                $body = (object) $_POST;
                $files = (object) $_FILES;
            }
        }
        return new Request(
            $urlPath ?? substr($_SERVER['REQUEST_URI'], strlen($baseUrl) - 1),
            $method,
            $body,
            $files,
            $_SERVER,
        );
    }
}
