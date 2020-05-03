<?php

declare(strict_types=1);

namespace Me\AuthorizingRoutes\Review;

use Pike\Response;

class ReviewController {
    /**
     * POST /reviews
     *
     * @param \Pike\Response $res
     */
    public function handleCreateReview(Response $res): void {
        // Validoi $req->body, ja insertoi data tietokantaan ...
        //
        if ('jokinEhto')
            $res->json(['insertId' => 1]);
        else
            $res->status(500)->json(['err' => 'Foo']);
    }
    /**
     * PUT /reviews/[i:reviewId]/approve-or-reject
     *
     * @param \Pike\Response $res
     */
    public function handleApproveOrRejectReview(Response $res): void {
        // Validoi $req->body, ja päivitä review $req->params->reviewId tietokantaan ...
        //
        if ('jokinEhto')
            $res->json(['ok' => 'ok']);
        else
            $res->status(500)->json(['err' => 'Foo']);
    }
}
