<?php
/**
 * AppleSdk
 */

namespace FF\Library\Sdk;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Library\Utils\ApiRequester;

class AppleSdk extends ApiRequester
{
    private $verifyReceiptUrl = "https://buy.itunes.apple.com/verifyReceipt";
    private $sandboxVerifyReceiptUrl = "https://sandbox.itunes.apple.com/verifyReceipt";
    private $loginAuthKeysUrl = "https://appleid.apple.com/auth/keys";
    protected static $supportedAlgs = array(
        'HS256' => array('hash_hmac', 'SHA256'),
        'HS512' => array('hash_hmac', 'SHA512'),
        'HS384' => array('hash_hmac', 'SHA384'),
        'RS256' => array('openssl', 'SHA256'),
        'RS384' => array('openssl', 'SHA384'),
        'RS512' => array('openssl', 'SHA512'),
    );
    
    public function queryOrder($receipt, &$isSandbox = false)
    {
        if ($isSandbox) {
            $this->setUrl($this->sandboxVerifyReceiptUrl);
        } else {
            $this->setUrl($this->verifyReceiptUrl);
        }
        
        $this->setOption(CURLOPT_SSL_VERIFYPEER, 0);
        $this->setOption(CURLOPT_SSL_VERIFYHOST, 0);
        
        for ($i = 0; $i < 3; ++$i) {
            $data = $this->requestData(json_encode(array("receipt-data" => $receipt)));
            if ($data) {
                $data = json_decode($data, true);
                if ($data['status'] == 21007) {
                    $isSandbox = true;
                    return $this->queryOrder($receipt, $isSandbox);
                }
                return $data;
            }
        }
        
        return null;
    }
    
    public function loginVerifyJwt($userID,$identityToken){
        
        $token = explode('.', $identityToken);
        $jwt_header = json_decode( base64_decode($token[0]), TRUE);
        $jwt_data = json_decode( base64_decode($token[1]), TRUE);
        $jwt_sign = $token[2];
        if( $userID !== $jwt_data['sub'] ){
            FF::throwException(Code::FAILED, '用户ID与token不对应！');
        }
        //if( $jwt_data['exp'] < time() ){
        //    FF::throwException(Code::FAILED, 'token已过期,请重新登录！');
        //}
        
        $this->setUrl($this->loginAuthKeysUrl);
        $applekeys = $this->requestData([],'GET');
        
        $applekeys = json_decode($applekeys, TRUE);
        if( !$applekeys ){
            FF::throwException(Code::FAILED, '请求苹果服务器失败');
        }
        
        $the_apple_key = [];
        foreach($applekeys['keys'] as $key){
            if($key['kid'] == $jwt_header['kid'] ){
                $the_apple_key = $key;
            }
        }
        unset($key);
        
        $pem = self::createPemFromModulusAndExponent($the_apple_key['n'], $the_apple_key['e']);
        $pKey = openssl_pkey_get_public($pem);
        if( $pKey === FALSE ){
            FF::throwException(Code::FAILED, '生成苹果pem失败');
        }
        $publicKeyDetails = openssl_pkey_get_details($pKey);
        
        $pub_key = $publicKeyDetails['key'];
        $alg = $jwt_header['alg'];
        
        $ok = self::verify("$token[0].$token[1]", static::urlsafeB64Decode($jwt_sign), $pub_key, $alg);
        if( !$ok ){
            FF::throwException(Code::FAILED, '苹果登录签名校验失败');
        }
        
        return true;
    }
    
    protected static function createPemFromModulusAndExponent($n, $e)
    {
        $modulus = static::urlsafeB64Decode($n);
        $publicExponent = static::urlsafeB64Decode($e);
        
        $components = array(
            'modulus' => pack('Ca*a*', 2, self::encodeLength(strlen($modulus)), $modulus),
            'publicExponent' => pack('Ca*a*', 2, self::encodeLength(strlen($publicExponent)), $publicExponent)
        );
        
        $RSAPublicKey = pack(
            'Ca*a*a*',
            48,
            self::encodeLength(strlen($components['modulus']) + strlen($components['publicExponent'])),
            $components['modulus'],
            $components['publicExponent']
        );
        
        // sequence(oid(1.2.840.113549.1.1.1), null)) = rsaEncryption.
        $rsaOID = pack('H*', '300d06092a864886f70d0101010500'); // hex version of MA0GCSqGSIb3DQEBAQUA
        $RSAPublicKey = chr(0) . $RSAPublicKey;
        $RSAPublicKey = chr(3) . self::encodeLength(strlen($RSAPublicKey)) . $RSAPublicKey;
        
        $RSAPublicKey = pack(
            'Ca*a*',
            48,
            self::encodeLength(strlen($rsaOID . $RSAPublicKey)),
            $rsaOID . $RSAPublicKey
        );
        
        $RSAPublicKey = "-----BEGIN PUBLIC KEY-----\r\n" .
            chunk_split(base64_encode($RSAPublicKey), 64) .
            '-----END PUBLIC KEY-----';
        
        return $RSAPublicKey;
    }
    
    protected static function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
    
    protected static function encodeLength($length)
    {
        if ($length <= 0x7F) {
            return chr($length);
        }
        
        $temp = ltrim(pack('N', $length), chr(0));
        return pack('Ca*', 0x80 | strlen($temp), $temp);
    }
    
    protected static function safeStrlen($str)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, '8bit');
        }
        return strlen($str);
    }
    
    protected static function verify($msg, $signature, $key, $alg)
    {
        if (empty(static::$supportedAlgs[$alg])) {
            FF::throwException(Code::FAILED, 'Algorithm not supported');
        }
        
        list($function, $algorithm) = static::$supportedAlgs[$alg];
        switch($function) {
            case 'openssl':
                $success = openssl_verify($msg, $signature, $key, $algorithm);
                if ($success === 1) {
                    return true;
                } elseif ($success === 0) {
                    return false;
                }
                FF::throwException(Code::FAILED, 'OpenSSL error');
            //return 'OpenSSL error: ' . openssl_error_string();
            case 'hash_hmac':
            default:
                $hash = hash_hmac($algorithm, $msg, $key, true);
                if (function_exists('hash_equals')) {
                    return hash_equals($signature, $hash);
                }
                $len = min(static::safeStrlen($signature), static::safeStrlen($hash));
                
                $status = 0;
                for ($i = 0; $i < $len; $i++) {
                    $status |= (ord($signature[$i]) ^ ord($hash[$i]));
                }
                $status |= (static::safeStrlen($signature) ^ static::safeStrlen($hash));
                
                return ($status === 0);
        }
    }
}