<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Hook extends HookCore
{
    protected static $psoftDebugbarHookCollect = false;
    protected static $psoftDebugbarHookTimings = array();
    protected static $psoftDebugbarActiveHooks = array();
    protected static $psoftDebugbarPendingHook = null;

    public static function startPsoftDebugbarHookCollection($currentHook = null)
    {
        self::$psoftDebugbarHookCollect = true;
        self::$psoftDebugbarHookTimings = array();
        self::$psoftDebugbarActiveHooks = array();
        self::$psoftDebugbarPendingHook = $currentHook ? array(
            'name' => (string) $currentHook,
            'started_at' => microtime(true),
        ) : null;
    }

    public static function getPsoftDebugbarHookCollection()
    {
        $timings = self::$psoftDebugbarHookTimings;
        $now = microtime(true);

        foreach (self::$psoftDebugbarActiveHooks as $activeHook) {
            self::addPsoftDebugbarHookTiming(
                $timings,
                $activeHook['name'],
                ($now - $activeHook['started_at']) * 1000
            );
        }

        return $timings;
    }

    public static function exec(
        $hook_name,
        $hook_args = array(),
        $id_module = null,
        $array_return = false,
        $check_exceptions = true,
        $use_push = false,
        $id_shop = null,
        $chain = false
    ) {
        if (!self::$psoftDebugbarHookCollect
            && strcasecmp((string) $hook_name, 'actionDispatcher') !== 0) {
            return self::callPsoftDebugbarParentExec(
                $hook_name,
                $hook_args,
                $id_module,
                $array_return,
                $check_exceptions,
                $use_push,
                $id_shop,
                $chain
            );
        }

        if (!self::$psoftDebugbarHookCollect) {
            try {
                return self::callPsoftDebugbarParentExec(
                    $hook_name,
                    $hook_args,
                    $id_module,
                    $array_return,
                    $check_exceptions,
                    $use_push,
                    $id_shop,
                    $chain
                );
            } finally {
                self::finishPsoftDebugbarPendingHook($hook_name);
            }
        }

        $startedAt = microtime(true);
        self::$psoftDebugbarActiveHooks[] = array(
            'name' => (string) $hook_name,
            'started_at' => $startedAt,
        );

        try {
            return self::callPsoftDebugbarParentExec(
                $hook_name,
                $hook_args,
                $id_module,
                $array_return,
                $check_exceptions,
                $use_push,
                $id_shop,
                $chain
            );
        } finally {
            array_pop(self::$psoftDebugbarActiveHooks);
            self::addPsoftDebugbarHookTiming(
                self::$psoftDebugbarHookTimings,
                $hook_name,
                (microtime(true) - $startedAt) * 1000
            );
        }
    }

    protected static function callPsoftDebugbarParentExec(
        $hookName,
        $hookArgs,
        $idModule,
        $arrayReturn,
        $checkExceptions,
        $usePush,
        $idShop,
        $chain
    ) {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return parent::exec(
                $hookName,
                $hookArgs,
                $idModule,
                $arrayReturn,
                $checkExceptions,
                $usePush,
                $idShop,
                $chain
            );
        }

        return parent::exec(
            $hookName,
            $hookArgs,
            $idModule,
            $arrayReturn,
            $checkExceptions,
            $usePush,
            $idShop
        );
    }

    protected static function finishPsoftDebugbarPendingHook($hookName)
    {
        if (!self::$psoftDebugbarHookCollect
            || !is_array(self::$psoftDebugbarPendingHook)
            || self::$psoftDebugbarPendingHook['name'] !== (string) $hookName) {
            return;
        }

        self::addPsoftDebugbarHookTiming(
            self::$psoftDebugbarHookTimings,
            $hookName,
            (microtime(true) - self::$psoftDebugbarPendingHook['started_at']) * 1000
        );
        self::$psoftDebugbarPendingHook = null;
    }

    protected static function addPsoftDebugbarHookTiming(&$timings, $hookName, $duration)
    {
        $hookName = (string) $hookName;
        if (!isset($timings[$hookName])) {
            $timings[$hookName] = array(
                'duration' => 0.0,
                'calls' => 0,
            );
        }

        $timings[$hookName]['duration'] += max(0, (float) $duration);
        ++$timings[$hookName]['calls'];
    }
}
