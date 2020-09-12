<?php

declare(strict_types=1);

namespace Pike;

class Response {
    /** @var ?string */
    protected $contentType;
    /** @var int */
    protected $statusCode;
    /** @var ?string */
    protected $body;
    /** @var array<int, mixed[]> */
    protected $headers;
    /** @var bool */
    protected $responseIsSent;
    /** @var bool */
    protected $hasRedirect;
    /**
     * @param int $statusCode = 200
     */
    public function __construct(int $statusCode = 200) {
        $this->statusCode = $statusCode;
        $this->headers = [];
        $this->responseIsSent = false;
        $this->hasRedirect = false;
    }
    /**
     * @param int $statusCode
     * @return $this
     */
    public function status(int $statusCode): Response {
        $this->statusCode = $statusCode;
        return $this;
    }
    /**
     * @param object|array|string $data
     * @return $this
     */
    public function json($data): Response {
        $this->body = is_string($data) ? $data : json_encode($data);
        $this->type('json');
        return $this;
    }
    /**
     * @param string $body
     * @return $this
     */
    public function html(string $body): Response {
        $this->body = $body;
        $this->type('html');
        return $this;
    }
    /**
     * @param string $body
     * @return $this
     */
    public function plain(string $body): Response {
        $this->body = $body;
        $this->type('plain');
        return $this;
    }
    /**
     * @param string $data
     * @param string $fileName = 'file.zip'
     * @param string $mime = 'application/zip'
     * @return $this
     */
    public function attachment(string $data,
                               string $fileName = 'file.zip',
                               string $mime = 'application/zip'): Response {
        $this->contentType = $mime;
        $this->headers[] = ['Content-Length', strlen($data)];
        $this->headers[] = ['Content-Disposition', "attachment; filename=\"{$fileName}\""];
        $this->body = $data;
        return $this;
    }
    /**
     * @param string $to
     * @param bool $isPermanent = true
     * @return $this
     */
    public function redirect(string $to,
                             bool $isPermanent = true): Response {
        $this->headers[] = ['Location', $to, true, $isPermanent ? 301 : 302];
        $this->hasRedirect = true;
        return $this;
    }
    /**
     * @param string $name
     * @param string $value
     * @param bool $replace = true
     * @return $this
     */
    public function header(string $name,
                           string $value,
                           bool $replace = true): Response {
        $this->headers[] = [$name, $value, $replace];
        return $this;
    }
    /**
     * Lähettää configuroidut headerit ja datan clientille.
     *
     * @throws \Pike\PikeException
     */
    public function send(): void {
        if (!$this->body && !$this->hasRedirect)
            throw new PikeException('Nothing to send', PikeException::BAD_INPUT);
        $this->sendOutput();
        $this->responseIsSent = true;
    }
    /**
     * @return bool $responseIsSent
     */
    public function sendIfReady(): bool {
        if ($this->isSent()) return true;
        if (($this->contentType && $this->body) || $this->hasRedirect) {
            $this->send();
            return true;
        }
        return false;
    }
    /**
     * @return bool
     */
    public function isSent(): bool {
        return $this->responseIsSent;
    }
    /**
     */
    protected function sendOutput(): void {
        http_response_code($this->statusCode);
        header("Content-Type: {$this->contentType}");
        foreach ($this->headers as $def)
            header("{$def[0]}: {$def[1]}", ...array_slice($def, 2));
        echo $this->body;
    }
    /**
     * @param string $type 'html' | 'json' | 'plain'
     */
    private function type(string $type): void {
        $this->contentType = [
            'html' => 'text/html',
            'json' => 'application/json',
            'plain' => 'text/plain',
        ][$type];
    }
}
