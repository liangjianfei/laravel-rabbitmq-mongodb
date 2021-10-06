<?php

namespace App\Service\RabbitMq;

/**
 * 回调接口
 */
interface MqHandleInterface
{
    public function callback(mqHandleData &$handleData);
}
