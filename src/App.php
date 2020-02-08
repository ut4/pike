<?php

namespace Pike;

use AltoRouter;
use Auryn\Injector;
use Pike\Auth\Authenticator;
use Pike\Auth\Crypto;
use Pike\Auth\Internal\CachingServicesFactory;

final class App {
    private $ctx;
    private $moduleClsPaths;
    /**
     * @param \stdClass $ctx
     * @param string[] $modules
     */
    public function __construct(\stdClass $ctx, array $modules) {
        $this->ctx = $ctx;
        $this->moduleClsPaths = $modules;
    }
    /**
     * RadCMS:n entry-point.
     *
     * @param \Pike\Request|string $request
     * @param string|\Auryn\Injector ...$args
     */
    public function handleRequest($request, ...$args) {
        if (is_string($request))
            $request = Request::createFromGlobals($request, $args[0] ?? null);
        if (($match = $this->ctx->router->match($request->path, $request->method))) {
            $request->params = (object)$match['params'];
            // @allow \Pike\PikeException
            [$ctrlClassPath, $ctrlMethodName, $requireAuth] =
                $this->validateRouteMatch($match, $request);
            $request->user = $this->ctx->auth->getIdentity();
            if ($requireAuth && !$request->user) {
                (new Response(403))->json(['err' => 'Login required']);
            } else {
                $injector = $this->setupIocContainer(array_pop($args), $request);
                $injector->execute($ctrlClassPath . '::' . $ctrlMethodName);
            }
        } else {
            throw new PikeException("No route for {$request->method} {$request->path}");
        }
    }
    /**
     * @param array $match
     * @return array [string, string, bool]
     * @throws \Pike\PikeException
     */
    private function validateRouteMatch($match, $req) {
        $routeInfo = $match['target'];
        if (!is_array($routeInfo) ||
            count($routeInfo) !== 3 ||
            !is_string($routeInfo[0]) ||
            !is_string($routeInfo[1]) ||
            !is_bool($routeInfo[2])) {
            throw new PikeException(
                'A route (' . $req->method . ' ' . $req->path . ') must be an array ' .
                '[\'Ctrl\\Class\\Path\', \'methodName\', \'requireAuth\' ? true : false].',
                PikeException::BAD_INPUT);
        }
        return $routeInfo;
    }
    /**
     * @param \Auryn\Injector|string $candidate
     * @param \Pike\Request $request
     * @return \Auryn\Injector
     */
    private function setupIocContainer($candidate, $request) {
        $container = !($candidate instanceof Injector) ? new Injector() : $candidate;
        $container->share($this->ctx->db);
        $container->share($this->ctx->auth);
        $container->share($request);
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
     */
    public static function create(array $modules, $config = null, $ctx = null) {
        [$config, $ctx] = self::normalizeConfig($config, $ctx);
        //
        if (!isset($ctx->db))
            $ctx->db = new Db($config);
        if (!isset($ctx->router)) {
            $ctx->router = new AltoRouter();
            $ctx->router->addMatchTypes(['w' => '[0-9A-Za-z_-]++']);
        }
        if (!isset($ctx->auth)) {
            $ctx->auth = new Authenticator(new CachingServicesFactory($ctx->db,
                                                                      new Crypto()));
        }
        //
        foreach ($modules as $clsPath) {
            if (!method_exists($clsPath, 'init'))
                throw new PikeException('Module must have init() -method',
                                        PikeException::BAD_INPUT);
            call_user_func([$clsPath, 'init'], $ctx);
        }
        //
        return new static($ctx, $modules);
    }
    /**
     * @return array [array, object]
     */
    private static function normalizeConfig($config, $ctx) {
        if (is_string($config))
            $config = require $config;
        if (!$ctx) {
            if (!is_array($config))
                throw new PikeException('Can\'t make db without config',
                                        PikeException::BAD_INPUT);
            $ctx = (object)['db' => null, 'router' => null, 'auth' => null];
        }
        return [$config, $ctx];
    }
}
