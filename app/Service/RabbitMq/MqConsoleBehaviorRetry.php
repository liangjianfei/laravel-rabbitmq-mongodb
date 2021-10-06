<?php

namespace App\Service\RabbitMq;

use Illuminate\Support\Facades\Log;

class MqConsoleBehaviorRetry implements MqConsoleBehaviorInterface
{
    protected $mqHelper;

    protected $normalInfo;

    protected $retryInfo;

    protected $failInfo;

    public function __construct(MQHelper &$mqhelper, MqInitInfo ...$mqInitInfoArr)
    {
        $this->mqHelper = $mqhelper;
        $this->normalInfo = $mqInitInfoArr[0];
        $this->retryInfo = $mqInitInfoArr[1];
        $this->failInfo = $mqInitInfoArr[2];
        // 创建重试队列，此处不绑定交换机，因重试队列绑定的时延时交换机，依赖mqConsoleBehaviorNormal中的绑定
        $this->mqHelper->connectMQ($this->retryInfo->exchangeName, $this->retryInfo->queueName, $this->retryInfo->server);
        $this->mqHelper->mqChannel->queue_declare($this->normalInfo->queueName, false, true, false, false, false);
        // 创建失败队列，并绑定交换机（配置中可指定共用正常交换机）
        $this->mqHelper->mqChannel->exchange_declare($this->failInfo->exchangeName, $this->failInfo->exchangeType, false, true, false);
        $this->mqHelper->mqChannel->queue_declare($this->failInfo->queueName, false, true, false, false, false);
        $this->mqHelper->mqChannel->queue_bind($this->failInfo->queueName, $this->failInfo->exchangeName, $this->failInfo->routingKey);
    }

    public function running(MqHandleData $handleData, MqHandleInterface ...$handlerArr)
    {
        // 回调方法中执行
        foreach ($handlerArr as $handler) {
            $handler->callback($handleData);
        }

        // 执行结果为false时，执行错误处理
        if (! $handleData->getResult()) {
            // 获取消息header，从header中获取重试次数
            $applicationHeaders = MQHelper::getMessageAppHeaders($handleData->message);
            $applicationHeaders['retry_num'] = $applicationHeaders['retry_num'] ?? 1;
            Log::info('INFO', [THISSCRIPT . ':applicationHeaders', $applicationHeaders]);

            // 超出重试次数则推入失败队列
            if ($applicationHeaders['retry_num'] >= 3) {
                $this->onFail($handleData);
            } else {
                $this->sendRetry($handleData, $applicationHeaders);
            }
        }

        // 上面步骤未出现异常，把消息ack掉
        $this->mqHelper->ack($handleData->message);
    }

    public function onFail(MqHandleData $handleData ,MQHelper $MQHelper)
    {
        // 消息推送进失败队列
        $MQHelper->pushDataToMQ(
            $handleData->mqData,
            $this->failInfo->exchangeName,
            $this->failInfo->queueName,
            $this->failInfo->routingKey,
            $this->failInfo->server
        );
    }

    /**
     * 发送重试消息
     * @param MqHandleData $handleData
     * @param array        $applicationHeaders
     */
    public function sendRetry(MqHandleData $handleData, array $applicationHeaders)
    {
        Log::info('INFO', [THISSCRIPT . ':retry_data', $handleData->mqData]);
        ++$applicationHeaders['retry_num'];
        MQHelper::setMessageHeaders($handleData->message, ['application_headers' => $applicationHeaders]);
        // 推送至retry 队列(延时消费);延迟routing_key用正常routing_key生成
        $delayedRouteKey = $this->mqHelper->getDelayedRouteKey($this->normalInfo->routingKey);
        $this->mqHelper->mqChannel->basic_publish($handleData->message, $this->retryInfo->exchangeName, $delayedRouteKey);
    }
}
