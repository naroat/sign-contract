# sign contract
电子合同 - 对接上上签平台

# 安装
```
composer require taoran/sign-contract
```

# 示例
```
$sc = new \Taoran\SignContract\SignContract([
    '_serverHost' => $_serverHost,
    '_developerId' => $_developerId,
    '_pem' => $_pem,    //私钥
]);

$path = '/user/getPersonalCredential/';

$param = [
    'account' => '130xxxxxxxx'
];

//调用
$result = $sc->sendPostApi($path, $param);

var_dump($result);
```
