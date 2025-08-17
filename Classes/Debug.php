<?php

class Debug {
    public const DEBUG_LEVEL_NONE     = 0;
    public const DEBUG_LEVEL_INFO     = 1;
    public const DEBUG_LEVEL_WARNING  = 2;
    public const DEBUG_LEVEL_ERROR    = 4;
    public const DEBUG_LEVEL_CRITICAL = 8;
    public const DEBUG_LEVEL_ALL      = 15;
    public const DEBUG_QUERIES_NONE   = 0;
    public const DEBUG_QUERIES_LIMIT  = 1;
    public const DEBUG_QUERIES_ALWAYS = 2;
    
    public static int    $debugLevel           = self::DEBUG_LEVEL_ALL;
    public static int    $debugQueryLevel      = self::DEBUG_QUERIES_ALWAYS;
    private static int   $dump_max_depth       = 10;
    private static int   $dump_max_array_depth = 100;
    private static array $timers               = [];
    private static array $memory_points        = [];
    private static array $sql_queries          = [];
    
    // region Dumps
    public static function dd(...$vars) : void {
        echo '<pre style="text-align: left; box-sizing: border-box; min-width:100%; width:fit-content; background-color: #1e1e1e; color: #fff; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 14px; white-space: pre-wrap; display: flex; flex-direction: column">';
        foreach ($vars as $var) {
            echo self::formatValue($var);
        }
        echo '</pre>';
        die();
    }
    
    public static function dump(...$vars) : void {
        echo '<pre style="text-align: left; box-sizing: border-box; min-width:100%; width:fit-content; background-color: #1e1e1e; color: #fff; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 14px; white-space: pre-wrap; display: flex; flex-direction: column">';
        foreach ($vars as $var) {
            echo self::formatValue($var);
        }
        echo '</pre>';
    }
    
    private static function formatValue(mixed $var, bool $is_array_key = false, int $depth = 0) : string {
        $colors = [
            "default" => "#FF8400", "string" => "#56DB3A", "number" => "#1299DA", "array" => "#1299DA",
        ];
        $indentation = 2;
        $max_depth = self::$dump_max_depth;
        $max_array_depth = self::$dump_max_array_depth;
        
        if (is_null($var)) {
            $style = "color:" . $colors['default'] . ";";
            $style .= !$is_array_key ? 'font-weight:bold;' : '';
            return "<span style='" . $style . "'>NULL</span>";
        } elseif (is_string($var)) {
            $style = "color:" . $colors['string'] . ";";
            $style .= !$is_array_key ? 'font-weight:bold;' : '';
            return "<span style='color:" . $colors['default'] . "'>\"<span style='" . $style . "'>" . htmlspecialchars($var) . "</span>\"</span>";
        } elseif (is_int($var) || is_float($var)) {
            $style = "color:" . $colors['number'] . ";";
            $style .= !$is_array_key ? 'font-weight:bold;' : '';
            return "<span style='" . $style . "'>$var</span>";
        } elseif (is_bool($var)) {
            $style = "color:" . $colors['default'] . ";";
            $style .= !$is_array_key ? 'font-weight:bold;' : '';
            
            $value = $var ? 'true' : 'false';
            return "<span style='" . $style . "'>" . $value . "</span>";
        } elseif (is_array($var) || is_object($var)) {
            $label = is_array($var) ? 'array:' . count($var) : 'object:' . get_class($var);
            $var = is_array($var) ? $var : (array)$var;
            
            $style = "color:" . $colors['array'] . ";";
            
            $result = "<span style='color:" . $colors['default'] . "'><span style='" . $style . "'>" . $label . "</span> [</span>";
            $result .= "<span style='display: flex; flex-direction: column;'>";
            if ($depth <= $max_depth) {
                $count = 0;
                foreach ($var as $key => $value) {
                    if ($count >= $max_array_depth) {
                        $result .= "<span style='color:" . $colors['default'] . ";margin-left: " . $indentation . "rem;'>...+" . (count($var) - $max_array_depth) . "</span>";
                        break;
                    } else {
                        $result .= "<span style='color:" . $colors['default'] . ";margin-left: " . $indentation . "rem;'>" . self::formatValue($key, true, $depth + 1) . " => " . self::formatValue($value, false, $depth + 1) . "</span>";
                        $count++;
                    }
                }
            } else {
                $result .= "<span style='color:" . $colors['default'] . ";margin-left: " . $indentation . "rem;'>...profondità massima raggiunta</span>";
            }
            $result .= "<span style='color:" . $colors['default'] . "'>]</span></span>";
            return $result;
        } else {
            $style = "color:" . $colors['default'] . ";";
            $style .= !$is_array_key ? 'font-weight:bold;' : '';
            return "<span style='" . $style . "'>(unknown)</span>";
        }
    }
    // endregion
    
    // region Logs
    public static function info(string $message, bool $backtrace = false) : void {
        if (self::$debugLevel & self::DEBUG_LEVEL_INFO) {
            echo $message;
            self::log($message, "INFO", $backtrace);
        }
    }
    
    public static function warning(string $message, bool $backtrace = false) : void {
        if (self::$debugLevel & self::DEBUG_LEVEL_WARNING) {
            echo $message;
            self::log($message, "WARNING", $backtrace);
        }
    }
    
    public static function error(string $message, bool $backtrace = false) : void {
        if (self::$debugLevel & self::DEBUG_LEVEL_ERROR) {
            echo $message;
            self::log($message, "ERROR", $backtrace);
        }
    }
    
    public static function critical(string $message, bool $backtrace = false) : void {
        if (self::$debugLevel & self::DEBUG_LEVEL_CRITICAL) {
            self::log($message, "CRITICAL", $backtrace);
        }
    }
    
    public static function logQuery($query) {
        if ((Debug::$debugQueryLevel & 1 && Debug::getElapsedTimes($query) > 4) || (Debug::$debugQueryLevel & 2)) {
            echo var_export(Debug::getQueries($query), true);
            self::log(var_export(Debug::getQueries($query), true), "QUERY");
        }
    }
    
    public static function log(string $message, string $level = 'INFO', bool $backtrace = false) : void {
        error_log("[{$level}] {$message}");
        if ($backtrace) {
            error_log(Debug::backtrace());
        }
    }
    
    public static function backtrace() : string {
        $trace = "[PHP Backtrace]:\n";
        foreach ($debug_backtrace = array_reverse(debug_backtrace()) as $key => $value) {
            if (count($debug_backtrace) == $key + 1) {
                continue;
            }
            $file = $value['file'];
            $line = $value['line'];
            $class = $value['class'] ?? '';
            $type = $value['type'] ?? '';
            $function = $value['function'] ?? '';
            $args = '';
            foreach ($value['args'] as $arg) {
                $args .= "{$arg}, ";
            }
            $args = rtrim($args, ", ");
            $trace .= "{$key}) {$file}({$line}): {$class}{$type}{$function}({$args})\n";
        }
        return trim($trace);
    }
    
    public static function setDebugLevel(int $level) : void {
        self::$debugLevel = $level;
    }
    
    public static function setDebugQueryLevel(int $level) : void {
        self::$debugQueryLevel = $level;
    }
    // endregion
    
    // region Queries
    public static function setQuery(string $query, array $binding = []) : void {
        self::$sql_queries[$query] = [
            'query' => $query,
            'binding' => $binding,
            'executionTime' => Debug::getElapsedTimes($query),
            'memoryUsage' => Debug::getMemoryDiff(($query . "-start"), ($query . "-end")),
            'timestamp'   => date('Y-m-d H:i:s')
        ];
    }
    
    public static function getQueries($query = null) : array {
        if (empty($query)) {
            return self::$sql_queries;
        } else {
            return self::$sql_queries[$query];
        }
    }
    
    public static function getQueriesInfo() : array {
        $info = [
            'count' => count(self::$sql_queries), 'total_time' => 0, 'total_memory' => 0, 'queries' => []
        ];
        
        foreach (self::$sql_queries as $query) {
            $info['total_time'] += $query['execution_time'];
            $info['total_memory'] += $query['memory_usage'];
            $info['queries'][] = [
                'query'  => $query['query'], 'time' => number_format($query['execution_time'] * 1000, 2) . ' ms',
                'memory' => self::formatBytes($query['memory_usage'])
            ];
        }
        
        $info['total_time'] = number_format($info['total_time'] * 1000, 2) . ' ms';
        $info['total_memory'] = self::formatBytes($info['total_memory']);
        
        return $info;
    }
    // endregion
    
    // region Timers
    public static function startTimer(string $label) : void {
        self::$timers[$label]['start'] = microtime(true);
        self::$timers[$label]['end'] = null;
    }
    
    public static function endTimer(string $label) : void {
        if (isset(self::$timers[$label]['start']) && self::$timers[$label]['end'] === null) {
            self::$timers[$label]['end'] = microtime(true);
        }
    }
    
    public static function getElapsedTimes(string $label = null, int $precision = 4) : string|array|null {
        if (empty($label)) {
            $results = [];
            foreach (self::$timers as $label => $data) {
                if (isset($data['start']) && isset($data['end'])) {
                    $results[$label] = number_format($data['end'] - $data['start'], $precision);
                }
            }
            return $results;
        } else {
            if (isset(self::$timers[$label]['start']) && isset(self::$timers[$label]['end'])) {
                $elapsed = self::$timers[$label]['end'] - self::$timers[$label]['start'];
                return number_format($elapsed, $precision);
            }
            return null;
        }
    }
    // endregion
    
    // region Memory
    public static function memoryCheckpoint(string $label) : void {
        self::$memory_points[$label] = memory_get_usage(true);
    }
    
    public static function getMemoryUsage(string $label) : ?string {
        if (isset(self::$memory_points[$label])) {
            return self::formatBytes(self::$memory_points[$label]);
        }
        return null;
    }
    
    public static function getAllMemoryUsage() : ?array {
        $results = [];
        foreach (self::$memory_points as $label => $data) {
            if (isset($data)) {
                $results[$label] = self::formatBytes($data);
            }
        }
        return $results;
    }
    
    public static function getMemoryDiff(string $start, string $end) : ?string {
        if (isset(self::$memory_points[$start]) && isset(self::$memory_points[$end])) {
            $diff = self::$memory_points[$end] - self::$memory_points[$start];
            return self::formatBytes($diff);
        }
        return null;
    }
    
    private static function formatBytes(int $bytes, int $precision = 2) : string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    // endregion
    
}