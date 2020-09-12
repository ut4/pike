<?php

declare(strict_types=1);

namespace Pike\Auth;

use Pike\PikeException;

class ACL {
    public const ROLE_SUPER_ADMIN = 1 << 0;
    public const ROLE_EDITOR      = 1 << 1;
    public const ROLE_AUTHOR      = 1 << 2;
    public const ROLE_CONTRIBUTOR = 1 << 3;
    // Suurin bit-maskaukseen soveltuva 24-bittinen/mediumint kokonaisluku
    public const ROLE_LAST        = 1 << 23;
    public const ROLE_VIEWER      = self::ROLE_LAST;
    public const NO_PERMISSIONS   = 0;
    /** @var ?object */
    protected $resources;
    /** @var ?object */
    protected $permissions;
    /** @var ?object */
    protected $compactRules;
    /** @var bool */
    protected $doThrowDevWarnings;
    /**
     * @param bool $doThrowDevWarnings = false
     */
    public function __construct(bool $doThrowDevWarnings = false) {
        $this->doThrowDevWarnings = $doThrowDevWarnings;
    }
    /**
     * Esimerkki: (object)[
     *     'resources' => (object)[
     *         'content' => (object)['create' => 1 << 1, 'edit' => 1 << 2],
     *         'plugins' => (object)['install' => 1 << 1],
     *     },
     *     'userPermissions' => (object)[
     *         ACL::ROLE_FOO: (object)[
     *             'content' => (1 << 1) | (1 << 2),
     *             'plugins' => 0,
     *         ],
     *         ACL::ROLE_BAR: (object)[
     *             // Kiellä kaikki
     *         ]
     *     ]
     * ]
     *
     * @param \stdClass $compactRules {resources: \stdClass, userPermissions: \stdClass}
     */
    public function setRules(\stdClass $compactRules): void {
        $this->compactRules = $compactRules;
    }
    /**
     * @param int $role
     * @param string $action
     * @param string $resource
     * @return bool
     * @throw \Pike\PikeException
     */
    public function can(int $role, string $action, string $resource): bool {
        if ($role === self::ROLE_SUPER_ADMIN)
            return true;
        if (!$this->resources)
            // @allow \Pike\PikeException
            $this->parseAndLoadRules();
        $resourceRules = $this->resources->$resource ?? null;
        $userPermissions = $this->permissions->{$role} ?? null;
        if (!$resourceRules && $this->doThrowDevWarnings)
            throw new PikeException("Resource `{$resource}` doesn\'t exist",
                                    PikeException::BAD_INPUT);
        if (!$resourceRules || !$userPermissions)
            return false;
        $flags = $userPermissions->$resource ?? 0;
        $flag = $resourceRules->$action ?? null;
        if ($flag === null && $this->doThrowDevWarnings)
            throw new PikeException("Resource `{$resource}` has no action `{$action}`",
                                    PikeException::BAD_INPUT);
        if (!$flags || !$flag)
            return false;
        return (bool)($flags & $flag);
    }
    /**
     * @throws \Pike\PikeException
     */
    protected function parseAndLoadRules(): void {
        if (!(($this->compactRules->resources ?? null) instanceof \stdClass))
            throw new PikeException('rules->resources must be a \stdClass',
                                    PikeException::BAD_INPUT);
        if (!(($this->compactRules->userPermissions ?? null) instanceof \stdClass))
            throw new PikeException('rules->userPermissions must be a \stdClass',
                                    PikeException::BAD_INPUT);
        $this->resources = $this->compactRules->resources;
        $this->permissions = $this->compactRules->userPermissions;
    }
    /**
     * @param string[]|string $allowedActions
     * @param \stdClass $resourceActions
     * @return int
     * @throws \Pike\PikeException
     */
    public static function makePermissions($allowedActions,
                                           \stdClass $resourceActions): int {
        $flags = 0;
        if ($allowedActions !== '*') {
            foreach ($allowedActions as $actionName) {
                if (($flag = ($resourceActions->$actionName ?? null)))
                    $flags |= $flag;
                else
                    throw new PikeException("`{$actionName}` not found, available: " .
                                            implode(', ', array_keys((array) $resourceActions)),
                                            PikeException::BAD_INPUT);
            }
        } else {
            foreach ($resourceActions as $flag)
                $flags |= $flag;
        }
        return $flags;
    }
}
