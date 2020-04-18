<?php

declare(strict_types=1);

namespace Me\AuthorizingRoutes\Review;

abstract class ReviewModule {
    /**
     * @param \stdClass $ctx {\Pike\Router router}
     */
    public static function init(\stdClass $ctx) {
        $ctx->router->map('POST', '/reviews',
            [ReviewController::class, 'handleCreateReview', 'post:reviews']
        );
        $ctx->router->map('PUT', '/reviews/[i:reviewId]/approve-or-reject',
            [ReviewController::class, 'handleApproveOrRejectReview', 'approveOrReject:reviews']
        );
    }
}
