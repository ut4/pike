<?php

declare(strict_types=1);

namespace Pike;

use Auryn\Injector;
use Pike\Interfaces\{FileSystemInterface, SessionInterface};

final class App {
    public const VERSION = '0.9.0-dev';
    public const MAKE_AUTOMATICALLY = '@auto';
    /** @var object[] */
    private $moduleInstances;
    /** @var \Pike\AppContext */
    private $ctx;
    /**
     * @param object[] $modules
     * @param ?\Pike\AppContext $ctx = null
     * @param ?\Pike\Router $router = null
     */
    public function __construct(array $modules,
                                ?AppContext $ctx = null,
                                ?Router $router = null) {
        self::throwIfAnyModuleIsNotValid($modules);
        $this->moduleInstances = $modules;
        $this->ctx = $ctx ?? new AppContext;
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
        $this->forEachModuleCall('init', $this->ctx);
        //
        $this->ctx->req = !$request || is_string($request)
            ? Request::createFromGlobals($request, $baseUrl)
            : $request;
        $this->ctx->res = $response ?? new Response;
        $req = $this->ctx->req;
        $res = $this->ctx->res;
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
            $responseWasSent = $this->runMiddleware();
            if ($responseWasSent) return;
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
     * @return object[]
     */
    public function &getModules(): array {
        return $this->moduleInstances;
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
                throw new PikeException(get_class($instance) . '->init(\Pike\Router $router) is required',
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
     * @return bool $responseWasSent
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
            if ($i === $iBefore || // Did the middleware skip next()?
                $res->isCommitted()) return true;
        }
        return $res->commitIfReady();
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
     * @param mixed $arg
     */
    private function forEachModuleCall(string $methodName, $arg): void {
        foreach ($this->moduleInstances as $instance) {
            if (method_exists($instance, $methodName))
                call_user_func([$instance, $methodName], $arg);
        }
    }
}
