<?php

namespace Pike\TestUtils;

use Pike\Response;

class MutedSpyingResponse extends Response {
    public function getActualContentType(): ?string {
        return $this->contentType;
    }
    public function getActualStatusCode(): int {
        return $this->statusCode;
    }
    public function getActualBody(): ?string {
        return $this->body;
    }
    public function getActualHeaders(?string $name = null): array {
        if (!$name) return $this->headers;
        $out = [];
        foreach ($this->getActualHeaders() as $header) {
            if ($header[0] === $name) $out[] = $header;
        }
        return $out;
    }
    public function getActualHeader(string $name): ?array {
        foreach ($this->getActualHeaders() as $header) {
            if ($header[0] === $name) return $header;
        }
        return null;
    }
    protected function sendOutput(): void {
        // Muted.
    }
}
