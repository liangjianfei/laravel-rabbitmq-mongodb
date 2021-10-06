<?php

namespace App\Service\RabbitMq;

use PhpAmqpLib\Message\AMQPMessage;
/**
 * 回调参数
 */
class MqHandleData
{
    public $params; // 命令行参数

    public $message; // mq原始消息

    public $mqData; // mq消息，解析为数组

    public $msgReset = false; // 是否通过mqData重新构造MQ消息对象

    private $result = true; // handler的执行结果

    /**
     * 重制result
     */
    public function resetResult()
    {
        $this->result = true;
        $this->msgReset = false;
    }

    /**
     * result获取方法，初始化时为null,仍未null判定为false
     * @return bool
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * 设置执行结果（保存错误状态）
     * @param bool $res
     */
    public function setResult(bool $res)
    {
        if ($this->result) {
            $this->result = $res;
        }
    }

    /**
     * 根据指定的键值将mqData进行过滤重制
     * @param array $keys
     * @return array
     */
    public function dataFilter(array $keys)
    {
        in_array('uniqueCode', $keys) || $keys[] = 'uniqueCode';
        $this->mqData = array_intersect_key($this->mqData, array_flip($keys));
        return $this->mqData;
    }

    /**
     * 通过mqData重新构造MQ消息对象
     * @return AMQPMessage
     */
    public function newMsgFromData($takeOldHeader = true)
    {
        // 新msg对象
        $newAMQPMsg = new AMQPMessage(json_encode($this->mqData), [
            'content_type' => MQHelper::CONTENT_TYPE_JSON,
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);
        // 将header信息携带
        $takeOldHeader && MQHelper::setMessageHeaders($newAMQPMsg, ['application_headers' => MQHelper::getMessageAppHeaders($this->message)]);

        return $newAMQPMsg;
    }
}
