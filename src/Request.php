<?php

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
    /** @var mixed */
    public $user;
    /**
     * @param string $path
     * @param string $method = 'GET'
     * @param object $body = new \stdClass()
     * @param object $files = new \stdClass()
     */
    public function __construct($path,
                                $method = 'GET',
                                $body = null,
                                $files = null) {
        $this->path = urldecode($path);
        $this->method = $method;
        $this->body = $body ?? new \stdClass();
        $this->files = $files ?? new \stdClass();
        $this->params = new \stdClass();
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param string $BASE_URL
     * @param string $urlPath = substr($_SERVER['REQUEST_URI'], strlen($BASE_URL) - 1)
     * @return \Pike\Request
     * @throws \Pike\PikeException
     */
    public static function createFromGlobals($BASE_URL, $urlPath = null) {
        $method = $_SERVER['REQUEST_METHOD'];
        $body = null;
        $files = null;
        if ($method === 'POST' || $method === 'PUT') {
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $body = (object)$_POST;
                $files = (object)$_FILES;
            } else {
                if (!($json = file_get_contents('php://input')))
                    $body = new \stdClass();
                elseif (($body = json_decode($json)) === null)
                    throw new PikeException('Invalid json input', PikeException::BAD_INPUT);
            }
        }
        return new Request(
            $urlPath ?? substr($_SERVER['REQUEST_URI'], strlen($BASE_URL) - 1),
            $method,
            $body,
            $files
        );
    }
}