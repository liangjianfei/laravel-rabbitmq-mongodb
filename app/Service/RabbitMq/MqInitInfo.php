<?php

namespace App\Service\RabbitMq;

/**
 * 队列初始化参数
 */
class MqInitInfo
{
    public $server; // 服务配置名

    public $exchangeName; // 交换机名称

    public $exchangeType; // 交换机类型

    public $queueName; // 队列名称

    public $routingKey; // 路由key

    public function __construct(
        $server = '',
        $exchangeName = '',
        $exchangeType = '',
        $queueName = '',
        $routingKey = ''
    ) {
        $this->server = $server;
        $this->exchangeName = $exchangeName;
        $this->exchangeType = $exchangeType;
        $this->queueName = $queueName;
        $this->routingKey = $routingKey;
    }
}
