<?php

namespace App\Service\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class MQHelper
{
    const DLX_SUFFIX = '.dlx';

    const RETRY_SUFFIX = '.retry';

    // 同步逆向日志ID给客退入库单
    const MQ_EXCHANGE_REVERSE_RETURN = 'sht.work_order.reverse_return_data';

    const MQ_QUEUE_REVERSE_RETURN = 'sht.work_order.reverse_return_data';

    const MQ_ROUTING_REVERSE_RETURN = 'sht.work_order.reverse_return_data';

    const MQ_QUEUE_REVERSE_RETURN_RETRY = 'sht.work_order.reverse_return_data.retry';

    const MQ_ROUTING_KEY_REVERSE_RETURN_RETRY = 'sht.work_order.reverse_return_data.retry';

    const MQ_QUEUE_REVERSE_RETURN_FAILED = 'sht.work_order.reverse_return_data.failed';

    const MQ_ROUTING_KEY_REVERSE_RETURN_FAILED = 'sht.work_order.reverse_return_data.failed';

    /**
     * 内容格式
     *
     * @var string
     */
    // JSON
    const CONTENT_TYPE_JSON = 'application/json';

    // Text
    const CONTENT_TYPE_TEXT = 'text/plain';

    /**
     * 队列链接
     *
     * @var AMQPStreamConnection
     */
    private $mqConnection;

    /**
     * 队列的频道
     *
     * @var AMQPChannel
     */
    public $mqChannel;

    /**
     * Exchange
     * @var string
     */
    private $exchange;

    /**
     * Queue
     * @var string
     */
    private $queue;

    /**
     * 创建队列链接
     * @param $exchange
     * @param $queue
     * @param string $serverName
     * @param array $conf
     * @throws \Exception
     */
    public function connectMQ($exchange, $queue, string $serverName = 'test1')
    {
        try {
            $this->exchange = $exchange;
            $this->queue = $queue;
            $serverName = config('mq.'.$serverName) ?? config('mq.test1');

            $this->mqConnection = new AMQPStreamConnection(
                $serverName['host'],
                $serverName['port'],
                $serverName['user'],
                $serverName['password'],
                $serverName['vhost']
            );

            // Loop as long as the channel has callbacks registered
            while ($this->mqChannel && count($this->mqChannel->callbacks)) {
                $this->mqChannel->wait();
            }

            $this->mqChannel = $this->mqConnection->channel();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 发送消息给队列
     * @param $messageBody
     * @param string $routing_key
     * @param array $headers
     * @throws \Exception
     */
    public function publishMessage($messageBody, $routing_key = '', $headers = [])
    {
        try {
            $contentType = static::CONTENT_TYPE_TEXT;
            if (is_array($messageBody)) {
                $messageBody = json_encode($messageBody);
                $contentType = static::CONTENT_TYPE_JSON;
            }

            $message = new AMQPMessage($messageBody, [
                'content_type' => $contentType,
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);
            $headers && self::setMessageHeaders($message, $headers);
            $this->mqChannel->basic_publish($message, $this->exchange, $routing_key);
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * 推送消息到MQ
     * @param $data
     * @param $exchange
     * @param $queue
     * @param string $routing_key
     * @param string $serverName
     * @throws \Exception
     */
    public function pushDataToMQ($data, $exchange, $queue, $routing_key = '', $serverName = 'test1')
    {
        try {
            $this->connectMQ($exchange, $queue, $serverName);

            $this->publishMessage($data, $routing_key);

            $this->closeMQ();
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * 设置消息头
     * @param AMQPMessage $message
     * @param array $headersData
     * @return bool|void
     */
    public static function setMessageHeaders(AMQPMessage $message, array $headersData)
    {
        if (! $headersData || ! is_array($headersData)) {
            return true;
        }
        foreach ($headersData as $name => $value) {
            $value = $name == 'application_headers' ? new AMQPTable($value) : strval($value);
            $message->set($name, $value);
        }
    }

    /**
     * 获取完整消息体
     *
     * User:phl
     * Date:2020/6/23
     * @return null|mixed
     */
    public function getWholeMessage()
    {
        try {
            $message = $this->mqChannel->basic_get($this->queue);

            return $message ?? null;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * 关闭队列链接
     */
    public function closeMQ()
    {
        if ($this->mqChannel) {
            $this->mqChannel->close();
        }

        if ($this->mqConnection) {
            $this->mqConnection->close();
        }
    }

    /**
     * 确认队列
     *
     * @param AMQPMessage $message
     */
    public function ack($message)
    {
        $this->mqChannel->basic_ack($message->delivery_info['delivery_tag']);
    }

    /**
     * 拒绝确认消息
     *
     * @param AMQPMessage $message
     */
    public function nack($message)
    {
        $this->mqChannel->basic_nack($message->delivery_info['delivery_tag'], false, true);
    }

    /**
     * 拒绝消息
     *
     * @param AMQPMessage $message
     */
    public function reject($message)
    {
        $this->mqChannel->basic_reject($message->delivery_info['delivery_tag'], false);
    }

    /**
     * 创建重试+延时队列和交换机
     * @param string $exchange_name 交换机名称
     * @param string $queue_name    队列名称
     * @param string $routing_key   绑定的key
     * @param int    $delayMin      延迟时间/分钟
     */
    public function createDelayExchangeAndQueue(string $exchange_name, string $queue_name, string $routing_key, int $delayMin = 1)
    {
        // 获取延时队列配置
        $delayedExchangeName = $this->getDelayedExchange();
        $delayedQueueName = $this->getDelayedQueue($delayMin);
        $delayedRouteKey = $this->getDelayedRouteKey($routing_key, $delayMin);
        $delayTtl = $this->getDelayedTtl($delayMin);
        //定义默认的交换器
        $this->mqChannel->exchange_declare($exchange_name, 'direct', false, true, false);
        //定义延迟交换器
        $this->mqChannel->exchange_declare($delayedExchangeName, 'direct', false, true, false);

        //定义延迟队列
        $this->mqChannel->queue_declare($delayedQueueName, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => $delayedExchangeName,
            'x-dead-letter-routing-key' => $delayMin == 1 ? $routing_key : $delayedRouteKey,
            'x-message-ttl' => $delayTtl, // 1分钟延迟
        ]));
        //绑定延迟队列到默认交换机上
        $this->mqChannel->queue_bind($delayedQueueName, $exchange_name, $delayedRouteKey);
        //定义重试消费队列
        $this->mqChannel->queue_declare($queue_name, false, true, false, false, false);
        //绑定重试消费队列到延迟交换器上
        $this->mqChannel->queue_bind($queue_name, $delayedExchangeName, $delayMin == 1 ? $routing_key : $delayedRouteKey);
    }

    /**
     * 获取延迟交换机
     * @return string
     */
    public function getDelayedExchange()
    {
        return 'sht.exchange.delay';
    }

    /**
     * 获取延迟队列
     * @param $delayMin
     * @return string
     */
    public function getDelayedQueue($delayMin)
    {
        $delayNameSuffix = '.delay';
        if ($delayMin > 1) {
            $delayNamePrefix = '_';
            $delayNamePrefix .= ceil(bcdiv($delayMin, 60, 2));
            $delayNamePrefix .= 'h';
            $delayNameSuffix = $delayNamePrefix . $delayNameSuffix;
        }
        return $this->queue . $delayNameSuffix;
    }

    /**
     * 获取延迟队列的绑定routeKey
     * @param $routeKey
     * @param  int    $delayMin
     * @return string
     */
    public function getDelayedRouteKey($routeKey, int $delayMin = 1)
    {
        $delayNameSuffix = '.delay';
        if ($delayMin > 1) {
            $delayNamePrefix = '_';
            $delayNamePrefix .= ceil(bcdiv($delayMin, 60, 2));
            $delayNamePrefix .= 'h';
            $delayNameSuffix = $delayNamePrefix . $delayNameSuffix;
        }
        return $routeKey . $delayNameSuffix;
    }

    /**
     * 获取延迟队列
     * @param $delayMin
     * @return int
     */
    public function getDelayedTtl($delayMin)
    {
        if ($delayMin > 1) {
            $delayMin = (int) bcmul(ceil(bcdiv($delayMin, 60, 2)), 60);
        }
        return $delayMin * 60 * 1000;
    }

    /**
     * 获取重试次数
     *
     * @param AMQPMessage $message
     *
     * @return int
     */
    public static function getMessageAppHeaders($message)
    {
        return $message->has('application_headers') ? $message->get('application_headers')
            ->getNativeData() : [];
    }
}
