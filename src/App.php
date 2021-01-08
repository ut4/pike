<?php

declare(strict_types=1);

namespace Pike;

use Auryn\Injector;
use Pike\Auth\Authenticator;
use Pike\Auth\Defaults\DefaultCookieStorage;
use Pike\Defaults\DefaultUserRepository;
use Pike\Interfaces\{FileSystemInterface, SessionInterface};

final class App {
    public const VERSION = '0.8.1';
    public const MAKE_AUTOMATICALLY = '@auto';
    /** @var \Pike\AppContext */
    private $ctx;
    /** @var class-string[] */
    private $moduleClsPaths;
    /** @var ?callable */
    private $makeInjector;
    /**
     * @param \Pike\AppContext $ctx
     * @param class-string[] $modules
     * @param ?callable $makeInjector fn(): \Auryn\Injector
     */
    private function __construct(AppContext $ctx,
                                 array $modules,
                                 ?callable $makeInjector) {
        $this->ctx = $ctx;
        $this->moduleClsPaths = $modules;
        $this->makeInjector = $makeInjector;
    }
    /**
     * @param \Pike\Request|string|null $request
     * @param ?string $baseUrl = null
     */
    public function handleRequest($request, ?string $baseUrl = null): void {
        if (!$request || is_string($request))
            $request = Request::createFromGlobals($request, $baseUrl);
        if (($match = $this->ctx->router->match($request->path, $request->method))) {
            // @allow \Pike\PikeException
            [$ctrlClassPath, $ctrlMethodName, $usersRouteCtx] =
                $this->validateRouteMatch($match, $request);
            $request->params = (object)$match['params'];
            $request->name = $match['name'];
            $request->routeInfo = (object)[
                'myCtx' => $usersRouteCtx,
                'name' => $request->name,
            ];
            $this->ctx->req = $request;
            if (!$this->ctx->res) $this->ctx->res = new Response();
            // @allow \Pike\PikeException
            $this->execMiddlewareCallback(0);
            if ($this->ctx->res->commitIfReady())
                return;
            $injector = $this->setupIocContainer();
            $injector->execute("{$ctrlClassPath}::{$ctrlMethodName}");
            if ($this->ctx->auth) $this->ctx->auth->postProcess();
            $this->ctx->res->commitIfReady();
        } else {
            throw new PikeException("No route for {$request->method} {$request->path}");
        }
    }
    /**
     * @param int $index
     */
    private function execMiddlewareCallback(int $index): void {
        $ware = $this->ctx->router->middleware[$index] ?? null;
        if (!$ware || $this->ctx->res->isCommitted()) return;
        // @allow \Pike\PikeException
        call_user_func($ware->fn, $this->ctx->req, $this->ctx->res, function () use ($index) {
            $this->execMiddlewareCallback($index + 1);
        });
    }
    /**
     * @param array<string, mixed> $match
     * @param \Pike\Request $req
     * @return array [string, string, <usersRouteCtx>|null]
     * @throws \Pike\PikeException
     */
    private function validateRouteMatch(array $match, Request $req): array {
        $routeInfo = $match['target'];
        $numItems = is_array($routeInfo) ? count($routeInfo) : 0;
        if ($numItems < 2 ||
            !is_string($routeInfo[0]) ||
            !is_string($routeInfo[1]))
            throw new PikeException(
                "A route ({$req->method} {$req->path}) must return an" .
                " array [\'Ctrl\\Class\\Path\', \'methodName\', <yourCtxVarHere>].",
                PikeException::BAD_INPUT);
        if ($numItems < 3)
            $routeInfo[] = null;
        return $routeInfo;
    }
    /**
     * @return \Auryn\Injector
     */
    private function setupIocContainer(): Injector {
        $container = !$this->makeInjector
            ? new Injector()
            : call_user_func($this->makeInjector);
        $container->share($this->ctx->req);
        $container->share($this->ctx->res);
        $container->share($this->ctx->appConfig);
        if ($this->ctx->db) $container->share($this->ctx->db);
        if ($this->ctx->auth) $container->share($this->ctx->auth);
        $container->alias(FileSystemInterface::class, FileSystem::class);
        $container->alias(SessionInterface::class, NativeSession::class);
        foreach ($this->moduleClsPaths as $clsPath) {
            if (method_exists($clsPath, 'alterIoc'))
                call_user_func([$clsPath, 'alterIoc'], $container);
        }
        return $container;
    }
    /**
     * @param class-string[] $modules
     * @param string|array|\Pike\AppConfig $config = null
     * @param ?\Pike\AppContext $ctx = null
     * @param ?callable $makeInjector = null fn(): \Auryn\Injector
     * @return \Pike\App
     */
    public static function create(array $modules,
                                  $config = null,
                                  AppContext $ctx = null,
                                  callable $makeInjector = null): App {
        $ctx = self::makeEmptyCtx($config, $ctx);
        //
        if (!$ctx->router) {
            $ctx->router = new Router();
            $ctx->router->addMatchTypes(['w' => '[0-9A-Za-z_-]++']);
        }
        if (!$ctx->db &&
            ($ctx->serviceHints['db'] ?? '') === self::MAKE_AUTOMATICALLY) {
            $config = $ctx->appConfig->getVals();
            $ctx->db = new Db($config);
            foreach (get_object_vars($config) as $key => $_) {
                if (strpos($key, 'db.') === 0) $config->{$key} = '<wiped>';
            }
        }
        if (!$ctx->auth &&
            ($ctx->serviceHints['auth'] ?? '') === self::MAKE_AUTOMATICALLY) {
            if (!$ctx->db) throw new PikeException('Can\'t make auth without db',
                                                   PikeException::BAD_INPUT);
            $ctx->auth = new Authenticator(
                function ($_factory) use ($ctx) {
                    return new DefaultUserRepository($ctx->db);
                },
                function ($_factory) {
                    return new NativeSession();
                },
                function ($_factory) use ($ctx) {
                    return new DefaultCookieStorage($ctx);
                },
                'maybeLoggedInUserRole',
                true // doUseRememberMe
            );
        }
        //
        foreach ($modules as $clsPath) {
            if (!method_exists($clsPath, 'init'))
                throw new PikeException("Module ({$clsPath}) must have init()-method",
                                        PikeException::BAD_INPUT);
            call_user_func([$clsPath, 'init'], $ctx);
        }
        //
        return new static($ctx, $modules, $makeInjector);
    }
    /**
     * @param array|string|null $config
     * @param ?\Pike\AppContext $ctx
     * @return \Pike\AppContext
     */
    private static function makeEmptyCtx($config, ?AppContext $ctx): AppContext {
        if (!$ctx)
            $ctx = new AppContext;
        if (is_string($config) && strlen($config))
            $config = require $config;
        $ctx->appConfig = !($config instanceof AppConfig)
            ? new AppConfig($config ?? [])
            : $config;
        return $ctx;
    }
}
