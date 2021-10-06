<?php

namespace App\Service\RabbitMq;

class MqConsoleBehaviorNormal implements MqConsoleBehaviorInterface
{
    protected $mqHelper;

    protected $normalInfo;

    protected $retryInfo;

    public function __construct(MQHelper &$mqhelper, MqInitInfo ...$mqInitInfoArr)
    {
        $this->mqHelper = $mqhelper;
        $this->normalInfo = $mqInitInfoArr[0];
        $this->retryInfo = $mqInitInfoArr[1];
        $routingKey = $this->normalInfo->exchangeType == 'fanout' ? '' : $this->normalInfo->routingKey;
        // 支持绑定多个扇形交换机的情况
        $this->normalInfo->exchangeName = is_array($this->normalInfo->exchangeName) ? $this->normalInfo->exchangeName : [$this->normalInfo->exchangeName];
        // 创建正常队列并绑定交换机
        $this->mqHelper->connectMQ($this->normalInfo->exchangeName[0], $this->normalInfo->queueName, $this->normalInfo->server);
        $this->mqHelper->mqChannel->queue_declare($this->normalInfo->queueName, false, true, false, false, false);
        // 遍历交换机进行绑定
        foreach ($this->normalInfo->exchangeName as $exchangeName) {
            $this->mqHelper->mqChannel->exchange_declare($exchangeName, $this->normalInfo->exchangeType, false, true, false);
            $this->mqHelper->mqChannel->queue_bind($this->normalInfo->queueName, $exchangeName, $routingKey);
        }
        // 创建重试+延迟消费的交换机和队列
        $this->mqHelper->createDelayExchangeAndQueue($this->retryInfo->exchangeName, $this->retryInfo->queueName, $this->normalInfo->routingKey);
    }

    public function running(MqHandleData $handleData, MqHandleInterface ...$handlerArr)
    {
        // 回调方法中执行
        foreach ($handlerArr as $handler) {
            $handler->callback($handleData);
        }

        // 执行结果为false时，执行错误处理
        if (! $handleData->getResult()) {
            $this->onFail($handleData);
        }

        // 上面步骤未出现异常，把消息ack掉
        $this->mqHelper->ack($handleData->message);
    }

    public function onFail(mqHandleData $handleData ,MQHelper $MQHelper)
    {
        // 推送retry消息
        $delayedRouteKey = $this->mqHelper->getDelayedRouteKey($this->normalInfo->routingKey);
        $this->mqHelper->mqChannel->basic_publish(
            $handleData->msgReset ? $handleData->newMsgFromData() : $handleData->message,
            $this->retryInfo->exchangeName,
            $delayedRouteKey
        );
    }
}
