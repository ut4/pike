<?php

declare(strict_types=1);

namespace Me\AuthorizingRoutes\Review;

use Pike\AppContext;

abstract class ReviewModule {
    /**
     * @param \Pike\AppContext $ctx
     */
    public static function init(AppContext $ctx) {
        $ctx->router->map('POST', '/reviews',
            [ReviewController::class, 'handleCreateReview', 'post:reviews']
        );
        $ctx->router->map('PUT', '/reviews/[i:reviewId]/approve-or-reject',
            [ReviewController::class, 'handleApproveOrRejectReview', 'approveOrReject:reviews']
        );
    }
}
