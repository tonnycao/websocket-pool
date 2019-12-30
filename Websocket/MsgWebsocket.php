<?php
/**
 *  * Created by PhpStorm.
 *  * User: zhangqf
 *  * Date: 2019-06-20
 *  * Time: 13:13
 *  */

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
            $mysqli = $this->reconnect();
            if($mysqli)
            {
                $this->put($mysqli);
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
            $mysqli =  $this->pool->pop();
            //判断是否连接正常
            if(!$this->checkActive($mysqli))
            {
                return $this->reconnect();
            }
            return $mysqli;
        }
        //无空闲连接，创建新连接
        return $this->reconnect();
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

    public function reconnect()
    {
        $mysql = new Swoole\Coroutine\MySQL();
        $mysql->connect([
            'host' =>$this->config['host'],
            'port' => $this->config['port'],
            'user' => $this->config['user'],
            'password' => $this->config['password'],
            'database' => $this->config['database'],
        ]);
        $mysql->query('SET NAMES utf8mb4');
        return $mysql;
    }

    public function idle()
    {
        return $this->pool->count();
    }

}

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
        $this->pool = new SplQueue;
        $this->config = $config;
        $i = 0;
        while ($i<$this->config['size'])
        {
            $redis = new Swoole\Coroutine\Redis();
            $res = $redis->connect($this->config['ip'], $this->config['port']);
            if($res)
            {
                $redis->select($this->config['db']);
                $i++;
                $this->put($redis);
            }
        }
    }

    function put($redis)
    {
        $this->pool->push($redis);
    }

    function get()
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
        $redis = new Swoole\Coroutine\Redis();
        $res = $redis->connect($this->config['ip'], $this->config['port']);
        if ($res == false)
        {
            return false;
        } else {
            $redis->select($this->config['db']);
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
}


/**
 * 本文件需要实现的功能：
 *       >1.认证客户端(TV+Mobile)，客户端携带token，在laravel框架查询token的有效性
 *       >2.认证客户端连接成功以后，客户端连接fd-Mac-手机号-token 绑定在一个数据表（可以是redis存储）
 *       >3.客户端推送消息，严格按照消息结构体发送：  data=>  code:1,msg:"www.baidu.com/123.mp4"
 *       >3.Tv-Mobie能够实现互相推送
 *      TV ['mac','code','data','msg']
 *      Mobile ['mobile','code','data','msg']
 * publish  CH2019  "测试消息"
 * @param $redis
 * @param $P_channel_no
 * @param $P_msg
 * @author zhangqf
 * @date 2019-06-20 15:52
 */

//消息体编码定义
 define('WS_CODE_VIDEO',1); //发送视频
 define('WS_CODE_LOGOUT',2);//退出或者返回上一级
 define('WS_CODE_REDIRECT',3);//发送跳转URL

 //频道定义
 define ('WS_REDIS_ONLINE','test_ws:online');
 define ('WS_ONLINE_USER_TV','test_ws:user:tv:');
 define ('WS_ONLINE_USER_MOBILE','test_ws:user:tv:');
 define('WS_REDIS_CHANNEL_NOTICE','CH20019');
 define('WS_REDIS_CHANNEL_ALTER','ALTER');
 define ('WS_ONLINE_USER','test_ws:user:');
 //视频地址
 define ('VIDEO_URL','');
 define('TV','TV');
 define('MOBILE','MOBILE');
 define('SERVER','SERVER');
 define('POOL_MAX', 2);

//视频地址转换
function video_url_switch($raw_url)
{
    $url = $raw_url;
    if(strlen(VIDEO_URL)>0)
    {
        $url_list = parse_url($raw_url);
        $url = VIDEO_URL.'/'.$url_list['path'].'?'.$url_list['query'];
    }
    return $url;
}

$redis_config = [
    'ip'=>'127.0.0.1',
    'port'=>6379,
    'size'=>POOL_MAX,
    'db'=>0,
];
$db_config = [
    'host' => '127.0.0.1',
    'port' => 3308,
    'user' => 'bestbox3',
    'password' => 'secret2017',
    'database' => 'db_bestbox3_internet',
    'size'=>POOL_MAX,
];

function verify_mac($mac)
{
    global $mysqli;
    $sql = "SELECT member_id,member_info_id,mac FROM tb_member_mac WHERE mac='{$mac}'";
    $res = $mysqli->query($sql);
    $member_ids = [];
    if($res)
    {
        while ($result = $res->fetch_assoc())
        {
            $member_ids[] = [
                'member_id'=>$result['member_id'],
                'member_info_id'=>$result['member_info_id'],
            ];
        }
    }
    return $member_ids;
}

$WS_Server = new Swoole\WebSocket\Server("0.0.0.0", 20194);

$pool = NULL;
$mysql_pool = NULL;
$redis = NULL;
$fd_redis_map = [];
$redis_map = [];

$WS_Server->on('Start',function ($server){
    var_dump("Start:");

});

$WS_Server->on('WorkerStart',function ($server, $worker_id){
    global $redis_config, $db_config, $pool, $mysql_pool;
    var_dump("workID:".$worker_id);

    if(!isset($pool)) {
        $pool = RedisPool::init($redis_config);
    }
    if(!isset($mysql_pool))
    {
        $mysql_pool = MysqlPool::init($db_config);
        for ($i = 0; $i < POOL_MAX; $i++) {
            $mysql = $mysql_pool->get();

            var_dump($mysql->query('SELECT LAST_INSERT_ID'));
        }
    }
});

//  设备连接
$WS_Server->on('open', function($server, $req) {
    global $fd_redis_map,$pool,$redis_map;
    if(!isset($pool))
    {
        echo 'open pool \n';
        $pool = RedisPool::init(POOL_MAX);
    }

    if(!isset($fd_redis_map[$req->fd]))
    {
        echo 'open redis \n';
        $redis = $pool->get();
        $fd_redis_map[$req->fd] = $redis;
    }else{
        $redis = $fd_redis_map[$req->fd];
    }

    $redis_map[] = [
        'fd'=>$req->fd,
        'do'=>'open',
        'idle'=>$pool->idle(),
        'redis'=>$redis,
    ];
    var_dump($redis_map);
    if(!$redis->sIsMember(WS_REDIS_ONLINE, $req->fd))
    {
        $redis->sAdd(WS_REDIS_ONLINE,$req->fd);
    }
    $online_client = $redis->sMembers(WS_REDIS_ONLINE);
    echo "sMembers:\n";
    var_dump($online_client);

    $total = count($online_client);
    echo "客户端（Tv端连接成功）: {$req->fd}\n";
    echo "total online client:{$total}\n";
});

// 设备发送的消息
$WS_Server->on('message', function($server, $frame) {
    global $fd_redis_map,$mysqli,$pool,$redis_map;
    if(!isset($pool))
    {
        echo 'init pool\n';
        $pool = new RedisPool();
    }
    if(!isset($fd_redis_map[$frame->fd]))
    {
        echo 'message redis \n';
        $redis = $pool->get();
        $fd_redis_map[$frame->fd] = $redis;
    }else{
        $redis = $fd_redis_map[$frame->fd];
    }

    $redis_map[] = [
        'fd'=>$frame->fd,
        'do'=>'message',
        'idle'=>$pool->idle(),
        'redis'=>$redis,
    ];
    var_dump($redis_map);
    $data = trim($frame->data);
    echo "收到来自{$frame->fd}的消息: {$data}\n";
    $message_body = json_decode($data,true);
    $online_client = $redis->sMembers(WS_REDIS_ONLINE);
    var_dump($online_client);


    if(isset($message_body['mobile']) && !empty($message_body['mobile']))
    {
        //给TV发消息
        $mobile = trim($message_body['mobile']);
        $mobile_key = WS_ONLINE_USER.$mobile;
        if(!$redis->sIsMember($mobile_key, $frame->fd))
        {
            $redis->sAdd($mobile_key,$frame->fd);
        }
        $message = [
            'from'=>MOBILE
        ];
        switch ($message_body['code'])
        {
            case WS_CODE_VIDEO:
                $message['code'] = WS_CODE_VIDEO;
                if(is_array($message_body['data']))
                {
                    $data = [];
                    foreach ($message_body['data'] as $url)
                    {
                        $data[] = video_url_switch($url);
                    }
                    $message['data'] = $data;
                }else{
                    $message['data'] = video_url_switch($message_body['data']);
                }
                $message['msg'] = $message_body['msg'];
                break;
            case WS_CODE_LOGOUT:
                unset($message_body['mac']);
                unset($message_body['mobile']);
                $message = array_merge($message,$message_body);
                break;
        }
        $has_send = false;
        $macs = [];
        var_dump($message);
        $sql = "SELECT a.phone as mobile,b.mac,b.member_id,b.member_info_id FROM tb_members AS a LEFT JOIN tb_member_mac AS b ON a.id=b.member_id WHERE a.phone='{$mobile}'";
        echo $sql.PHP_EOL;
        $res = $mysqli->query($sql);
        if($res)
        {
            while ($result = $res->fetch_assoc())
            {
                if(!empty($result['mac']) && !in_array($result['mac'],$macs))
                {
                    $mac_key = WS_ONLINE_USER.$result['mac'];
                    $fd_list = $redis->sGetMembers($mac_key);
                    var_dump($fd_list);
                    foreach ($fd_list as $fd)
                    {
                        if($fd != $frame->fd && in_array($fd,$online_client))
                        {
                            $server->push($fd, json_encode($message));
                            $has_send = true;
                        }
                        if(!in_array($fd,$online_client))
                        {
                            $redis->sRem($mac_key,$fd);
                        }
                    }
                    $macs[] = $result['mac'];
                }
            }
        }

        if($has_send)
        {
            $msg = ['code'=>0,'msg'=>'success','from'=>SERVER];
            $server->push($frame->fd, json_encode($msg));
        }else{
            $msg = ['code'=>-1,'msg'=>'fail','from'=>SERVER];
            $server->push($frame->fd, json_encode($msg));
        }

    }else if(isset($message_body['mac']) && !empty($message_body['mac']))
    {
        //TV给Mobile发消息
        $has_send = false;
        $mobiles = [];
        //添加到TV集合
        $mac = str_replace('-','',$message_body['mac']);
        $mac =  trim($mac);
        $mac_key = WS_ONLINE_USER.$mac;

        if(!$redis->sIsMember($mac_key, $frame->fd))
        {
            $redis->sAdd($mac_key,$frame->fd);
        }

        $message = [
            'from'=>TV
        ];
        switch ($message_body['code'])
        {
            case WS_CODE_VIDEO:
                $message['code'] = WS_CODE_VIDEO;
                if(is_array($message_body['data']))
                {
                    $data = [];
                    foreach ($message_body['data'] as $url)
                    {
                        $data[] = video_url_switch($url);
                    }
                    $message['data'] = $data;
                }else{
                    $message['data'] = video_url_switch($message_body['data']);
                }
                $message['msg'] = $message_body['msg'];
                break;
            case WS_CODE_LOGOUT:
                unset($message_body['mac']);
                unset($message_body['mobile']);
                $message = $message_body;
                break;
        }
        var_dump($message);
        //查找online mobile
        $sql = "SELECT distinct(a.phone),b.mac,b.member_id,b.member_info_id FROM tb_members AS a LEFT JOIN tb_member_mac AS b ON a.id=b.member_id WHERE b.mac='{$message_body['mac']}'";
        echo $sql.'\n';
        $res = $mysqli->query($sql);
        if($res)
        {
            while ($result = $res->fetch_assoc())
            {
                if(!empty($result['phone']) && !in_array($result['phone'],$mobiles))
                {
                    $mobile_key = WS_ONLINE_USER.$result['phone'];
                    $fd_list = $redis->sGetMembers($mobile_key);
                    var_dump($fd_list);
                    if(!empty($fd_list))
                    {
                        foreach ($fd_list as $fd)
                        {
                            if($fd != $frame->fd && in_array($fd,$online_client))
                            {
                                $server->push($fd, json_encode($message));
                                $has_send = true;
                            }
                            if(!in_array($fd,$online_client))
                            {
                                $redis->sRem($mobile_key,$fd);
                            }
                        }
                    }

                    $mobiles[] = $result['phone'];
                }
            }
        }

        //发否发送消息
        if($has_send)
        {
            $msg = ['code'=>0,'msg'=>'success','from'=>SERVER];
            $server->push($frame->fd, json_encode($msg));
        }else{
            $msg = ['code'=>-1,'msg'=>'fail','from'=>SERVER];
            $server->push($frame->fd, json_encode($msg));
        }
    }

});


//  设备断开连接
$WS_Server->on('close', function($server, $fd) {
    global $fd_redis_map,$pool,$redis_map;

    if(!isset($fd_redis_map[$fd]))
    {
        echo 'close redis \n';
        $redis = $pool->get();
        $fd_redis_map[$fd] = $redis;
    }else{
        $redis = $fd_redis_map[$fd];
    }
    $redis_map[] = [
        'fd'=>$fd,
        'idle'=>$pool->idle(),
        'redis'=>$redis,
    ];
    var_dump($fd_redis_map);
    echo "设备断开连接: {$fd}\n";
    $redis->sRem(WS_REDIS_ONLINE, $fd);
    $redis->close();

});

// 初始化频道+启动服务
$WS_Server->start();





