<?php

declare(strict_types=1);

namespace Me\AuthorizingRoutes\Product;

abstract class ProductModule {
    /**
     * @param \stdClass $ctx {\Pike\Router router}
     */
    public static function init(\stdClass $ctx) {
        $ctx->router->map('POST', '/products',
            [ProductController::class, 'handleCreateProduct', 'create:products']
        );
        $ctx->router->map('PUT', '/products/[i:productId]',
            [ProductController::class, 'handleEditProduct', 'edit:products']
        );
        $ctx->router->map('POST', '/products/[i:productId]/comment',
            [ProductController::class, 'handleAddComment', 'comment:products']
        );
    }
}
