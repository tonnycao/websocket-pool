<?php

namespace App\Services\Websocket;

class MysqlPool
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

    public function __construct($config)
    {
        $this->pool = new \SplQueue;
        $this->config = $config;
        $i = 0;

        while ($i<$this->config['size']) {
            $mysql = new \Swoole\Coroutine\MySQL();
            $res = $mysql->connect([
                'host' =>$config['host'],
                'port' => $config['port'],
                'user' => $config['username'],
                'password' => $config['password'],
                'database' => $config['database'],
            ]);
            if($res)
            {
                $mysql->query('SET NAMES utf8mb4');
                $this->put($mysql);
                $i++;
            }
        }
    }

    public function put($mysqli)
    {
        $this->pool->push($mysqli);
    }

    public function get()
    {
        //有空闲连接
        if ($this->pool->count() > 0)
        {
            $mysql =  $this->pool->pop();
            //判断是否连接正常
            if(!$this->checkActive($mysql))
            {
                return $this->reconnect($this->config);
            }
            return $mysql;
        }
        //无空闲连接，创建新连接
        return $this->reconnect($this->config);
    }

    protected function checkActive($mysql)
    {
        $flag = false;
        $res = $mysql->query('SELECT LAST_INSERT_ID()');
        if($res)
        {
            $flag = true;
        }
        return $flag;
    }

    public function reconnect($config)
    {
        $mysql = new \Swoole\Coroutine\MySQL();
        $mysql->connect([
            'host' =>$config['host'],
            'port' => $config['port'],
            'user' => $config['username'],
            'password' => $config['password'],
            'database' => $config['database'],
        ]);
        $mysql->query('SET NAMES utf8mb4');
        return $mysql;
    }

    public function idle()
    {
        return $this->pool->count();
    }

    public function destruct()
    {
        // 连接池销毁, 置不可用状态, 防止新的客户端进入常驻连接池, 导致服务器无法平滑退出
        while (!$this->pool->isEmpty()) {
            $this->pool->pop();
        }
    }
}