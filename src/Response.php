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
    protected $responseIsCommitted;
    /** @var bool */
    protected $hasRedirect;
    /**
     * @param int $statusCode = 200
     */
    public function __construct(int $statusCode = 200) {
        $this->contentType = 'text/html';
        $this->statusCode = $statusCode;
        $this->body = null;
        $this->headers = [];
        $this->responseIsCommitted = false;
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
     * @param string $type 'html' | 'json' | 'plain' | 'content/type'
     * @return $this
     */
    public function type(string $type): Response {
        $this->contentType = [
            'html' => 'text/html',
            'json' => 'application/json',
            'plain' => 'text/plain',
        ][$type] ?? $type;
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
     * @param string $body
     */
    public function send(string $body): void {
        $this->body = $body;
    }
    /**
     * @return bool $responseIsCommitted
     */
    public function commitIfReady(): bool {
        if ($this->isCommitted()) return true;
        if (($this->contentType && $this->body !== null) || $this->hasRedirect) {
            $this->commit();
            return true;
        }
        return false;
    }
    /**
     * @return bool
     */
    public function isCommitted(): bool {
        return $this->responseIsCommitted;
    }
    /**
     * @throws \Pike\PikeException
     */
    protected function commit(): void {
        if ($this->body === null && !$this->hasRedirect)
            throw new PikeException('Nothing to send', PikeException::BAD_INPUT);
        http_response_code($this->statusCode);
        header("Content-Type: {$this->contentType}");
        foreach ($this->headers as $def)
            header("{$def[0]}: {$def[1]}", ...array_slice($def, 2));
        echo $this->body;
        $this->responseIsCommitted = true;
    }
}
