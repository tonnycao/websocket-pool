<?php
namespace  App\Services;

class RsaHelper
{

    /**
     * 加密数据
     * @param string $raw_data 源数据
     * @param string $public_key 公钥
     * @param integer $padding 见 openssl_public_encrypt 的参数说明
     * @return string
     */
    public static function encrypt($raw_data, $public_key, $padding = OPENSSL_PKCS1_PADDING)
    {
        if ($public_key[0] != '-') {
            $public_key = self::rsa_ges($public_key, 'public');
        }

        //OPENSSL_PKCS1_PADDING下的处理方式
        $res = openssl_get_publickey($public_key);
        $keyInfo = openssl_pkey_get_details($res);
        $step = $keyInfo['bits'] / 8 - 11;
        $decrypted_list = [];
        for ($i = 0, $len = strlen($raw_data); $i < $len; $i += $step)
        {
            $data = substr($raw_data, $i, $step);
            $encrypted = '';
            openssl_public_encrypt($data, $encrypted, $res, $padding);
            $decrypted_list[] = ($encrypted);
        }
        openssl_free_key($res);
        $data = join('', $decrypted_list);
        return $data;
    }
    
    /**
     * 解密数据
     * @param string $encrypted_data 待解密的数据
     * @param string $private_key 私钥 
     * @return string
     */
    public static function decrypt($encrypted_data, $private_key, $padding = OPENSSL_PKCS1_PADDING)
    {
        if ($private_key[0] != '-') {
            $private_key = self::rsa_ges($private_key, 'private');
        }
        $res = openssl_get_privatekey($private_key);
        $key_info = openssl_pkey_get_details($res);
        $step = $key_info['bits'] / 8;
        $decrypted_list = [];
        for ($i = 0, $len = strlen($encrypted_data); $i < $len; $i += $step)
        {
            $data = substr($encrypted_data, $i, $step);
            $decrypted = '';
            openssl_private_decrypt($data, $decrypted, $res, $padding);
            $decrypted_list[] = $decrypted;
        }
        openssl_free_key($res);
        return join('', $decrypted_list);
    }

    /**
     * RSA签名
     * @param string $data 需要签名的数据
     * @param string $private_key 私钥
     * @return string
     */
    public static function sign($data, $private_key, $signature_alg = OPENSSL_ALGO_SHA1)
    {
        if ($private_key[0] != '-') {
            $private_key = self::rsa_ges($private_key, 'private');
        }
        $res = openssl_get_privatekey($private_key);
        $sign = null;
        openssl_sign($data, $sign, $res, $signature_alg);
        openssl_free_key($res);
        return $sign;
    }

    /**
     * RSA验签
     * @param string $data 签名数据
     * @param string $sign 签名
     * @param integer $signature_alg 签名算法 
     * @return type
     */
    public static function verify($data, $sign, $pubilc_key, $signature_alg = OPENSSL_ALGO_SHA1)
    {
        if ($pubilc_key[0] != '-') {
            $pubilc_key = self::rsa_ges($pubilc_key, 'public');
        }

        $res = openssl_get_publickey($pubilc_key);
        
        $result = openssl_verify($data, $sign, $res, $signature_alg);

        openssl_free_key($res);

        return $result;
    }
    
    /**
     *  RSA签名  PKCS12标准 
     * 
     * @param unknown $data
     * @param unknown $private_key
     * @param string $passwor
     * @return void|string
     */
    public static function pkcs12Sign($data, $private_key, $passwor = NULL)
    {

        $certs = array();

        openssl_pkcs12_read(file_get_contents($private_key), $certs, $passwor); //其中password为你的证书密码

        if (!$certs)
            return;

        $signature = '';

        openssl_sign($data, $signature, $certs['pkey']);

        return base64_encode($signature);
    }

    /**
     * URL安全 base64 encode
     * @param type $string
     * @return type
     */
    public static function urlsafe_base64encode($string)
    {
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($string));
    }
    
    /**
     * URL安全 base64 decode
     * @param type $string
     * @return type
     */
    public static function urlsafe_base64decode($string)
    {
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    public static function rsa_ges($str, $type = 'public')
    {
        $publicKeyString = "-----BEGIN " . strtoupper($type) . " KEY-----\n";
        $publicKeyString .= wordwrap($str, 64, "\n", true);
        $publicKeyString .= "\n-----END " . strtoupper($type) . " KEY-----\n";
        return $publicKeyString;
    }
}
