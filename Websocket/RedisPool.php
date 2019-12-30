<?php
namespace App\Services\Websocket;
class RedisPool
{
    protected $pool;
    private static $instance;
    private $config = NULL;

    public static function init($config)
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    function __construct($config)
    {
        $this->pool = new \SplQueue;
        $this->config = $config;
        $i = 0;
        $options = [
            'connect_timeout' => 1,//连接事件超时时间（秒）
            'timeout' => 3600//socket连接状态的超时时间（秒）
        ];
        while ($i<$this->config['size'])
        {
            $redis = new \Swoole\Coroutine\Redis();
            $res = $redis->connect($this->config['host'], $this->config['port']);
            if($res)
            {
                $redis->setOptions($options);
                $redis->select($this->config['db']);
                $i++;
                $this->put($redis);
            }
        }
    }

    public function put($redis)
    {
        $this->pool->push($redis);
    }

    public function get()
    {
        //有空闲连接
        if ($this->pool->count() > 0)
        {
            $redis =  $this->pool->pop();
            //判断是否连接正常
            if(!$this->checkActive($redis))
            {
                return $this->reconnect();
            }
            return $redis;
        }
        //无空闲连接，创建新连接
        return $this->reconnect();
    }

    protected function reconnect()
    {
        $redis = new \Swoole\Coroutine\Redis();
        $res = $redis->connect($this->config['host'], $this->config['port']);
        if ($res == false)
        {
            return false;
        } else {
            return $redis;
        }
    }

    public function idle()
    {
        return $this->pool->count();
    }

    protected function checkActive($redis)
    {
        $flag = false;
        try{
            if($redis->ping() == 'pong')
            {
                $flag = true;
            }
        }catch (Exception $e) {
        }
        return $flag;
    }

    public function destruct()
    {
        // 连接池销毁, 置不可用状态, 防止新的客户端进入常驻连接池, 导致服务器无法平滑退出
        while (!$this->pool->isEmpty()) {
            $this->pool->pop();
        }
    }
}