# Reittien mappaus

Tämä esimerkki demonstroi:
- , että Pikessä reitit määritellään moduulien `init()`-tiedostossa
- , että reitteihin **tulee** määritellä `[SomeController::class, 'methodName']`
- , että reitille **voi** määritellä nimen, ja kontekstin
- , miten reittiin määritelty tieto päätyy `Pike\Request` -olioon

! Pike käyttää reititykseen `AltoRouteria`, jonka dokumentaatio löytyy osoitteesta [altorouter.com/usage/mapping-routes.html](https://altorouter.com/usage/mapping-routes.html). AltoRouterin _Match Types_:ien (`[i:myId]`) lisäksi voit käyttää myös Piken [omia](#piken-rekisteröimät-match-typet).

## Sisällysluettelo

- [MappingRoutes/Module.php](#mappingroutesmodulephp)
- [MappingRoutes/Controller.php](#mappingroutescontrollerphp)
- [Esimerkkikutsut ja vastaukset](#esimerkkikutsut-ja-vastaukset)
- [Match Typet](#piken-rekisteröimät-match-typet)

## MappingRoutes/Module.php

```php
<?php

declare(strict_types=1);

namespace Me\MappingRoutes;

abstract class Module {
    /**
     * @param \stdClass $ctx {\Pike\Router router}
     */
    public static function init(\stdClass $ctx) {
        $ctx->router->map('GET', '/route-a',
            [Controller::class, 'handleRouteA']
        );
        $ctx->router->map('GET', '/route-b/[i:myNumber]/[w:myOptionalSlug]?',
            [Controller::class, 'handleRouteB']
        );
        $ctx->router->map('GET', '/route-c/[foo|bar:fooOrBar]',
            [Controller::class, 'handleRouteC'],
            'nameOfRouteC'
        );
        $ctx->router->map('POST', '/route-d/[i:id]',
            [Controller::class, 'handleRouteC', ['my' => 'context']]
        );
        /*
        $ctx->router->map('GET', '/some-route', 'notAnArray');   // PikeException
        $ctx->router->map('GET', '/some-route', ['incomplete']); // PikeException
        $ctx->router->map('GET', '/some-route');                 // PikeException
        */
    }
}

```

## MappingRoutes/Controller.php

```php
<?php

declare(strict_types=1);

namespace Me\MappingRoutes;

use Pike\Response;
use Pike\Request;

class Controller {
    public function handleRouteA(Request $req, Response $res): void {
        $res->json((object) [
            'params' => $req->params,
            'body' => $req->body,
            'routeCtx' => $req->routeCtx, 
        ]);
    }
    // loput handlerit identtisiä handleRouteA:n kanssa
    // ...
}
```

## Esimerkkikutsut ja vastaukset

Esimerkin applikaatio löytyy kansiosta `/examples/`, jota voi testata esim. php:hen bundlatulla dev-serverillä:
- `cd examples`
- `php -S localhost:8080`

### route-a

```php
GET 'http://localhost:8080/mapping-routes.php?q=/route-a'
$req->params;   // {}
$req->body;     // {}
$req->routeCtx; // {"myData":null,"name":null}
```

### route-b

```php
GET 'http://localhost:8080/mapping-routes.php?q=/route-b/1/foo-bar'
$req->params;   // {"myNumber":"1","myOptionalSlug":"foo-bar"}
$req->body;     // {}
$req->routeCtx; // {"myData":null,"name":null}

GET 'http://localhost:8080/mapping-routes.php?q=/route-b/2'
$req->params;   // {"myNumber":"1"}
$req->body;     // {}
$req->routeCtx; // {"myData":null,"name":null}

GET 'http://localhost:8080/mapping-routes.php?q=/route-b/3/not-allœw€d'
-> PikeException: No match

GET 'http://localhost:8080/mapping-routes.php?q=/route-b/not-a-number'
-> PikeException: No match
```

### route-c

```php
GET 'http://localhost:8080/mapping-routes.php?q=/route-c/foo'
->
$req->params;   // {"fooOrBar":"foo"}
$req->body;     // {}
$req->routeCtx; // {"myData":null,"name":"nameOfRouteC"}

GET 'http://localhost:8080/mapping-routes.php?q=/route-c/baz'
->
PikeException: No match
```

### route-d

```php
POST 'http://localhost:8080/mapping-routes.php?q=/route-d/1'
(Headers): `Content-Type: application/json`
(Body): `{"foo":"bar"}`
// ->
$req->params;   // {"id:"1"}
$req->body;     // {"foo":"bar"}
$req->routeCtx; // {"myData":{"my":"context"},"name":null}

POST 'http://localhost:8080/mapping-routes.php?q=/route-d/2'
(Headers): `Content-Type: application/json`
(Body): none
// ->
$req->params;   // {"id:"2"}
$req->body;     // {}
$req->routeCtx; // {"myData":{"my":"context"},"name":null}

POST 'http://localhost:8080/mapping-routes.php?q=/route-d/3'
(Headers): none
(Body): `{"bar":"baz"}`
// ->
$req->params;   // {"id:"3"}
$req->body;     // {}
$req->routeCtx; // {"myData":{"my":"context"},"name":null}

POST 'http://localhost:8080/mapping-routes.php?q=/route-d/d'
// ->
PikeException: No match
```

## Piken rekisteröimät Match Typet

```php
$router->addMatchTypes(['w' => '[0-9A-Za-z_-]++']);
```
