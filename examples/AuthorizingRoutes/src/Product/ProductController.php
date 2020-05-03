<?php

declare(strict_types=1);

namespace Me\AuthorizingRoutes\Product;

use Pike\Response;

class ProductController {
    /**
     * POST /products
     *
     * @param \Pike\Response $res
     */
    public function handleCreateProduct(Response $res): void {
        // Validoi $req->body, ja insertoi data tietokantaan ...
        //
        if ('jokinEhto')
            $res->json(['insertId' => 1]);
        else
            $res->status(500)->json(['err' => 'Foo']);
    }
    /**
     * PUT /products/[i:productId]
     *
     * @param \Pike\Response $res
     */
    public function handleEditProduct(Response $res): void {
        // Validoi $req->body, päivitä data tietokantaan id:llä
        // $req->params->productId ...
        //
        if ('jokinEhto')
            $res->json(['ok' => 'ok']);
        else
            $res->status(500)->json(['err' => 'Foo']);
    }
    /**
     * POST /products/[i:productId]/comment
     *
     * @param \Pike\Response $res
     */
    public function handleAddComment(Response $res): void {
        // Validoi $req->body, ja insertoi kommentti tietokantaan tuotteelle
        // $req->params->productId ...
        //
        if ('jokinEhto')
            $res->json(['ok' => 'ok']);
        else
            $res->status(500)->json(['err' => 'Foo']);
    }
}
