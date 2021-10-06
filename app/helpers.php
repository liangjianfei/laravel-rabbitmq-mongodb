<?php
/**
 * 页面结束处理
 */
function byebye()
{
    // broken if zlib.output_compression is on with Apache 2
    if (php_sapi_name() != 'apache2handler' && php_sapi_name() != 'apache2filter') {
        flush();
    }

    exit();
}

/**
 * 记录和统计时间（微秒）和内存使用情况
 * 使用方法:
 * <code>
 * timeOrMemUsed('begin'); // 记录开始标记位
 * // ... 区间运行代码
 * timeOrMemUsed('end'); // 记录结束标签位
 * echo timeOrMemUsed('begin','end',6); // 统计区间运行时间 精确到小数后6位
 * echo timeOrMemUsed('begin','end','m'); // 统计区间内存使用情况
 * 如果end标记位没有定义，则会自动以当前作为标记位
 * 其中统计内存使用需要 MEMORY_LIMIT_ON 常量为true才有效
 * </code>.
 *
 * @param string     $start 开始标签
 * @param string     $end   结束标签
 * @param int|string $dec   小数位或者m
 *
 * @return mixed
 */
function timeOrMemUsed($start, $end = '', $dec = 4)
{
    static $_info = [];
    static $_mem = [];
    if (is_float($end)) { // 记录时间
        $_info[$start] = $end;
    } elseif (! empty($end)) { // 统计时间和内存使用
        if (! isset($_info[$end])) {
            $_info[$end] = microtime(true);
        }
        if ($dec == 'm') {
            if (! isset($_mem[$end])) {
                $_mem[$end] = memory_get_usage();
            }

            return number_format(($_mem[$end] - $_mem[$start]) / 1024);
        }

        return number_format(($_info[$end] - $_info[$start]), $dec);
    } else { // 记录时间和内存使用
        $_info[$start] = microtime(true);
        $_mem[$start] = memory_get_usage();
    }

    return null;
}
