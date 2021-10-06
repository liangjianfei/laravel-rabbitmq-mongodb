<?php

namespace App\Service\RabbitMq;

use Illuminate\Support\Facades\Log;

/**
 * MQ命令行处理容器
 */
class MqConsoleContainer
{
    protected $handlerList = []; // 注册的handler列表

    protected $params = []; // 命令行接收的参数

    protected $behavior = [
        'normal' => MqConsoleBehaviorNormal::class, // [-m normal]时启动的behavior
        'retry' => MqConsoleBehaviorRetry::class, // [-m retry]时启动的behavior
        'fail' => MqConsoleBehaviorFail::class, // [-m fail]时启动的behavior
    ];

    protected $mqHelper;

    public $MQInitDataNormal; // 正常队列初始化参数

    public $MQInitDataRetry; // 重试队列初始化参数

    public $MQInitDataFail; // 失败队列初始化参数

    protected $nackTimes = 0; // 当前nack次数

    protected $nackMaxTimes = 10; // 当前进程允许最大nack次数，适用于定时执行，守护执行会导致进程频繁重启

    protected $loopTimes = 10000; // 循环最大次数

    /**
     * 正式运行前准备
     */
    public function __construct()
    {
        // 注册关闭、异常结束前处理方法
        register_shutdown_function([$this, 'onShutdown']);
        // 命令行参数获取
        global $neo;
        $this->getParams();
        Log::info('INFO', ['start:' . THISSCRIPT, $this->params, true]);
        $this->mqHelper = new MQHelper($neo);
    }

    /**
     * 执行退出前执行
     */
    public function onShutdown()
    {
        if ($this->mqHelper) {
            $this->mqHelper->closeMQ();
        }
        Log::info('INFO', ['shutdown:' . THISSCRIPT]);
        byebye();
    }

    /**
     * 获取命令行参数
     * @throws \Exception
     */
    protected function getParams()
    {
        $this->params = getopt('m:n::', ['run::']);
        if (! isset($this->params['m'])) {
            throw new \Exception('参数-m[normal|retry|fail]必填');
        }
        if (! in_array($this->params['m'], array_keys($this->behavior))) {
            throw new \Exception('参数-m[normal|retry|fail]错误');
        }
        if (isset($this->params['n'])) {
            $this->loopTimes = intval($this->params['n']);
            Log::info('INFO', ['设置取消息数量:', $this->loopTimes, true]);
        }
    }

    /**
     * 注册handler
     * @param  string    $class
     * @throws \Exception
     */
    public function register(string $class)
    {
        $obj = new $class();
        if (! $obj instanceof MqHandleInterface) {
            throw new \Exception(sprintf('class: %s not instance of mqHandleInterface', $class));
        }
        $this->handlerList[] = $obj;
    }

    /**
     * 重置行为
     * @param string $type
     * @param string $behavior
     */
    public function resetBehavior(string $type, string $behavior)
    {
        $this->behavior[$type] = $behavior;
    }

    /**
     * 运行主体
     */
    public function run()
    {
        # 参数整理
        $handleData = new MqHandleData();
        $handleData->params = $this->params;

        # 根据参数-m[normal|retry|fail]实例化对应的behavior
        $behavior = new $this->behavior[$this->params['m']](
            $this->mqHelper,
            $this->MQInitDataNormal,
            $this->MQInitDataRetry,
            $this->MQInitDataFail
        );

        # 循环拉取消息后退出
        $i = $this->loopTimes;
        while ($i && $message = $this->mqHelper->getWholeMessage()) {
            --$i;
            $data = $message->body;
            Log::info('INFO', [THISSCRIPT . ':getMessageBody -m:' . $this->params['m'], $data]);
            $data = json_decode($data, true);
            if (! $data) {
                Log::error('ERROR', [THISSCRIPT . ':json_decode mq message error', ['message' => $message->body]]);
                // 确认消息
                $this->mqHelper->ack($message);
            } else {
                # 处理主流程
                $handleData->message = $message;
                $handleData->mqData = $data;
                $handleData->resetResult();
                try {
                    // 调用behavier的running,将handler列表和存储数据的mqHandleData对象传入
                    $params = $this->handlerList;
                    array_unshift($params, $handleData);
                    call_user_func_array([$behavior, 'running'], $params);
                } catch (\Exception $exception) {
                    // 记录日志
                    Log::error('ERROR', [THISSCRIPT . ':checkException-drop:' . $exception->getMessage(), get_object_vars($handleData)]);
                    // 参数验证失败丢弃消息
                    $this->mqHelper->reject($message);
                } catch (\Exception $exception) {
                    // 记录日志
                    Log::error('ERROR', [THISSCRIPT . ':execException-nack:' . $exception->getMessage(), get_object_vars($handleData)]);
                    // 拒绝确认消息
                    $this->mqHelper->nack($message);
                    $this->nackAfter();
                } catch (\Exception $exception) {
                    // 记录日志
                    Log::error('ERROR', [THISSCRIPT . ':Exception-nack:' . $exception->getMessage(), get_object_vars($handleData)]);
                    // 拒绝确认消息
                    $this->mqHelper->nack($message);
                    $this->nackAfter();
                }
            }
        }
    }

    /**
     * nack后的处理
     */
    protected function nackAfter()
    {
        ++$this->nackTimes;
        if ($this->nackTimes >= $this->nackMaxTimes) {
            Log::error('ERROR', [THISSCRIPT . ':nack_too_many_times:nack_times > nack_max_times process exit.']);
            exit();
        }
    }
}
