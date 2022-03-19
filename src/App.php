<?php

declare(strict_types=1);

namespace Pike;

class App {
    public const VERSION = "1.0.0-alpha1";
    /** @var \ArrayObject */
    protected $mods;
    /** @var \Pike\Injector */
    protected $di;
    /**
     */
    public function __construct() {
        $this->mods = new \ArrayObject;
        $this->di = new Injector;
        $router = new Router;
        $router->addMatchTypes(["w" => "[0-9A-Za-z_-]++"]);
        $this->di->share($router);
    }
    /**
     * @param object[] $modules
     * @return $this
     */
    public function setModules(array $modules) {
        foreach ($modules as $instance) {
            if (!is_object($instance))
                throw new PikeException("A module (" . json_encode($instance) . ") must be an object",
                                        PikeException::BAD_INPUT);
            if (!method_exists($instance, "init"))
                throw new PikeException(get_class($instance) . "->init(\Pike\Router \$router) is required",
                                        PikeException::BAD_INPUT);
        }
        $this->mods->exchangeArray($modules);
        return $this;
    }
    /**
     * @param callable $fn callable(\Pike\Injector $di):void
     * @return $this
     */
    public function defineInjectables(callable $fn) {
        call_user_func($fn, $this->di);
        return $this;
    }
    /**
     * @param \Pike\Request|string|null $request
     * @param ?string $baseUrl = null
     * @param ?\Pike\Response $response = null
     * @throws \Pike\PikeException
     */
    public function handleRequest($request,
                                  ?string $baseUrl = null,
                                  ?Response $response = null): void {
        if (!$request || is_string($request))
            $request = Request::createFromGlobals($request, $baseUrl);
        $this->di->share($request);
        $router = $this->di->make(Router::class);
        $di = $this->di;
        foreach ($this->mods as $mod) {
            $mod->init($router, $di);
        }
        //
        $match = $router->match($request->path, $request->method);
        if (!$match) {
            throw new PikeException("No route for {$request->method} {$request->path}");
        }
        [$ctrlClassPath, $ctrlMethodName, $userDefinedRouteCtx] =
            $this->validateRouteMatch($match, $request);
        $request->params = (object) $match["params"];
        $request->name = $match["name"];
        $request->routeInfo = (object) [
            "myCtx" => $userDefinedRouteCtx,
            "name" => $request->name,
        ];
        if (!$response) $response = new Response;
        $this->di->share($response);
        //
        foreach ($this->mods as $mod) {
            if (method_exists($mod, "beforeExecCtrl"))
                $mod->beforeExecCtrl($this->di);
        }
        $allWaresRan = $this->runMiddleware($router, $request, $response);
        if ($allWaresRan) {
            if (isset($this->ctx->auth)) $this->ctx->auth->postProcess();
            $this->di->execute("{$ctrlClassPath}::{$ctrlMethodName}");
        }
        $response->commitIfReady();
    }
    /**
     * @return \ArrayObject
     */
    public function getModules(): \ArrayObject {
        return $this->modules;
    }
    /**
     * @return \Pike\Injector
     */
    public function getDi(): Injector {
        return $this->di;
    }
    /**
     * @param array<string, mixed> $match
     * @param \Pike\Request $req
     * @return array [string, string, <userDefinedRouteCtx>|null]
     * @throws \Pike\PikeException
     */
    private function validateRouteMatch(array $match, Request $req): array {
        $routeInfo = $match["target"];
        $numItems = is_array($routeInfo) ? count($routeInfo) : 0;
        if ($numItems < 2 ||
            !is_string($routeInfo[0]) ||
            !is_string($routeInfo[1]))
            throw new PikeException(
                "A route ({$req->method} {$req->path}) must return an" .
                " array [\"Ctrl\\Class\\Path\", \"methodName\", <yourCtxVarHere>].",
                PikeException::BAD_INPUT);
        if ($numItems < 3)
            $routeInfo[] = null;
        return $routeInfo;
    }
    /**
     * @param \Pike\Router $router
     * @param \Pike\Request $req
     * @param \Pike\Response $res
     * @return bool $didEveryMiddlewareCallNext
     */
    private function runMiddleware($router, $req, $res): bool {
        $i = 0;
        $next = function () use (&$i): void { ++$i; };
        $wares = $router->middleware;
        while ($i < count($wares)) {
            $iBefore = $i;
            call_user_func($wares[$iBefore]->fn, $req, $res, $next);
            if ($i === $iBefore) // Middleware didn"t call next()
                return false;
        }
        return true;
    }
}
