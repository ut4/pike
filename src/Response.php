<?php

namespace Pike;

class Response {
    public $contentType;
    public $statusCode;
    /**
     * @param integer $statusCode = 200
     */
    public function __construct($statusCode = 200) {
        $this->statusCode = $statusCode;
    }
    /**
     * @param integer $statusCode
     * @return Response
     */
    public function status($statusCode) {
        $this->statusCode = $statusCode;
        return $this;
    }
    /**
     * @param array|object|string $data
     */
    public function json($data) {
        $this->type('json')->send($data);
    }
    /**
     * @param string $data
     */
    public function html($body) {
        $this->type('html')->send($body);
    }
    /**
     * @param string $data
     * @param string $fileName = 'file.zip'
     * @param string $mime = 'application/zip'
     */
    public function attachment($data,
                               $fileName = 'file.zip',
                               $mime = 'application/zip') {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($data));
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo $data;
    }
    /**
     * @param string $type 'html' | 'json'
     * @return Response
     */
    protected function type($type) {
        $this->contentType = [
            'html' => 'text/html',
            'json' => 'application/json',
        ][$type];
        return $this;
    }
    /**
     * @param string|array|object $body = ''
     */
    protected function send($body = '') {
        http_response_code($this->statusCode);
        header('Content-Type: ' .  $this->contentType);
        echo is_string($body) ? $body : json_encode($body);
    }
    /**
     * @param string $to
     * @param bool $isPermanent = true
     */
    public function redirect($to, $isPermanent = true) {
        header('Location: ' . $to, true, $isPermanent ? 301 : 302);
    }
}
