# Hello world -esimerkkiapplikaatio

Tässä tiedostossa Hello World -applikaatio joka demonstroi miten:

- Pike-applikaatio laitetaan liikkeelle ([index.php](#indexphp))
- HTTP-reittejä rekisteröidään, koodia voi jaotella ryhmiin ([SomeModule.php](#helloworldsomemodulephp))
- HTTP-pyyntöjen parametreihin ja POST-dataan pääse käsiksi, miten selaimelle lähetetään tietoa takaisin ([SomeController.php](#helloworldsomecontrollerphp))
- [Auryn\Injector](https://github.com/rdlowrey/auryn) injektoi automaattisesti luokat (SomeClass.php) kontrollereihin type-hinttien perusteella

## Sisällysluettelo

- [index.php](#index.php)
- [HelloWorld/SomeModule.php](#helloworldsomemodulephp)
- [HelloWorld/SomeController.php](#helloworldsomecontrollerphp)
- [HelloWorld/SomeClass.php](#helloworldsomeclassphp)
- [Esimerkkipyynnöt](#esimerkkipyynnöt)

## index.php

```php
<?php

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Me\\HelloWorld\\', __DIR__ . '/HelloWorld/src');

$myModules = [\Me\HelloWorld\SomeModule::class];
$app = \Pike\App::create($myModules);

$req = \Pike\Request::createFromGlobals('', $_GET['q'] ?? '/');
$app->handleRequest($req);

```

## HelloWorld/SomeModule.php

```php
<?php

declare(strict_types=1);

namespace Me\HelloWorld;

abstract class SomeModule {
    /**
     * @param \stdClass $ctx  {\Pike\Router router}
     */
    public static function init(\stdClass $ctx): void {
        // ks. examples/mapping-routes.md
        $ctx->router->map('GET', '/some-route',
            [SomeController::class, 'handleSomeRoute']
        );
        $ctx->router->map('POST', '/another-route/[*:someParam]',
            [SomeController::class, 'handleAnotherRoute']
        );
    }
}

```

## HelloWorld/SomeController.php

```php
<?php

declare(strict_types=1);

namespace Me\HelloWorld;

use Pike\Response;
use Pike\Request;

class SomeController {
    /**
     * @param \Me\HelloWorld\SomeClass $myClass
     * @param \Pike\Response $res
     */
    public function handleSomeRoute(SomeClass $myClass, Response $res): void {
        $data = $myClass->doSomething();
        if ($data)
            $res->json([$data]);
        else
            $res->status(500)->json(['err' => 1]);
    }
    /**
     * @param \Pike\Request $req
     * @param \Pike\Response $res
     */
    public function handleAnotherRoute(Request $req, Response $res): void {
        $res->json(['yourParamWas' => $req->params->someParam,
                    'requestBodyWas' => $req->body]);
    }
}

```

## HelloWorld/SomeClass.php

```php
<?php

declare(strict_types=1);

namespace Me\HelloWorld;

class SomeClass {
    /**
     * @return string|null
     */
    public function doSomething(): ?string {
        return 'Hello';
    }
}

```

## Esimerkkipyynnöt

Esimerkin applikaatio löytyy kansiosta `/examples/`, jota voi testata esim. php:hen bundlatulla dev-serverillä:
- `cd examples`
- `php -S localhost:8080`

Kansion `/examples/hello-world.php` vastaa esimerkin `index.php` -tiedostoa.

- some-route
    - url: http://localhost:8080/hello-world.php?q=/some-route
    - method: GET
- another-route
    - url: http://localhost:8080/hello-world.php?q=/another-route/foo
    - method: POST
    - body: {"any": "thing"}
    - header: Content-Type: application/json
