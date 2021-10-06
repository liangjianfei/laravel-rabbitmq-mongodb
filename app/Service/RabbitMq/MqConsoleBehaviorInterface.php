<?php

namespace App\Service\RabbitMq;

/**
 * 不同类型消息处理接口
 */
interface MqConsoleBehaviorInterface
{
    /**
     * 主要用于初始化mq
     * mqConsoleBehaviorInterface constructor.
     * @param MQHelper   $mqhelper
     * @param MqInitInfo ...$mqInitInfoArr
     */
    public function __construct(MQHelper &$mqhelper, MqInitInfo ...$mqInitInfoArr);

    /**
     * 行为运行方法
     * @param  MqHandleData      $handleData
     * @param  MqHandleInterface ...$handlerArr
     * @return mixed
     */
    public function running(MqHandleData $handleData, MqHandleInterface ...$handlerArr);

    /**
     * 失败处理
     * @param MqHandleData $handleData
     * @param MQHelper $MQHelper
     * @return mixed
     */
    public function onFail(MqHandleData $handleData, MQHelper $MQHelper);
}
