<?php

/**
 * 电子合同签署 - 对接上上签
 */

namespace RanPack\SignContract;

require(__DIR__ . '/util/HttpUtils.php');

class SignContract
{

    /* 用户服务 */
    const RegisterUri = '/user/reg/';                                   //注册个人、企业uri
    const GetCertUri = '/user/getCert/';                                //查询证书编号
    const GetPersonalCredential = '/user/getPersonalCredential/';       //获取个人用户的证件信息
    const GetEnterpriseCredential = '/user/getEnterpriseCredential/';   //获取企业用户的证件信息
    const AsyncApplyCertStatus = '/user/async/applyCert/status/';       //异步申请证书请求发送成功后，根据返回的 taskId 调用本接口查询申请结果
    const CertInfo = '/user/cert/info/';                                //获取证书详细信息
    /* 签名/印章图片服务 */
    const SignatureImageUserCreate = '/signatureImage/user/create/';    //生成用户签名/印章图片
    const SignatureImageUserUpload = '/signatureImage/user/upload/';    //上传用户签名/印章图片
    const SignatureImageUserDownload = '/signatureImage/user/download/';//下载用户签名/印章图片
    /* 文件存储服务 */
    const StorageUpload = '/storage/upload/';                            //上传合同文件
    const StorageAddPDFElements = '/storage/addPDFElements//';           //为PDF文件添加元素
    const StorageDownload = '/storage/download/';                        //下载文件
    const PDFVerifySignatures = '/pdf/verifySignatures/';                //PDF文件验签
    /* 单文档合同服务 */
    const StorageContractUpload = '/storage/contract/upload/';           //上传并创建合同
    const ContractSignCert = '/contract/sign/cert/';                     //签署合同（即自动签）
    const ContractSend = '/contract/send/';                              //发送合同（即手动签，指定图片大小）
    const ContractCancel = '/contract/cancel/';                          //撤销合同
    const StorageContractLock = '/storage/contract/lock/';               //锁定并结束合同
    const ContractGetInfo = '/contract/getInfo/';                        //查询合同信息
    const ContractGetSignerConfig = '/contract/getSignerConfig/';        //获取合同签署参数
    const ContractGetSignerStatus = '/contract/getSignerStatus/';        //查询合同签署者状态
    const ContractDownload = '/contract/download/';                      //下载合同文件
    const ContractGetPreviewURL = '/contract/getPreviewURL/';            //获取预览页URL
    const ContractCreate = '/contract/create/';                          //创建合同
    const ContractCreateAttachment = '/contract/createAttachment/';      //生成合同附页
    const ContractDownloadAttachment = '/contract/downloadAttachment/';  //下载合同附页文件
    const ContractSignKeywords = '/contract/sign/keywords/';             //关键字定位签署合同
    const ContractVerifyContractFileHash = '/contract/verifyContractFileHash';       //在线验签（通过合同ID和哈希值）
    const ContractGetApplyArbitrationURL = '/contract/getApplyArbitrationURL/';      //获取存证页URL
    const DistContractPdfAddAttachment = '/dist/contract/pdfAddAttachment/';         //获取存证页URL
    /** 模版签署功能 **/
    const TemplateGetTemplateVars = '/template/getTemplateVars/';        //获取模版变量
    const TemplateCreateContractPdf = '/template/createContractPdf/';    //通过模版生成合同文件
    const ContractCreateByTemplate = '/contract/createByTemplate/';      //通过模版创建合同
    const ContractSignTemplate = '/contract/sign/template/';             //用模版变量签署合同
    const TemplateGetTemplate = '/template/getTemplate/';                //获取模版信息
    const ContractSendByTemplate = '/contract/sendByTemplate/';          //用模版变量的手动签
    const PageTemplateCreate = '/page/template/create/';                 //获取创建模版的地址
    const PageTemplateModify = '/page/template/modify/';                 //获取编辑模版的地址
    const TemplateGetTemplates = '/template/getTemplates/';              //获取开发者模版列表
    const PageTemplatePreview = '/page/template/preview/';               //预览模版
    const DistTemplateCreateContract = '/dist/template/createContract/'; //上传模板变量创建合同
    /** 补充 **/
    const RealNameStatusBestsign = '/realName/status/bestsign/'; //核验上上签SaaS用户实名认证状态
    const DistSignatureImageEntCreate = '/dist/signatureImage/ent/create/'; //生成企业印章图片

    //接口地址
    private $_serverHost = '';

    //开发者编号
    private $_developerId = '';

    //私钥
    private $_pem = '';

    //http实例（主要用来发送请求）
    private $_http_utils = null;

    public function __construct($init)
    {
        $this->_serverHost = $init['_serverHost'];

        $this->_developerId = $init['_developerId'];

        $this->_pem = $this->_formatPem($init['_pem'], "");

        $this->_http_utils = new HttpUtils();
    }

    /*
     * * * * * * * * * * * * *
     * 通用的请求方式
     * * * * * * * * * * * * *
     */

    /**
     * 通用的post请求方式
     *
     * @param $path
     * @param $data
     * @param array $header_data
     * @return mixed
     * @throws \Exception
     */
    public function sendPostApi($path, $data, $header_data=array())
    {
        $post_data = json_encode($data);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 通用的get请求方式
     *
     * @param $path
     * @param $data
     * @param array $header_data
     * @return mixed
     * @throws \Exception
     */
    public function sendGetApi($path, $data, $header_data=array())
    {
        $url_params = $data;

        //url
        $url = $this->_getApiUrl($path, $url_params, null);

        //content
        $response = $this->execute('GET', $url, null, $header_data, true);

        return $response;
    }

    /*
     * * * * * * * * * * * * *
     * 基础工具
     * * * * * * * * * * * * *
     */

    /**
     * 获取签名串
     * @param $args
     * @return
     */
    public function getRsaSign()
    {
        $pkeyid = openssl_pkey_get_private($this->_pem);
        if (!$pkeyid)
        {
            throw new \Exception("openssl_pkey_get_private wrong!", -1);
        }

        if (func_num_args() == 0) {
            throw new \Exception('no args');
        }
        $sign_data = func_get_args();
        $sign_data = trim(implode("\n", $sign_data));
        openssl_sign($sign_data, $sign, $this->_pem);
        openssl_free_key($pkeyid);

        return base64_encode($sign);
    }

    private function _formatPem($rsa_pem, $pem_type = '')
    {
        //如果是文件, 返回内容
        if (is_file($rsa_pem))
        {
            return file_get_contents($rsa_pem);
        }

        //如果是完整的证书文件内容, 直接返回
        $rsa_pem = trim($rsa_pem);
        $lines = explode("\n", $rsa_pem);
        if (count($lines) > 1)
        {
            return $rsa_pem;
        }

        //只有证书内容, 需要格式化成证书格式
        $pem = '';
        for ($i = 0; $i < strlen($rsa_pem); $i++)
        {
            $ch = substr($rsa_pem, $i, 1);
            $pem .= $ch;
            if (($i + 1) % 64 == 0)
            {
                $pem .= "\n";
            }
        }
        $pem = trim($pem);
        if (0 == strcasecmp('RSA', $pem_type))
        {
            $pem = "-----BEGIN RSA PRIVATE KEY-----\n{$pem}\n-----END RSA PRIVATE KEY-----\n";
        }
        else
        {
            $pem = "-----BEGIN PRIVATE KEY-----\n{$pem}\n-----END PRIVATE KEY-----\n";
        }

        return $pem;
    }

    //执行请求
    public function execute($method, $url, $request_body = null, array $header_data = array(), $auto_redirect = true, $cookie_file = null)
    {
        $response = $this->request($method, $url, $request_body, $header_data, $auto_redirect, $cookie_file);

        $http_code = $response['http_code'];
        if ($http_code != 200)
        {
            throw new \Exception("Request err, code: " . $http_code . "\nmsg: " . $response['response'] );
        }

        return $response['response'];
    }

    public function request($method, $url, $post_data = null, array $header_data = array(), $auto_redirect = true, $cookie_file = null)
    {
        $headers = array();
        $headers[] = 'Content-Type: application/json; charset=UTF-8';
        $headers[] = 'Cache-Control: no-cache';
        $headers[] = 'Pragma: no-cache';
        $headers[] = 'Connection: keep-alive';

        foreach ($header_data as $name => $value)
        {
            $line = $name . ': ' . rawurlencode($value);
            $headers[] = $line;
        }

        if (strcasecmp('POST', $method) == 0)
        {
            $ret = $this->_http_utils->post($url, $post_data, null, $headers, $auto_redirect, $cookie_file);
        }
        else
        {
            $ret = $this->_http_utils->get($url, $headers, $auto_redirect, $cookie_file);
        }
        return $ret;
    }

    /**
     * @param $path：接口名
     * @param $url_params: get请求需要放进参数中的参数
     * @param $rtick：随机生成，标识当前请求
     * @param $post_md5：post请求时，body的md5值
     * @return string
     */
    private function _genSignData($path, $url_params, $rtick, $post_md5)
    {
        $request_path = parse_url($this->_serverHost . $path)['path'];

        $url_params['developerId'] = $this->_developerId;
        $url_params['rtick'] = $rtick;
        $url_params['signType'] = 'rsa';

        ksort($url_params);

        $sign_data = '';
        foreach ($url_params as $key => $value)
        {
            $sign_data = $sign_data . $key . '=' . $value;
        }
        $sign_data = $sign_data . $request_path;

        if (null != $post_md5)
        {
            $sign_data = $sign_data . $post_md5;
        }

        return $sign_data;
    }

    private function _getRequestUrl($path, $url_params, $sign, $rtick)
    {
        $url = $this->_serverHost .$path . '?';

        //url
        $url_params['sign'] = $sign;
        $url_params['developerId'] = $this->_developerId;
        $url_params['rtick'] = $rtick;
        $url_params['signType'] = 'rsa';

        foreach ($url_params as $key => $value)
        {
            $value = urlencode($value);
            $url = $url . $key . '=' . $value . '&';
        }

        $url = substr($url, 0, -1);
        return $url;
    }

    /**
     * 组合生成签名和生成url
     * @param $path
     * @param null $url_param
     * @param $post_data
     * @throws \Exception
     */
    private function _getApiUrl($path, $url_param, $post_data)
    {

        //md5 post_data
        $md5_post_data = ($post_data != null) ? md5($post_data) : null;

        //rtick
        $rtick = time().rand(1000, 9999);

        //sign data
        $sign_data = $this->_genSignData($path, $url_param, $rtick, $md5_post_data);

        //sign
        $sign = $this->getRsaSign($sign_data);

        //url
        $url = $this->_getRequestUrl($path, $url_param, $sign, $rtick);

        return $url;
    }






    /*
     * * * * * * * * * * * * *
     * api method
     * * * * * * * * * * * * *
     */


    /**
     * ~~~ 异步申请状态查询 POST
     */
    public function asyncApplyCertStatus($data)
    {
        $path = self::AsyncApplyCertStatus;
        $post_data = json_encode([
            'account' => $data['account'],                 //用户唯一标识
            'taskId' => $data['taskId'],                  //任务单号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 获取证书详细信息 POST
     */
    public function certInfo($data)
    {
        $path = self::CertInfo;

        $post_data = json_encode([
            'account' => $data['account'],                 //用户唯一标识
            'certId' => $data['certId'],                  //证书编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }


    /**
     * ~~~ 上传用户签名/印章图片 POST
     */
    public function signatureImageUserUpload($data)
    {
        $path = self::SignatureImageUserUpload;

        $post_data = json_encode([
            'account' => $data['account'],                //用户唯一标识
            'imageData' => $data['imageData']               //图片文件内容,图片经 Base64 编码后的字符串
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 上传合同文件 POST
     */
    public function storageUpload($data)
    {
        $path = self::StorageUpload;
        $post_data = json_encode([
            'account' => $data['account'],                 //用户唯一标识
            'fdata' => $data['fdata'],                   //文件数据，base64编码
            'fmd5' => $data['fmd5'],                    //文件md5值
            'ftype' => $data['ftype'],                   //文件类型
            'fname' => $data['fname'],                   //文件名
            'fpages' => $data['fpages'],                  //文件页数
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 为PDF文件添加元素 POST
     */
    public function storageAddPDFElements($data)
    {
        $path = self::StorageAddPDFElements;
        $post_data = json_encode([
            'account' => $data['account'],                 //用户唯一标识
            'fid' => $data['fid'],                     //源文件编号,源文件必须是 PDF 文件格式
            'elements' => $data['elements'],                //要添加的元素集合,json array 格式
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 下载文件 GET
     */
    public function storageDownload($data)
    {
        $path = self::StorageDownload;

        $url_params['fid'] = $data['fid'];  //上传合同文件得到的文件编号

        //url
        $url = $this->_getApiUrl($path, $url_params, null);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('GET', $url, null, $header_data, true);

        return $response;
    }

    /**
     * ~~~ PDF文件验签 POST
     */
    public function PDFVerifySignatures($data)
    {
        $path = self::PDFVerifySignatures;

        $post_data = json_encode([
            'pdfData' => $data['pdfData'],                     //PDF 文件 base64 编码过的字符串
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 上传并创建合同 POST
     */
    public function storageContractUpload($data)
    {
        $path = self::StorageContractUpload;

        $post_data = json_encode([
            'account' => $data['account'],                //用户唯一标识
            'fmd5' => $data['fmd5'],                   //文件 md5 值
            'ftype' => $data['ftype'],                  //文件类型
            'fname' => $data['fname'],                  //文件名
            'fpages' => $data['fpages'],                 //文件页数
            'fdata' => $data['fdata'],                  //文件数据， base64 编码
            'title' => $data['title'],                  //合同标题
            'expireTime' => $data['expireTime'],             //合同能够签署 的截止时间
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 签署合同（即自动签）   POST
     */
    public function contractSignCert($data)
    {
        $path = self::ContractSignCert;
        $post_data = json_encode([
            'contractId' => $data['contractId'],                 //合同编号
            'signer' => $data['signer'],                     //签署者
            'signaturePositions' => $data['signaturePositions']           //指定的签署 位 置 ， json array 格式
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 发送合同（即手动签，指定图片大小） POST
     */
    public function contractSend($data)
    {
        $path = self::ContractSend;

        $post_data = json_encode([
            'contractId' => $data['contractId'],                                    //合同编号
            'signer' => $data['signer'],                                            //签署者
            'signaturePositions' => $data['signaturePositions'],                    //签名位置坐标列表
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;

    }

    /**
     * ~~~ 撤销合同 POST
     */
    public function contractCancel($data)
    {
        $path = self::ContractCancel;
        $post_data = json_encode([
            'contractId' => $data['contractId'],                 //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 创建合同 POST
     */
    public function contractCreate($data)
    {
        $path = self::ContractCreate;
        $post_data = json_encode([
            'contractId' => $data['contractId'],              //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 关键字定位签署合同 POST
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function contractSignKeywords($data)
    {
        $path = self::ContractSignKeywords;

        $post_data = json_encode([
            'contractId' => $data['contractId'],              //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 在线验签（通过合同ID和哈希值） POST
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function contractVerifyContractFileHash($data)
    {
        $path = self::ContractVerifyContractFileHash;

        $post_data = json_encode([
            'contractId' => $data['contractId'],              //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 获取存证页URL POST
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function contractGetApplyArbitrationURL($data)
    {
        //
        $path = self::ContractGetApplyArbitrationURL;

        $post_data = json_encode([
            'contractId' => $data['contractId'],              //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * ~~~ 通过模版创建合同 POST
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function contractCreateByTemplate($data)
    {

        $path = self::ContractCreateByTemplate;

        $post_data = json_encode([
            'account' => $data['account'],               //合同创建者账号
            'tid' => $data['tid'],                   //模版编号
            'templateToken' => $data['templateToken'],         ///template/createContractPdf/ 返回的 templateToken
            'title' => $data['title'],                 //合同标题
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 获取创建模版的地址 POST
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function pageTemplateCreate($data)
    {
        $path = self::PageTemplateCreate;

        $post_data = json_encode([
            'account' => $data['account'],               //操作者的用户标识
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 获取编辑模版的地址 POST
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function pageTemplateModify($data)
    {
        $path = self::PageTemplateModify;

        $post_data = json_encode([
            'account' => $data['account'],            //表示开发者是把这个页面提供给哪个用户操作的，这个用户标识必须已经调用“注册用户”接口在上上签系统里创建过，否则这里会报“user not exists”
            'tid' => $data['tid'],                    //模版编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 查询证书编号 POST
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function getCert($data)
    {
        $path = self::GetCertUri;

        $post_data = json_encode([
            'account' => $data['account']                 //用户唯一标识
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }
    /**
     * 获取个人用户的证件信息
     */
    public function getPersonalCredential($data)
    {
        $path = self::GetPersonalCredential;

        //post data
        $post_data['account'] = $data['account'];
        $post_data = json_encode($post_data);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 获取模版变量
     * method post
     */
    public function templateGetTemplateVars($data)
    {

        $path = self::TemplateGetTemplateVars;

        //post data
        $post_data['tid'] = $data['tid'];                                   //模版编号
        $post_data['isRetrieveAllVars'] = $data['isRetrieveAllVars'];       //是否获取所有变量,0 只返回变量的type 和 name；  1 返回变量的所有字段；
        $post_data = json_encode($post_data);

        //rtick
        $rtick = time().rand(1000, 9999);

        //sign data
        $sign_data = $this->_genSignData($path, null, $rtick, md5($post_data));

        //sign
        $sign = $this->getRsaSign($sign_data);

        //url
        $url = $this->_getRequestUrl($path, null, $sign, $rtick);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 通过模版生成合同文件 post
     */
    public function templateCreateContractPdf($data)
    {

        $path = self::TemplateCreateContractPdf;

        $post_data = [
            'account' => $data['account'],                  //合同创建者账号
            'tid' => $data['tid'],                          //模版编号
            'templateValues' => $data['templateValues'] ?? '',         //模版变量
            'groupValues' => $data['groupValues'] ?? '',         //模版变量
        ];
        $post_data = json_encode($post_data);

        //rtick
        $rtick = time().rand(1000, 9999);

        //sign data
        $sign_data = $this->_genSignData($path, null, $rtick, md5($post_data));

        //sign
        $sign = $this->getRsaSign($sign_data);

        //url
        $url = $this->_getRequestUrl($path, null, $sign, $rtick);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 查询合同信息 POST
     */
    public function contractGetInfo($data)
    {
        $path = self::ContractGetInfo;

        $post_data = json_encode([
            'contractId' => $data['contractId'],                 //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 上传模板变量创建合同 POST
     */
    public function distTemplateCreateContract($data)
    {
        $path = self::DistTemplateCreateContract;

        $post_data = json_encode([
            'tid' => $data['tid'],               //模版编号
            'account' => $data['account'],           //操作者的用户标识
            'templateValues' => $data['templateValues'],    //模版变量
            'title' => $data['title'],             //合同标题
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 获取模版信息 POST
     */
    public function templateGetTemplate($data)
    {
        $path = self::TemplateGetTemplate;

        $post_data = json_encode([
            'tid' => $data['tid'],                   //模版编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 获取合同签署参数 POST
     */
    public function contractGetSignerConfig($data)
    {

        $path = self::ContractGetSignerConfig;

        $post_data = json_encode([
            'account' => $data['account'],                 //签署者
            'contractId' => $data['contractId'],              //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 用模版变量签署合同
     */
    public function contractSignTemplate($data)
    {

        $path = self::ContractSignTemplate;

        $post_data = json_encode([
            'contractId' => $data['contractId'],            //合同编号
            'tid' => $data['tid'],                   //模版编号
            'vars' => $data['vars'],                  //模版变量值
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }


    /**
     * 用模版变量的手动签
     */
    public function contractSendByTemplate($data)
    {
        $path = self::ContractSendByTemplate;

        $post_data = json_encode([
            'contractId' => $data['contractId'],               //合同创建者账号
            'signer' => $data['signer'],                //指定给哪个用户看
            'tid' => $data['tid'],                   //模版编号
            'varNames' => $data['varNames'],              //模板的变量名称
            'returnUrl' => $data['returnUrl'] ?? '',
            'pushUrl' => $data['pushUrl'] ?? '',
//            'vcodeMobile' => $data['vcodeMobile'] ?? '',              //校验手机号
//            'isDrawSignatureImage' => $data['isDrawSignatureImage'],              //手动签署时是否手绘签名
//            'signatureImageName' => $data['signatureImageName'] ?? 'default',              //签名/印章图片
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }


    //注册个人用户并申请证书
    public function register($data)
    {

        $path = self::RegisterUri;

        //需要的必填参数
        $post_data = json_encode([
            'account' => $data['account'],                 //用户的唯一标识，可以是邮箱、手机号、证件号
            'name' => $data['name'],                            //用户名称,必须和证件上登记的姓名一致
            'userType' => $data['userType'],                          //用户类型,1 表示个人
            'credential' => $data['credential'],
            'applyCert' => $data['applyCert'],                         //是否申请证书，申请填写为1
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 生成用户签名/印章图片
     */
    public function signatureImageUserCreate($data)
    {

        $path = self::SignatureImageUserCreate;

        $post_data = json_encode([
            'account' => $data['account']                 //用户唯一标识
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 下载用户签名/印章图片 GET
     */
    public function signatureImageUserDownload($data)
    {

        $path = self::SignatureImageUserDownload;

        $url_params['account'] = $data['account'];
        $url_params['imageName'] = $data['imageName'] ?? '';

        //url
        $url = $this->_getApiUrl($path, $url_params, null);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('GET', $url, null, $header_data, true);

        return $response;
    }

    /**
     * 查询企业用户证件信息 POST
     */
    public function getEnterpriseCredential($data)
    {
        $path = self::GetEnterpriseCredential;

        $post_data = json_encode([
            'account' => $data['account']                 //用户唯一标识
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }


    //注册企业用户并申请证书
    public function registerCompany($data)
    {
        $path = self::RegisterUri;

        //需要的必填参数
        $post_data = json_encode([
            'account' => $data['account'],                 //用户的唯一标识，可以是邮箱、手机号、证件号
            'name' => $data['name'],                            //企业名称,必须和证件上登记的姓名一致
            'userType' => '2',                          //用户类型,1 表示个人，2 表示企业
            'credential' => $data['credential'],
            'applyCert' => $data['applyCert'] ?? 1,                         //是否申请证书，申请填写为1
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 锁定并结束合同 POST
     */
    public function storageContractLock($data)
    {
        $path = self::StorageContractLock;

        $post_data = json_encode([
            'contractId' => $data['contractId'],                 //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 查询合同签署者状态 POST
     */
    public function contractGetSignerStatus($data)
    {
        $path = self::ContractGetSignerStatus;

        $post_data = json_encode([
            'contractId' => $data['contractId']
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 、获取预览页URL POST
     */
    public function contractGetPreviewURL($data)
    {
        $path = self::ContractGetPreviewURL;

        $post_data = json_encode([
            'account' => $data['account'],              //合同编号
            'contractId' => $data['contractId'],              //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 生成合同附页 POST
     */
    public function contractCreateAttachment($data)
    {
        $path = self::ContractCreateAttachment;

        $post_data = json_encode([
            'contractId' => $data['contractId'],              //合同编号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 下载合同文件 GET
     */
    public function contractDownload($data)
    {
        $path = self::ContractDownload;

        $url_params['contractId'] = $data['contractId'];

        //url
        $url = $this->_getApiUrl($path, $url_params, null);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('GET', $url, null, $header_data, true);

        return $response;
    }

    /**
     * 下载合同附页文件 GET
     */
    public function contractDownloadAttachment($data)
    {
        $path = self::ContractDownloadAttachment;

        $url_params['contractId'] = $data['contractId'];

        //url
        $url = $this->_getApiUrl($path, $url_params, null);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('GET', $url, null, $header_data, true);

        return $response;
    }

    /**
     * 获取开发者模版列表
     */
    public function templateGetTemplates($data)
    {
        $path = self::TemplateGetTemplates;

        $post_data = json_encode([
            'categoryName' => $data['categoryName'],               //类别名称
            'pageSize' => $data['pageSize'],               //每页显示的条数
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 核验上上签SaaS用户实名认证状态
     */
    public function realNameStatusBestsign($data)
    {
        $path = self::RealNameStatusBestsign;

        $post_data = json_encode([
            'account' => $data['account'],               //
            'userType' => $data['userType'],               //每页显示的条数
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 生成企业印章图片 POST
     */
    public function distSignatureImageEntCreate($data)
    {
        $path = self::DistSignatureImageEntCreate;

        $post_data = json_encode([
            'account' => $data['account'],               //账号
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

    /**
     * 预览模板 POST
     */
    public function pageTemplatePreview($data)
    {
        $path = self::PageTemplatePreview;

        $post_data = json_encode([
            'tid' => $data['tid'],               //模版编号
            'account' => $data['account'],               //操作者的用户标识
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }


    /**
     * 合同PDF文件上添加附件 POST
     */
    public function distContractPdfAddAttachment($data)
    {

        $path = self::DistContractPdfAddAttachment;

        $post_data = json_encode([
            'contractId' => $data['contractId'],         //合同编号
            'fname' => $data['fname'],              //附件名称
            'fdata' => $data['fdata'],              //附件文件，base64编码字符串（文件最大不得超过10MB）
            'fdescription' => $data['fdescription']        //附件描述
        ]);

        //url
        $url = $this->_getApiUrl($path, null, $post_data);

        //header data
        $header_data = array();

        //content
        $response = $this->execute('POST', $url, $post_data, $header_data, true);

        return $response;
    }

}
