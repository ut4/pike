<?php

declare(strict_types=1);

namespace Me\AuthorizingRoutes\Product;

use Pike\AppContext;

abstract class ProductModule {
    /**
     * @param \Pike\AppContext $ctx
     */
    public static function init(AppContext $ctx): void {
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
