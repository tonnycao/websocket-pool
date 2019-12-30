<?php


namespace App\Services;

use Elasticsearch\ClientBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class ElasticService{

    protected $logger;
    protected $client;

    public function __construct($hosts, $log_config)
    {
        $logger = new Logger($log_config['name']);
        $logger->pushHandler(new StreamHandler($log_config['path'], Logger::DEBUG));
        $this->logger = $logger;
        $client = ClientBuilder::create()->setHosts($hosts)->setLogger($logger)->build();
        $this->client = $client;

    }

    //索引单位文档
    public function index($index,$body, $type, $id = NULL)
    {
        $params_index = [
            'index' => $index,
            'body' => $body
        ];
        if(!empty($type))
        {
            $params_index['type'] = $type;
        }
        if(!empty($id))
        {
            $params_index['id'] = $id;
        }

        try{
            $response = $this->client->index($params_index);
            return $response;
        }catch (\Exception $e)
        {
            $this->logger->addInfo($e->getMessage());
            return false;
        }
        return false;
    }
    public function getById($index,$type,$id)
    {
        $params = [
            'index'=>$index,
            'id'=>$id
        ];
        if(!empty($type))
        {
            $params['type'] = $type;
        }
        try{
            $response = $this->client->get($params);
            return $response['_source'];
        }catch (\Exception $e)
        {
            $this->logger->addInfo($e->getMessage());
            return false;
        }
        return false;
    }

    //获取一个文档 获取文档详情
    public function get($params)
    {
        try{
            $response = $this->client->get($params);
            return $response['_source'];
        }catch (\Exception $e)
        {
            $this->logger->addInfo($e->getMessage());
            return false;
        }
        return false;
    }

    //布尔搜索
    public function boolSearch($index,$type,$params,$size=2)
    {
        $must = [];
        foreach ($params as $field=>$value)
        {
            $must['match'] = [$field=>$value];
        }
        $params = [
            'index' => $index,
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => $must
                    ]
                ],
                'size'=>$size
            ]
        ];
        if(!empty($type))
        {
            $params['type'] = $type;
        }
        try{
            $response = $this->client->search($params);
            return $response['hits']['hits'];
        }catch (\Exception $e)
        {
            $this->logger->addInfo($e->getMessage());
            return false;
        }

        return false;
    }

    public function search($index,$type,$body)
    {
        $params = [
            'index' => $index,
            'body'  => $body
        ];
        if(!empty($type))
        {
            $params['type'] = $type;
        }
        try{
            $response = $this->client->search($params);
            return $response['hits']['hits'];
        }catch (\Exception $e)
        {
            $this->logger->addInfo($e->getMessage());
            return false;
        }

        return false;
    }

    //布尔嵌套搜索
    public function boolNestSearch($index, $type,$params,$bool_params,$size=2)
    {
        $must = [];
        $bool = [];
        foreach ($params as $field=>$value)
        {
            $must['match'] = [$field=>$value];
        }
        foreach ($bool_params as $field=>$value)
        {
            $bool['match'] = [$field=>$value];
        }
        $params = [
            'index' => $index,
            'body'  => [
                'query' => [
                    'bool' => [
                        'should' => $must,
                        'bool'=>['must'=>$bool]
                    ]
                ],
                'size'=>$size
            ]
        ];
        if(!empty($type))
        {
            $params['type'] = $type;
        }
        try{
            $response = $this->client->search($params);
            return $response['hits']['hits'];
        }catch (\Exception $e)
        {
            $this->logger->addInfo($e->getMessage());
            return false;
        }

        return false;
    }
    public function delete($index, $type, $id)
    {
        $params = [
            'index' => $index,
            'id' => $id
        ];
        if(!empty($type))
        {
            $params['type'] = $type;
        }
        try{
            $response = $this->client->delete($params);
            return $response;
        }catch (\Exception $e)
        {
            $this->logger->addInfo($e->getMessage());
            return false;
        }
        return false;
    }

    public function updateWithDoc($index, $type, $id, $body)
    {
        $params = [
            'index' => $index,
            'id' => $id,
            'body' => ['doc'=>$body]
        ];
        if(!empty($type))
        {
            $params['type'] = $type;
        }
        try{
            $response = $this->client->update($params);
            return $response;
        }catch (\Exception $e)
        {
            $this->logger->addInfo($e->getMessage());
            return false;
        }
        return false;
    }

    public function updateWithScript($index, $type, $id, $script, $param=NULL)
    {
        $params = [
            'index' => $index,
            'id' => $id,
            'script' => [
                'inline' => $script
            ]
        ];
        if(!empty($type))
        {
            $params['type'] = $type;
        }
        if(!empty($param))
        {
            $params['script']['params'] = $param;
        }
        try{
            $response = $this->client->update($params);
            return $response;
        }catch (\Exception $e){
            $this->logger->addInfo($e->getMessage());
            return false;
        }

        return false;
    }

    public function updateWithUpserts($index, $type, $id, $script, $param, $upserts)
    {
        $params = [
            'index' => $index,
            'id' => $id,
            'script' => [
                'inline' => $script,
                'params'=>$param,
                'upsert'=>$upserts
            ]
        ];
        if(!empty($type))
        {
            $params['type'] = $type;
        }
        try{
            $response = $this->client->update($params);
            return $response;
        }catch (\Exception $e){
            $this->logger->addInfo($e->getMessage());
            return false;
        }

        return false;
    }
}