# 支付宝支付

支付宝支付基于支付宝api2.0接口实现 http://gong.gg/


## 安装和配置
修改项目下的composer.json文件，并添加：  
```
    "shopxo/phalapi2-alipay": "dev-master"
```
然后执行```composer update```。  

## 注册
在/path/to/phalapi/config/di.php文件中，注册：  
```php
$di->alipay = function() {
    return new \PhalApi\Alipay\Lite();
};
```

## 使用
第一种使用方式：直接输出二维码图片：
```php
\PhalApi\DI()->alipay->Pay();
```