<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class Db extends DbCore
{
    protected static $psoftDebugbarCollect = false;
    protected static $psoftDebugbarQueries = array();
    protected static $psoftDebugbarTotal = 0;
    protected static $psoftDebugbarLimit = 100;

    public static function startPsoftDebugbarCollection($limit = 100)
    {
        self::$psoftDebugbarCollect = true;
        self::$psoftDebugbarQueries = array();
        self::$psoftDebugbarTotal = 0;
        self::$psoftDebugbarLimit = max(10, min(300, (int) $limit));
    }

    public static function getPsoftDebugbarCollection()
    {
        return array(
            'queries' => self::$psoftDebugbarQueries,
            'total' => self::$psoftDebugbarTotal,
            'truncated' => max(0, self::$psoftDebugbarTotal - count(self::$psoftDebugbarQueries)),
        );
    }

    public function query($sql)
    {
        if (!self::$psoftDebugbarCollect) {
            return parent::query($sql);
        }

        $sqlText = $sql instanceof DbQuery ? (string) $sql : (string) $sql;
        $startedAt = microtime(true);

        try {
            return parent::query($sql);
        } finally {
            $duration = (microtime(true) - $startedAt) * 1000;
            ++self::$psoftDebugbarTotal;

            if (count(self::$psoftDebugbarQueries) < self::$psoftDebugbarLimit) {
                self::$psoftDebugbarQueries[] = array(
                    'sql' => trim($sqlText),
                    'duration' => number_format($duration, 2, '.', ''),
                    'slow' => $duration >= 100,
                );
            }
        }
    }
}
