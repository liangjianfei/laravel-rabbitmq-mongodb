<?php

namespace App\Service\RabbitMq;

/**
 * 回调接口通用实现
 */
class MqHandleComm implements MqHandleInterface
{
    public function callback(mqHandleData &$handleData){}
}
