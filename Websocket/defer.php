<?php
$server = new Swoole\Http\Server("0.0.0.0", 9516, SWOOLE_BASE);

$server->set([
    'worker_num' => 1,
]);
$server->on('Request', function ($request, $response) {
    $redis = new Swoole\Coroutine\Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->select(0);
    $redis->setDefer();
    $redis->sMembers('ws:online');

    $mysql = new Swoole\Coroutine\MySQL();
    $mysql->connect([
        'host' => '127.0.0.1',
        'port'=>3308,
        'user' => 'bestbox3',
        'password' => 'secret2017',
        'database' => 'db_bestbox3_internet',
    ]);
    $mysql->setDefer();
    $mysql->query('SELECT member_id,member_info_id,mac FROM tb_member_mac ORDER BY member_id DESC LIMIT 10');

    $redis_res = $redis->recv();
    echo 'redis:'.PHP_EOL;
    var_dump($redis_res);
    $mysql_res = $mysql->recv();
    echo 'mysql:'.PHP_EOL;
    var_dump($mysql_res);

    $response->end('Test End');
});
$server->start();

