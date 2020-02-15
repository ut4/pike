<?php

namespace Pike;

class Response {
    private $contentType;
    private $statusCode;
    private $body;
    private $headers;
    private $isSent;
    /**
     * @param integer $statusCode = 200
     */
    public function __construct($statusCode = 200) {
        $this->statusCode = $statusCode;
        $this->headers = [];
        $this->isSent = false;
    }
    /**
     * @param integer $statusCode
     * @return $this
     */
    public function status($statusCode) {
        $this->statusCode = $statusCode;
        return $this;
    }
    /**
     * @param array|object|string $data
     * @return $this
     */
    public function json($data) {
        $this->body = is_string($data) ? $data : json_encode($data);
        $this->type('json');
        return $this;
    }
    /**
     * @param string $body
     * @return $this
     */
    public function html($body) {
        $this->body = $body;
        $this->type('html');
        return $this;
    }
    /**
     * @param string $data
     * @param string $fileName = 'file.zip'
     * @param string $mime = 'application/zip'
     * @return $this
     */
    public function attachment($data,
                               $fileName = 'file.zip',
                               $mime = 'application/zip') {
        $this->contentType = $mime;
        $this->headers['Content-Length'] = [strlen($data)];
        $this->headers['Content-Disposition'] = ["attachment; filename=\"{$fileName}\""];
        $this->body = $data;
        return $this;
    }
    /**
     * @param string $to
     * @param bool $isPermanent = true
     * @return $this
     */
    public function redirect($to, $isPermanent = true) {
        $this->headers['Location'] = [$to, true, $isPermanent ? 301 : 302];
        return $this;
    }
    /**
     * @param string $name
     * @param string $value
     * @param bool $replace = true
     * @return $this
     */
    public function header($name, $value, $replace = true) {
        $this->headers[$name] = [$value, $replace];
        return $this;
    }
    /**
     * Lähettää configuroidut headerit ja datan clientille.
     *
     * @throws \Pike\PikeException
     */
    public function send() {
        if (!$this->body)
            throw new PikeException('Nothing to send', PikeException::BAD_INPUT);
        http_response_code($this->statusCode);
        header('Content-Type: ' .  $this->contentType);
        foreach ($this->headers as $name => $vals) {
            $vals[0] = "{$name}: {$vals[0]}";
            header(...$vals);
        }
        echo $this->body;
        $this->isSent = true;
    }
    /**
     * @return bool $responseIsSent
     */
    public function sendIfReady() {
        if ($this->isSent()) return true;
        if (!$this->contentType || !$this->body) return false;
        $this->send();
        return true;
    }
    /**
     * @return bool
     */
    public function isSent() {
        return $this->isSent;
    }
    /**
     * @param string $type 'html' | 'json'
     */
    private function type($type) {
        $this->contentType = [
            'html' => 'text/html',
            'json' => 'application/json',
        ][$type];
    }
}
