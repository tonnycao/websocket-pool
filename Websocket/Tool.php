<?php

namespace App\Services\Websocket;
use \App\Models\MemberMac;
use Lcobucci\JWT\Parser;
use Laravel\Passport\Token;

class Tool
{
    public static function videoUrlSwitch($raw_url,$video_url='')
    {
        $url = $raw_url;
        if(is_string($raw_url) && strlen($video_url)>0)
        {
            $url_list = parse_url($raw_url);
            if(empty($url_list) || empty($url_list['path']))
            {
                return $url;
            }
            if(empty($url_list['query']))
            {
                $url = trim($video_url,'/').'/'.$url_list['path'];
            }else{
                $url = trim($video_url,'/').'/'.$url_list['path'].'?'.$url_list['query'];
            }
        }
        return $url;
    }

    public static function parseAccessToken($access_tokens)
    {
        try{
            $Parser = new Parser();
            $Token = $Parser->parse($access_tokens);
            $id = $Token->getClaim('jti');
            return $id;
        }catch (\Exception $exception)
        {
            return '';
        }
    }

    public static function getMemberByAccessToken($access_tokens)
    {
        $member_id = 0;
        try{
            $Parser = new Parser();
            $Token = $Parser->parse($access_tokens);
            $id = $Token->getClaim('jti');
        }catch (\Exception $exception)
        {
            return -1;
        }

        if(empty($id))
        {
            return $member_id;
        }
        $oauth_access_tokens = Token::where('id', $id)->where('client_id', 2)->first();
        $now_timestamp = date('Y-m-d H:i:s');
        if($oauth_access_tokens && $oauth_access_tokens->expires_at>$now_timestamp)
        {
            $member_id = $oauth_access_tokens->user_id;
        }
        return $member_id;
    }

    //一个机顶盒可以绑定多次 同时在线的用户只有一个 在用户登录的时候已经做了限制了？对于一个已经绑定mac的用户只能登录一个
    public static function getMemberByMac($mac)
    {
        $data = [];
        $MemberMac = new MemberMac();
        $list = $MemberMac->getMemberByMac($mac);
        if(!$list)
        {
            return $data;
        }
        $member = $list[0];
        $data['member_id'] = $member->member_id;
        $data['member_info_id'] = $member->member_info_id;
        return $data;
    }

    public static function isOnline($member_id,$member_info_id)
    {
        $redis_online_client_key_prefix = "ONLINE_CLIENT:";
        $redis_online_client_key = $redis_online_client_key_prefix . $member_id . ":" . $member_info_id;
        // 切换cs 数据库
        \Illuminate\Support\Facades\Redis::select(env("CS_REDIS_DATABASE"));
        $online_client = \Illuminate\Support\Facades\Redis::get($redis_online_client_key);
        // 切回bs 数据库
        \Illuminate\Support\Facades\Redis::select(config("database.redis.default.database"));
        if($online_client)
        {
            return true;
        }
        return false;
    }

}