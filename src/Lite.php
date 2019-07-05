<?php
namespace PhalApi\Alipay;

use PhalApi\Exception;

/**
 * 支付宝支付
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2019-07-04
 * @desc    description
 */
class Lite
{
    private $appid;
    private $rsa_private;
    private $rsa_public;
    private $out_rsa_public;
    private $notify_url;
    private $pay_params;
    private $params;

    /**
     * 构造方法
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-07-04
     * @desc    description
     * @param   [type]          $config [配置信息]
     */
    public function __construct($config)
    {
        $this->appid            = isset($config['appid']) ? $config['appid'] : '';
        $this->rsa_private      = isset($config['rsa_private']) ? $config['rsa_private'] : '';
        $this->rsa_public       = isset($config['rsa_public']) ? $config['rsa_public'] : '';
        $this->out_rsa_public   = isset($config['out_rsa_public']) ? $config['out_rsa_public'] : '';
        $this->notify_url       = isset($config['notify_url']) ? $config['notify_url'] : '';
    }

    /**
     * 支付入口
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-19
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Pay($params = [])
    {
        // 参数
        if(empty($params))
        {
            throw new Exception('参数不能为空', 400);
        }
        
        if(empty($params['client_type']))
        {
            throw new Exception('客户端类型有误', 401);
        }

        // 配置信息
        if(empty($this->appid) || empty($this->rsa_private) || empty($this->rsa_public) || empty($this->out_rsa_public) || empty($this->notify_url))
        {
            throw new Exception('支付缺少配置', 410);
        }

        // 支付参数
        $this->params = $params;
        $this->SetPayParams();

        // 支付方式
        switch($this->params['client_type'])
        {
            // app支付
            case 'app' :
                $this->pay_params['method'] = 'alipay.trade.app.pay';
                $ret = $this->AppPay();
                break;

            // 小程序支付
            case 'miniapp' :
                $this->pay_params['method'] = 'alipay.trade.create';
                $ret = $this->MiniPay();
                break;

            // web支付（包含h5）
            default :
                $this->WebPayCheck();
                if($this->IsMobile())
                {
                    $this->pay_params['method'] = 'alipay.trade.wap.pay';
                    $ret = $this->PayMobile();
                } else {
                    $this->pay_params['method'] = 'alipay.trade.page.pay';
                    $ret = $this->PayWeb();
                }
        }
        return $ret;
    }

    /**
     * web支付请求判断
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-07-05
     * @desc    description
     */
    private function WebPayCheck()
    {
        // 请求方式判断
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
        {
            throw new Exception('web支付请使用表单POST请求', 420);
        }
        if(!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'POST')
        {
            //throw new Exception('请使用POST访问', 430);
        }

        // 同步返回地址判断
        if(empty($this->params['return_url']))
        {
            throw new Exception('同步返回地址有误', 440);
        }

        // 当前是否为微信环境
        if(!empty($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger'))
        {
            throw new Exception('微信内不能使用支付宝', 450);
        }

        // 同步地址
        $this->pay_params['return_url'] = $this->params['return_url'];
    }

    /**
     * 设置支付参数
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-07-04
     * @desc    description
     */
    private function SetPayParams()
    {
        // 公共参数
        $this->pay_params = [
            'app_id'                =>  $this->appid,
            'format'                =>  'JSON',
            'charset'               =>  'utf-8',
            'sign_type'             =>  'RSA2',
            'timestamp'             =>  date('Y-m-d H:i:s'),
            'version'               =>  '1.0',
            'notify_url'            =>  $this->notify_url,
        ];

        // 支付参数
        $this->pay_params['biz_content'] = [
            'subject'               =>  $this->params['subject'],
            'out_trade_no'          =>  $this->params['order_no'],
            'total_amount'          =>  $this->params['total_amount'],
        ];
    }

    /**
     * wap手机支付
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2018-09-28T00:41:09+0800
     */
    private function PayMobile()
    {
        // 支付参数
        $this->pay_params['biz_content']['product_code'] = 'QUICK_WAP_WAY';

        // 生成签名
        $this->SetParamSign();
        
        // 输出执行form表单post提交
        $this->BuildRequestForm();
    }

    /**
     * PC支付
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2018-09-28T00:23:04+0800
     */
    private function PayWeb()
    {
        // 支付参数
        $this->pay_params['biz_content']['product_code'] = 'FAST_INSTANT_TRADE_PAY';

        // 生成签名
        $this->SetParamSign();
        //return $this->pay_params;
        // 输出执行form表单post提交
        $this->BuildRequestForm();
    }

    /**
     * APP支付
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-19
     * @desc    description
     */
    public function AppPay()
    {
        // 支付参数
        $this->pay_params['biz_content']['product_code'] = 'QUICK_MSECURITY_PAY';

        // 生成签名
        $this->SetParamSign();

        // 生成支付参数
        $value = '';
        $i = 0;
        foreach($this->pay_params as $k=>$v)
        {
            if(!empty($v) && "@" != substr($v, 0, 1))
            {
                if($i == 0)
                {
                    $value .= $k.'='.urlencode($v);
                } else {
                    $value .= '&'.$k.'='.urlencode($v);
                }
                $i++;
            }
        }
        unset($k, $v);
        return $value;
    }

    /**
     * 小程序支付
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-19
     * @desc    description
     */
    public function MiniPay()
    {
        // 用户openid
        if(empty($this->params['openid']))
        {
            throw new Exception('用户openid不能为空', 401);
        }
        $this->pay_params['biz_content']['buyer_id'] = $this->params['openid'];

        // 生成签名
        $this->SetParamSign();

        // 执行请求
        $result = $this->HttpRequest('https://openapi.alipay.com/gateway.do', $this->pay_params);
        $data = $this->ApiReturnHandle($result);
        return $data['trade_no'];
    }

    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-03-15
     * @desc    description
     * @return  [string]                 [提交表单HTML文本]
     */
    private function BuildRequestForm()
    {
        $html = "<form id='alipaysubmit' name='alipaysubmit' action='https://openapi.alipay.com/gateway.do?charset=utf-8' method='POST'>";
        foreach($this->pay_params as $key=>$val)
        {
            if(!empty($val))
            {
                $val = str_replace("'", "&apos;", $val);
                $html .= "<input type='hidden' name='".$key."' value='".$val."'/>";
            }
        }

        //submit按钮控件请不要含有name属性
        $html .= "<input type='submit' value='ok' style='display:none;''></form>";
        
        $html .= "<script>document.forms['alipaysubmit'].submit();</script>";
        
        exit($html);
    }

    /**
     * api返回处理
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-07-04
     * @desc    description
     * @param   [type]          $data [description]
     */
    private function ApiReturnHandle($data)
    {
        $key = str_replace('.', '_', $this->pay_params['method']).'_response';

        // 验证签名
        if(!$this->SyncRsaVerify($data, $key))
        {
            throw new Exception('签名验证错误', 430);
        }

        // 状态
        if(isset($data[$key]['code']) && $data[$key]['code'] == 10000)
        {
            return $data[$key];
        }

        // 直接返回支付信息
        throw new Exception($data[$key]['sub_msg'].'['.$data[$key]['sub_code'].']', 440);
    }

    /**
     * 支付回调处理
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-19
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Respond($params = [])
    {
        $data = empty($_POST) ? $_GET :  array_merge($_GET, $_POST);
        ksort($data);

        // 参数字符串
        $sign = '';
        foreach($data AS $key=>$val)
        {
            if ($key != 'sign' && $key != 'sign_type' && $key != 'code')
            {
                $sign .= "$key=$val&";
            }
        }
        $sign = substr($sign, 0, -1);

        // 签名
        if(!$this->OutRsaVerify($sign, $data['sign']))
        {
            throw new Exception('签名校验失败', 430);
        }

        // 支付状态
        $status = isset($data['trade_status']) ? $data['trade_status'] : $data['result'];
        switch($status)
        {
            case 'TRADE_SUCCESS':
            case 'TRADE_FINISHED':
            case 'success':
                return $this->ReturnData($data);
                break;
        }
        throw new Exception('处理异常错误', 400);
    }

    /**
     * 返回数据统一格式
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2018-10-06T16:54:24+0800
     * @param    [array]                   $data [返回数据]
     */
    private function ReturnData($data)
    {
        // 兼容web版本支付参数
        $buyer_user = isset($data['buyer_logon_id']) ? $data['buyer_logon_id'] : (isset($data['buyer_email']) ? $data['buyer_email'] : '');
        $pay_price = isset($data['total_amount']) ? $data['total_amount'] : (isset($data['total_fee']) ? $data['total_fee'] : '');

        // 返回数据固定基础参数
        $data['trade_no']       = $data['trade_no'];        // 支付平台 - 订单号
        $data['buyer_user']     = $buyer_user;              // 支付平台 - 用户
        $data['out_trade_no']   = $data['out_trade_no'];    // 本系统发起支付的 - 订单号
        $data['subject']        = $data['subject'];         // 本系统发起支付的 - 商品名称
        $data['pay_price']      = $pay_price;               // 本系统发起支付的 - 总价

        return $data;
    }

    /**
     * 生成参数和签名
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-07-04
     * @desc    description
     */
    private function SetParamSign()
    {
        // 将支付参数转为json
        $this->pay_params['biz_content'] = json_encode($this->pay_params['biz_content'], JSON_UNESCAPED_UNICODE);

        // 处理签名参数
        $value = '';
        ksort($this->pay_params);
        foreach($this->pay_params AS $key=>$val)
        {
            if(!empty($val) && substr($val, 0, 1) != '@')
            {
                $value .= "$key=$val&";
            }
        }

        $this->pay_params['sign'] = $this->MyRsaSign(substr($value, 0, -1));
    }

    /**
     * [HttpRequest 网络请求]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2017-09-25T09:10:46+0800
     * @param    [string]          $url  [请求url]
     * @param    [array]           $data [发送数据]
     * @return   [mixed]                 [请求返回数据]
     */
    private function HttpRequest($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $body_string = '';
        if(is_array($data) && 0 < count($data))
        {
            foreach($data as $k => $v)
            {
                $body_string .= $k.'='.urlencode($v).'&';
            }
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body_string);
        }
        $headers = array('content-type: application/x-www-form-urlencoded;charset=UTF-8');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $reponse = curl_exec($ch);
        if(curl_errno($ch))
        {
            return false;
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if(200 !== $httpStatusCode)
            {
                return false;
            }
        }
        curl_close($ch);
        return json_decode($reponse, true);
    }

    /**
     * [MyRsaSign 签名字符串]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2017-09-24T08:38:28+0800
     * @param    [string]                   $prestr [需要签名的字符串]
     * @return   [string]                           [签名结果]
     */
    private function MyRsaSign($prestr)
    {
        $res = "-----BEGIN RSA PRIVATE KEY-----\n";
        $res .= wordwrap($this->rsa_private, 64, "\n", true);
        $res .= "\n-----END RSA PRIVATE KEY-----";
        return openssl_sign($prestr, $sign, $res, OPENSSL_ALGO_SHA256) ? base64_encode($sign) : null;
    }

    /**
     * [MyRsaDecrypt RSA解密]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2017-09-24T09:12:06+0800
     * @param    [string]                   $content [需要解密的内容，密文]
     * @return   [string]                            [解密后内容，明文]
     */
    private function MyRsaDecrypt($content)
    {
        $res = "-----BEGIN PUBLIC KEY-----\n";
        $res .= wordwrap($this->rsa_public, 64, "\n", true);
        $res .= "\n-----END PUBLIC KEY-----";
        $res = openssl_get_privatekey($res);
        $content = base64_decode($content);
        $result  = '';
        for($i=0; $i<strlen($content)/128; $i++)
        {
            $data = substr($content, $i * 128, 128);
            openssl_private_decrypt($data, $decrypt, $res, OPENSSL_ALGO_SHA256);
            $result .= $decrypt;
        }
        openssl_free_key($res);
        return $result;
    }

    /**
     * [OutRsaVerify 支付宝验证签名]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2017-09-24T08:39:50+0800
     * @param    [string]                   $prestr [需要签名的字符串]
     * @param    [string]                   $sign   [签名结果]
     * @return   [boolean]                          [正确true, 错误false]
     */
    private function OutRsaVerify($prestr, $sign)
    {
        $res = "-----BEGIN PUBLIC KEY-----\n";
        $res .= wordwrap($this->out_rsa_public, 64, "\n", true);
        $res .= "\n-----END PUBLIC KEY-----";
        $pkeyid = openssl_pkey_get_public($res);
        $sign = base64_decode($sign);
        if($pkeyid)
        {
            $verify = openssl_verify($prestr, $sign, $pkeyid, OPENSSL_ALGO_SHA256);
            openssl_free_key($pkeyid);
        }
        return (isset($verify) && $verify == 1) ? true : false;
    }

     /**
     * [SyncRsaVerify 同步返回签名验证]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2017-09-25T13:13:39+0800
     * @param    [array]                   $data [返回数据]
     * @param    [boolean]                 $key  [数据key]
     */
    private function SyncRsaVerify($data, $key)
    {
        $string = json_encode($data[$key], JSON_UNESCAPED_UNICODE);
        return $this->OutRsaVerify($string, $data['sign']);
    }

    /**
     * 是否是手机访问
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  0.0.1
     * @datetime 2016-12-05T10:52:20+0800
     * @return  [boolean] [手机访问true, 则false]
     */
    private function IsMobile()
    {
        /* 如果有HTTP_X_WAP_PROFILE则一定是移动设备 */
        if(isset($_SERVER['HTTP_X_WAP_PROFILE'])) return true;
        
        /* 此条摘自TPM智能切换模板引擎，适合TPM开发 */
        if(isset($_SERVER['HTTP_CLIENT']) && 'PhoneClient' == $_SERVER['HTTP_CLIENT']) return true;
        
        /* 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息 */
        if(isset($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], 'wap') !== false) return true;
        
        /* 判断手机发送的客户端标志,兼容性有待提高 */
        if(isset($_SERVER['HTTP_USER_AGENT']))
        {
            $clientkeywords = array(
                'nokia','sony','ericsson','mot','samsung','htc','sgh','lg','sharp','sie-','philips','panasonic','alcatel','lenovo','iphone','ipad','ipod','blackberry','meizu','android','netfront','symbian','ucweb','windowsce','palm','operamini','operamobi','openwave','nexusone','cldc','midp','wap','mobile'
            );
            /* 从HTTP_USER_AGENT中查找手机浏览器的关键字 */
            if(preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }

        /* 协议法，因为有可能不准确，放到最后判断 */
        if(isset($_SERVER['HTTP_ACCEPT']))
        {
            /* 如果只支持wml并且不支持html那一定是移动设备 */
            /* 如果支持wml和html但是wml在html之前则是移动设备 */
            if((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) return true;
        }
        return false;
    }
}
?>