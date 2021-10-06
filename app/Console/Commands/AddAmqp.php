<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Service\RabbitMq\MQHelper;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\IndexController;
use App\Exceptions\MqCheckException;

class AddAmqp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        $mqhelper = new MQHelper();
        // 正常交换机
        $exchangeName = MQHelper::MQ_EXCHANGE_REVERSE_RETURN;
        // 正常队列
        $queueName = MQHelper::MQ_QUEUE_REVERSE_RETURN;
        // 正常队列routeKey
        $routingKey = MQHelper::MQ_ROUTING_REVERSE_RETURN;

        // 创建连接
        $mqhelper->connectMQ($exchangeName, $queueName, 'test1');

        //定义交换机
        $mqhelper->mqChannel->exchange_declare($exchangeName, 'direct', false, true, false);

        //定义正常消费队列
        $mqhelper->mqChannel->queue_declare($queueName, false, true, false, false, false);

        //绑定正常队列到交换机上
        $mqhelper->mqChannel->queue_bind($queueName, $exchangeName, $routingKey);

        // 重试队列
        $retryQueueName = MQHelper::MQ_QUEUE_REVERSE_RETURN_RETRY;


        $i = 0;
        $max = 1000;

        do {
            ++$i;
            $message = $mqhelper->getWholeMessage();
            if (! $message || ! $message->body) {
                continue;
            }

            $data = $message->body;
            try {
                $data = json_decode($data, true);
                $this->checkData($data);

                $controller = new IndexController();
                $controller->consumeMq($data);
                // 确认消息
                $mqhelper->ack($message);
            }catch (MqCheckException $checkException) {
                $mqhelper->reject($message);
                Log::error('INFO: credit fail  【' . $checkException->getMessage() . '】数据:' . json_encode($data));
            }catch (\Throwable $exception) {
                Log::error('INFO: consume fail  【' . $exception->getMessage() . '】数据:' . json_encode($data));
                // 消费失败的异常处理
                // 创建重试+延迟消费的交换机和队列
                $mqhelper->createDelayExchangeAndQueue($exchangeName, $retryQueueName, $routingKey);
                // 推送retry消息
                $mqhelper->publishMessage($data, $mqhelper->getDelayedRouteKey($routingKey));
                $mqhelper->ack($message);
            }
        } while ($i < $max);

        $mqhelper->closeMQ();
    }

    /**
     * 参数校验
     * @param $data
     * @throws \Exception
     */
    private function checkData($data)
    {
        // 数据空检测
        if (! is_array($data)) {
            throw new MqCheckException('接收到的数据为空');
        }
        // 字段检测
        if (empty($data['name'])) {
            throw new MqCheckException('name为空');
        }
        if (empty($data['age'])) {
            throw new MqCheckException('age参数错误');
        }

        return true;
    }
}
