<?php

namespace App\Service\RabbitMq;

use Illuminate\Support\Facades\Log;

class MqConsoleBehaviorFail implements MqConsoleBehaviorInterface
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
        // 创建失败队列并绑定交换机
        $this->mqHelper->connectMQ($this->failInfo->exchangeName, $this->failInfo->queueName, $this->failInfo->server);
        $this->mqHelper->mqChannel->exchange_declare($this->failInfo->exchangeName, $this->failInfo->exchangeType, false, true, false);
        $this->mqHelper->mqChannel->queue_declare($this->failInfo->queueName, false, true, false, false, false);
        $this->mqHelper->mqChannel->queue_bind($this->failInfo->queueName, $this->failInfo->exchangeName, $this->failInfo->routingKey);
    }

    public function running(MqHandleData $handleData, MqHandleInterface ...$handlerArr)
    {
        // [--run=retry]时将错误队列的数据放入重试队列
        if ($handleData->params['run'] == 'retry') {
            $this->sendRetry($handleData);
            // 上面步骤未出现异常，把消息ack掉
            $this->mqHelper->ack($handleData->message);
        }
    }

    /**
     * 将重试次数设为0，重新放入重试队列
     * @param MqHandleData $handleData
     */
    public function sendRetry(MqHandleData $handleData)
    {
        Log::info('INFO', [THISSCRIPT . ':fail_retry_data', $handleData->mqData]);
        $applicationHeaders = MQHelper::getMessageAppHeaders($handleData->message);
        $applicationHeaders['retry_num'] = 0;
        MQHelper::setMessageHeaders($handleData->message, ['application_headers' => $applicationHeaders]);
        // 推送至retry 队列(延时消费);延迟routing_key用正常routing_key生成
        $delayedRouteKey = $this->mqHelper->getDelayedRouteKey($this->normalInfo->routingKey);
        $this->mqHelper->mqChannel->basic_publish($handleData->message, $this->retryInfo->exchangeName, $delayedRouteKey);
    }

    /**
     * 将重试失败队列推送到失败队列
     * @param mqHandleData $handleData
     * @param MQHelper $MQHelper
     * @throws \Exception
     */
    public function onFail(mqHandleData $handleData, MQHelper $MQHelper)
    {
        // 消息推送回进失败队列
        $MQHelper->pushDataToMQ(
            $handleData->mqData,
            $this->failInfo->exchangeName,
            $this->failInfo->queueName,
            $this->failInfo->routingKey,
            $this->failInfo->server
        );
    }
}
