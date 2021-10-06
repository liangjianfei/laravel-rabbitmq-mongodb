<?php

namespace App\Http\Controllers;

use App\Service\RabbitMq\MQHelper;
use App\Models\AdClick;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;


class IndexController extends BaseController
{
    public function test()
    {
        var_dump(phpinfo());
    }

    /**
     *
     */
    public function index()
    {
        try {
            $mo = DB::connection('mongodb')->collection('ad_clicks')->find('615be8c14c7f521399e501a4');
            var_dump($mo);

            return 'ok';
        } catch (\Throwable $throwable) {
            throw new \Exception($throwable->getMessage(),$throwable->getCode());
        }
    }

    /**
     * @return string
     */
    public function addMongo()
    {
        try {
            AdClick::create(['ip' =>'31.42.4.14', 'ad_index' =>4, 'created_at' =>'2019-06-10 18:10:01', 'ip2long' =>ip2long('31.42.4.14')]);
            return 'ok';
        } catch (\Throwable $throwable) {
            throw new \Exception($throwable->getMessage(),$throwable->getCode());
        }
    }

    /**
     * 测试rabbitMq
     * @throws \Exception
     */
    public function sendMq()
    {
        try {
            $unique = uniqid();
            $data = ['uniqueId'=>$unique,'age'=>rand(18,30)];
            $exchange = MQHelper::MQ_EXCHANGE_REVERSE_RETURN;
            $queue = MQHelper::MQ_QUEUE_REVERSE_RETURN;
            $routingKey = MQHelper::MQ_ROUTING_REVERSE_RETURN;
            $mq = new MQHelper();
            $mq->connectMQ($exchange, $queue, 'test1');
            $mq->mqChannel->exchange_declare($exchange, 'direct', false, true, false);
            $mq->mqChannel->queue_declare($queue, false, true, false, false, false);
            $mq->mqChannel->queue_bind($queue, $exchange, $routingKey);
            $mq->publishMessage($data, $routingKey);

            return 'ok';
        } catch (\Throwable $throwable) {
            throw new \Exception($throwable->getMessage(),$throwable->getCode());
        }
    }

    /**
     * @param $data
     * @return bool
     */
    public function consumeMq($data)
    {
        try {
            $result = DB::connection('mysql')->table('ampq_test')->insert(
                ['uniqueId'=>$data['uniqueId'] ?? '','name'=>$data['name'],'age'=>$data['age']]
            );
            return $result;
        } catch (\Throwable $throwable) {
            throw new \Exception($throwable->getMessage(),$throwable->getCode(),$throwable->getFile(),$throwable->getLine());
        }
    }
}
