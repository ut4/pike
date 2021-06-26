<?php

declare(strict_types=1);

namespace Pike;

use Auryn\Injector;
use Pike\Interfaces\{FileSystemInterface, SessionInterface};

final class App {
    public const VERSION = '0.9.0-dev';
    /** @var object[] */
    private $moduleInstances;
    /** @var \Pike\AppContext */
    private $ctx;
    /** @var ?\Closure */
    private $populateCtx;
    /** @var ?\Pike\ServiceDefaults */
    private $serviceDefaults;
    /**
     * @param object[] $modules
     * @param ?\Closure $populateCtx = null
     * @param ?\Pike\AppContext $initialCtx = null
     * @param ?\Pike\Router $router = null
     */
    public function __construct(array $modules,
                                ?\Closure $populateCtx = null,
                                ?AppContext $initialCtx = null,
                                ?Router $router = null) {
        self::throwIfAnyModuleIsNotValid($modules);
        $this->moduleInstances = $modules;
        $this->populateCtx = $populateCtx;
        $this->ctx = $initialCtx ?? new AppContext;
        $this->ctx->router = $router ?? new Router;
        $this->ctx->router->addMatchTypes(['w' => '[0-9A-Za-z_-]++']);
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
        $this->ctx->req = !$request || is_string($request)
            ? Request::createFromGlobals($request, $baseUrl)
            : $request;
        $this->ctx->res = $response ?? new Response;
        $req = $this->ctx->req;
        $res = $this->ctx->res;
        //
        $ctxIsPopulated = false;
        $populateCtxIfNotPopulated = function () use (&$ctxIsPopulated): void {
            if ($ctxIsPopulated) return;
            if ($this->populateCtx) call_user_func(
                $this->populateCtx,
                $this->ctx,
                $this->serviceDefaults ?? new ServiceDefaults($this->ctx)
            );
            $ctxIsPopulated = true;
        };
        $this->forEachModuleCall('init', $this->ctx, $populateCtxIfNotPopulated);
        //
        if (($match = $this->ctx->router->match($req->path, $req->method))) {
            // @allow \Pike\PikeException
            [$ctrlClassPath, $ctrlMethodName, $userDefinedRouteCtx] =
                $this->validateRouteMatch($match);
            $req->params = (object) $match['params'];
            $req->name = $match['name'];
            $req->routeInfo = (object) [
                'myCtx' => $userDefinedRouteCtx,
                'name' => $req->name,
            ];
            //
            $populateCtxIfNotPopulated();
            $allWaresRan = $this->runMiddleware();
            if (!$allWaresRan) return;
            //
            $di = $this->makeDi();
            $this->forEachModuleCall('alterDi', $di);
            $di->execute("{$ctrlClassPath}::{$ctrlMethodName}");
            if (isset($this->ctx->auth)) $this->ctx->auth->postProcess();
            $res->commitIfReady();
        } else {
            throw new PikeException("No route for {$req->method} {$req->path}");
        }
    }
    /**
     * @param callable(\Pike\AppContext $ctx): \Pike\ServiceDefaults $fn
     */
    public function setServiceInstantiator(callable $fn): void {
        $this->serviceDefaults = $fn($this->ctx);
    }
    /**
     * @return object[]
     */
    public function &getModules(): array {
        return $this->moduleInstances;
    }
    /**
     * @return \Pike\AppContext
    */
    public function getCtx(): AppContext {
        return $this->ctx;
    }
    /**
     * @param object[] $modules
     */
    private static function throwIfAnyModuleIsNotValid(array $modules): void {
        foreach ($modules as $instance) {
            if (!is_object($instance))
                throw new PikeException('A module (' . json_encode($instance) . ') must be an object',
                                        PikeException::BAD_INPUT);
            if (!method_exists($instance, 'init'))
                throw new PikeException(get_class($instance) . '->init(\Pike\AppContext $ctx) is required',
                                        PikeException::BAD_INPUT);
        }
    }
    /**
     * @param array<string, mixed> $match
     * @return array [string, string, <userDefinedRouteCtx>|null]
     * @throws \Pike\PikeException
     */
    private function validateRouteMatch(array $match): array {
        $routeInfo = $match['target'];
        $numItems = is_array($routeInfo) ? count($routeInfo) : 0;
        if ($numItems < 2 ||
            !is_string($routeInfo[0]) ||
            !is_string($routeInfo[1]))
            throw new PikeException(
                "A route ({$this->ctx->req->method} {$this->ctx->req->path}) must return an" .
                " array [\'Ctrl\\Class\\Path\', \'methodName\', <yourCtxVarHere>].",
                PikeException::BAD_INPUT);
        if ($numItems < 3)
            $routeInfo[] = null;
        return $routeInfo;
    }
    /**
     * @return bool $didEveryMiddlewareCallNext
     */
    private function runMiddleware(): bool {
        $i = 0;
        $next = function () use (&$i): void { ++$i; };
        $wares = $this->ctx->router->middleware;
        $req = $this->ctx->req;
        $res = $this->ctx->res;
        while ($i < count($wares)) {
            $iBefore = $i;
            call_user_func($wares[$iBefore]->fn, $req, $res, $next);
            if ($i === $iBefore) // Middleware didn't call next()
                return false;
        }
        return true;
    }
    /**
     * @return \Auryn\Injector
     */
    private function makeDi(): Injector {
        $di = new Injector;
        $di->share($this->ctx->req);
        $di->share($this->ctx->res);
        if (isset($this->ctx->config)) $di->share($this->ctx->config);
        if (isset($this->ctx->db)) $di->share($this->ctx->db);
        if (isset($this->ctx->auth)) $di->share($this->ctx->auth);
        $di->alias(FileSystemInterface::class, FileSystem::class);
        $di->alias(SessionInterface::class, NativeSession::class);
        return $di;
    }
    /**
     * @param string $methodName
     * @param mixed[] $args
     */
    private function forEachModuleCall(string $methodName, ...$args): void {
        foreach ($this->moduleInstances as $instance) {
            if (method_exists($instance, $methodName))
                call_user_func([$instance, $methodName], ...$args);
        }
    }
}
