<?php

namespace Pike;

use Auryn\Injector;
use Pike\Auth\Authenticator;
use Pike\Auth\Crypto;
use Pike\Auth\Internal\CachingServicesFactory;

final class App {
    public const VERSION = '0.1.0';
    public const SERVICE_DB = 'db';
    public const SERVICE_AUTH = 'auth';
    public const MAKE_AUTOMATICALLY = '@auto';
    private $ctx;
    private $moduleClsPaths;
    private $makeInjector;
    /**
     * @param \stdClass $ctx
     * @param string[] $modules
     * @param null|fn(): \Auryn\Injector $makeInjector
     */
    private function __construct(\stdClass $ctx,
                                 array $modules,
                                 callable $makeInjector = null) {
        $this->ctx = $ctx;
        $this->moduleClsPaths = $modules;
        $this->makeInjector = $makeInjector;
    }
    /**
     * @param \Pike\Request|string $request
     * @param string $urlPath = null
     */
    public function handleRequest($request, $urlPath = null) {
        if (is_string($request))
            $request = Request::createFromGlobals($request, $urlPath);
        if (($match = $this->ctx->router->match($request->path, $request->method))) {
            $request->params = (object)$match['params'];
            $request->name = $match['name'];
            // @allow \Pike\PikeException
            [$ctrlClassPath, $ctrlMethodName, $usersRouteCtx] =
                $this->validateRouteMatch($match, $request);
            $middlewareLoopState = (object)['req' => $request,
                'res' => $this->ctx->res ?? new Response()];
            $middlewareLoopState->req->routeCtx = (object)[
                'myData' => $usersRouteCtx,
                'name' => $match['name'],
            ];
            // @allow \Pike\PikeException
            $this->execMiddlewareCallback(0, $middlewareLoopState);
            if ($middlewareLoopState->res->sendIfReady())
                return;
            $injector = $this->setupIocContainer($middlewareLoopState);
            $injector->execute($ctrlClassPath . '::' . $ctrlMethodName);
            $middlewareLoopState->res->sendIfReady();
        } else {
            throw new PikeException("No route for {$request->method} {$request->path}");
        }
    }
    /**
     * @param int $index
     * @param {req: \Pike\Request, res: \Pike\Response} $state
     */
    private function execMiddlewareCallback($index, $state) {
        $ware = $this->ctx->router->middleware[$index] ?? null;
        if (!$ware || $state->res->isSent()) return;
        // @allow \Pike\PikeException
        call_user_func($ware->fn, $state->req, $state->res, function () use ($index, $state) {
            $this->execMiddlewareCallback($index + 1, $state);
        });
    }
    /**
     * @param array $match
     * @param \Pike\Request $req
     * @return array [string, string, <usersRouteCtx>|null]
     * @throws \Pike\PikeException
     */
    private function validateRouteMatch($match, $req) {
        $routeInfo = $match['target'];
        $numItems = is_array($routeInfo) ? count($routeInfo) : 0;
        if ($numItems < 2 ||
            !is_string($routeInfo[0]) ||
            !is_string($routeInfo[1]))
            throw new PikeException(
                'A route (' . $req->method . ' ' . $req->path . ') must return an' .
                ' array [\'Ctrl\\Class\\Path\', \'methodName\', <yourCtxVarHere>].',
                PikeException::BAD_INPUT);
        if ($numItems < 3)
            $routeInfo[] = null;
        return $routeInfo;
    }
    /**
     * @param {req: \Pike\Request, res: \Pike\Response} $http
     * @return \Auryn\Injector
     */
    private function setupIocContainer($http) {
        $container = !$this->makeInjector
            ? new Injector()
            : call_user_func($this->makeInjector);
        $container->share($http->req);
        $container->share($http->res);
        if (isset($this->ctx->db)) $container->share($this->ctx->db);
        if (isset($this->ctx->auth)) $container->share($this->ctx->auth);
        $container->alias(FileSystemInterface::class, FileSystem::class);
        $container->alias(SessionInterface::class, NativeSession::class);
        foreach ($this->moduleClsPaths as $clsPath) {
            if (method_exists($clsPath, 'alterIoc'))
                call_user_func([$clsPath, 'alterIoc'], $container);
        }
        return $container;
    }
    /**
     * @param callable[] $modules
     * @param string|array $config = null
     * @param object $ctx = null
     * @param fn(): \Auryn\Injector $makeInjector = null
     * @return \Pike\App
     */
    public static function create(array $modules,
                                  $config = null,
                                  $ctx = null,
                                  callable $makeInjector = null) {
        [$config, $ctx] = self::getNormalizedSettings($config, $ctx);
        //
        if (!isset($ctx->router)) {
            $ctx->router = new Router();
            $ctx->router->addMatchTypes(['w' => '[0-9A-Za-z_-]++']);
        }
        if (($ctx->{self::SERVICE_DB} ?? null) === self::MAKE_AUTOMATICALLY) {
            if (!is_array($config))
                throw new PikeException('cant orovide db without config',
                                        PikeException::BAD_INPUT);
            $ctx->db = new Db($config);
        }
        if (($ctx->{self::SERVICE_AUTH} ?? '') === self::MAKE_AUTOMATICALLY) {
            if (!isset($ctx->db))
                throw new PikeException('cant provide auth without db',
                                        PikeException::BAD_INPUT);
            $ctx->auth = new Authenticator(new CachingServicesFactory($ctx->db,
                                                                      new Crypto()));
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
     * @param array|string|null $confix
     * @param object|array $ctx
     * @return array [array|null, object]
     */
    private static function getNormalizedSettings($config, $ctx) {
        if (is_string($config) && strlen($config))
            $config = require $config;
        if (!($ctx instanceof \stdClass)) {
            if (is_array($ctx))
                $ctx = $ctx ? (object)$ctx : new \stdClass;
            elseif ($ctx === null)
                $ctx = new \stdClass;
            else
                throw new PikeException('ctx must be object|array');
        }
        return [$config, $ctx];
    }
}
