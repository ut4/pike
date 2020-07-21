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
    protected function sendOutput(): void {
        // Muted.
    }
}
