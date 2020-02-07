<?php

/**
 * 合同签署 - 上上签平台
 * 创建债权店铺 - 判断是否有上上签用户 - 没有就创建上上签企业账户
 * 用户购买后申请签约 - 提交信息后，债权店铺发起合同签署
 */

namespace RanPack\SignContract;

require(__DIR__ . '/util/HttpUtils.php');

class SignContract
{
    protected $author;

    //测试环境
    const ServerHost = 'https://openapi.bestsign.info';

    //正式环境
//    const ServerHost = 'https://openapi.bestsign.cn';
    /* 用户服务 */
    const RegisterUri = '/openapi/v2/user/reg/';                                   //注册个人、企业uri
    const GetCertUri = '/openapi/v2/user/getCert/';                                //查询证书编号
    const GetPersonalCredential = '/openapi/v2/user/getPersonalCredential/';       //获取个人用户的证件信息
    const GetEnterpriseCredential = '/openapi/v2/user/getEnterpriseCredential/';   //获取企业用户的证件信息
    const AsyncApplyCertStatus = '/openapi/v2/user/async/applyCert/status/';       //异步申请证书请求发送成功后，根据返回的 taskId 调用本接口查询申请结果
    const CertInfo = '/openapi/v2/user/cert/info/';                                //获取证书详细信息
    /* 签名/印章图片服务 */
    const SignatureImageUserCreate = '/openapi/v2/signatureImage/user/create/';    //生成用户签名/印章图片
    const SignatureImageUserUpload = '/openapi/v2/signatureImage/user/upload/';    //上传用户签名/印章图片
    const SignatureImageUserDownload = '/openapi/v2/signatureImage/user/download/';//下载用户签名/印章图片
    /* 文件存储服务 */
    const StorageUpload = '/openapi/v2/storage/upload/';                            //上传合同文件
    const StorageAddPDFElements = '/openapi/v2/storage/addPDFElements//';           //为PDF文件添加元素
    const StorageDownload = '/openapi/v2/storage/download/';                        //下载文件
    const PDFVerifySignatures = '/openapi/v2/pdf/verifySignatures/';                //PDF文件验签
    /* 单文档合同服务 */
    const StorageContractUpload = '/openapi/v2/storage/contract/upload/';           //上传并创建合同
    const ContractSignCert = '/openapi/v2/contract/sign/cert/';                     //签署合同（即自动签）
    const ContractSend = '/openapi/v2/contract/send/';                              //发送合同（即手动签，指定图片大小）
    const ContractCancel = '/openapi/v2/contract/cancel/';                          //撤销合同
    const StorageContractLock = '/openapi/v2/storage/contract/lock/';               //锁定并结束合同
    const ContractGetInfo = '/openapi/v2/contract/getInfo/';                        //查询合同信息
    const ContractGetSignerConfig = '/openapi/v2/contract/getSignerConfig/';        //获取合同签署参数
    const ContractGetSignerStatus = '/openapi/v2/contract/getSignerStatus/';        //查询合同签署者状态
    const ContractDownload = '/openapi/v2/contract/download/';                      //下载合同文件
    const ContractGetPreviewURL = '/openapi/v2/contract/getPreviewURL/';            //获取预览页URL
    const ContractCreate = '/openapi/v2/contract/create/';                          //创建合同
    const ContractCreateAttachment = '/openapi/v2/contract/createAttachment/';      //生成合同附页
    const ContractDownloadAttachment = '/openapi/v2/contract/downloadAttachment/';  //下载合同附页文件
    const ContractSignKeywords = '/openapi/v2/contract/sign/keywords/';             //关键字定位签署合同
    const ContractVerifyContractFileHash = '/openapi/v2/contract/verifyContractFileHash';       //在线验签（通过合同ID和哈希值）
    const ContractGetApplyArbitrationURL = '/openapi/v2/contract/getApplyArbitrationURL/';      //获取存证页URL
    const DistContractPdfAddAttachment = '/openapi/v2/dist/contract/pdfAddAttachment/';         //获取存证页URL
    /** 模版签署功能 **/
    const TemplateGetTemplateVars = '/openapi/v2/template/getTemplateVars/';        //获取模版变量
    const TemplateCreateContractPdf = '/openapi/v2/template/createContractPdf/';    //通过模版生成合同文件
    const ContractCreateByTemplate = '/openapi/v2/contract/createByTemplate/';      //通过模版创建合同
    const ContractSignTemplate = '/openapi/v2/contract/sign/template/';             //用模版变量签署合同
    const TemplateGetTemplate = '/openapi/v2/template/getTemplate/';                //获取模版信息
    const ContractSendByTemplate = '/openapi/v2/contract/sendByTemplate/';          //用模版变量的手动签
    const PageTemplateCreate = '/openapi/v2/page/template/create/';                 //获取创建模版的地址
    const PageTemplateModify = '/openapi/v2/page/template/modify/';                 //获取编辑模版的地址
    const TemplateGetTemplates = '/openapi/v2/template/getTemplates/';              //获取开发者模版列表
    const PageTemplatePreview = '/openapi/v2/page/template/preview/';               //预览模版
    const DistTemplateCreateContract = '/openapi/v2/dist/template/createContract/'; //上传模板变量创建合同

    //开发者编号
    public $developerId = '';

    //unix 时间戳+4位随机数
    public $rtick;

    //签名串计算方法
    public $signType = 'rsa';

    //签名串
    public $sign = '';

    public $private_key = '';

    private $_http_utils = null;

    public function __construct($init)
    {
        $this->rtick = time() . rand(1000, 9999);

        $this->developerId = $init['DeveloperId'];

        $this->_keyInit($init['pem'], "");

        $this->_http_utils = new HttpUtils();
    }

    //注册个人用户并申请证书
    public function register()
    {
        //需要的必填参数
        $param = [
            'account' => '15013070796',                 //用户的唯一标识，可以是邮箱、手机号、证件号
            'name' => '陶然',                            //用户名称,必须和证件上登记的姓名一致
            'userType' => '1',                          //用户类型,1 表示个人
            'credential' => json_encode([               //用户证件信息对象
                'identity' => '522428000000000000'      //用户证件号
            ]),
            'applyCert' => '1',                         //是否申请证书，申请填写为1
        ];

        //生成签名
        $sign = self::signCreate($param, self::RegisterUri, 'post');

        //组合api url
        $api_url = self::apiUrl(self::RegisterUri, $sign);

        //请求注册
        $res = self::http_post($api_url, $param);

        return $res;
    }

    //注册企业用户并申请证书
    public function registerCompany()
    {
        //需要的必填参数
        $param = [
            'account' => '15013070796',                 //用户的唯一标识，可以是邮箱、手机号、证件号
            'name' => '陶然',                            //用户名称,必须和证件上登记的姓名一致
            'userType' => '2',                          //用户类型,1 表示个人，2 表示企业
            'credential' => json_encode([               //用户证件信息对象
                'regcode' => '',                        //三证合一传：统一社会信用代码；/老三证传：：工商注册号；/个体户传：：工商注册号
                'orgcode' => '',                        //三证合一传：统一社会信用代码；/老三证传：：组织机构代码；/个体户传：：''
                'taxcode' => '',                        //三证合一传：统一社会信用代码；/老三证传：：税务登记证；/个体户传：：''
                'legalPerson' => '',                    //法定代表人姓名或经办人姓名
                'legalPersonIdentity' => '',            //法定代表人证件号或经办人证件号
                'legalPersonIdentityType' => '',        //法定代表人证件类型或经办人证件类型，0-居民身份证
                'contactMobile' => '',                  //联系手机
            ]),
            'applyCert' => '1',                         //是否申请证书，申请填写为1
        ];

        //生成签名 + 组合api url
        $sign_api = self::signApiUrl($param, self::RegisterUri, 'post');

        //请求注册
        $res = self::http_post($sign_api, $param);

        return $res;
    }

    //查询证书编号
    public function getCert()
    {
        $param = [
            'account' => ''                 //用户唯一标识
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::GetCertUri, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 获取个人用户的证件信息
     */
    public function getPersonalCredential($data)
    {
        $param = [
            'account' => $data['account']                 //用户唯一标识
        ];
        $param = json_encode($param);
        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::GetPersonalCredential, 'post');

        $header_data = array();
        //请求
//        $res = $this->http_post($sign_api, $param);
        $res = $this->execute('POST', $sign_api, $param, $header_data, true);

        return $res;
    }

    /**
     * 异步申请状态查询
     */
    public function asyncApplyCertStatus()
    {
        $param = [
            'account' => '',                 //用户唯一标识
            'taskId' => '',                  //任务单号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::AsyncApplyCertStatus, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 获取证书详细信息
     */
    public function certInfo()
    {
        $param = [
            'account' => '',                 //用户唯一标识
            'certId' => '',                  //证书编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::CertInfo, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }


    /**
     * 查询企业用户证件信息
     */
    public function getEnterpriseCredential($data)
    {

        $param = [
            'account' => $data['account']                 //用户唯一标识
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::GetEnterpriseCredential, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 生成用户签名/印章图片
     */
    public function signatureImageUserCreate()
    {
        $param = [
            'account' => ''                 //用户唯一标识
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::SignatureImageUserCreate, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 上传用户签名/印章图片
     */
    public function signatureImageUserUpload()
    {
        $param = [
            'account' => '',                //用户唯一标识
            'imageData' => ''               //图片文件内容,图片经 Base64 编码后的字符串
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::SignatureImageUserUpload, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 下载用户签名/印章图片
     */
    public function signatureImageUserDownload()
    {
        $param = [
            'account' => '',                 //用户唯一标识
            'imageName' => '',                 //签名/印章图片名称
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::SignatureImageUserDownload, 'get');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 上传合同文件
     */
    public function storageUpload()
    {
        $param = [
            'account' => '',                 //用户唯一标识
            'fdata' => '',                   //文件数据，base64编码
            'fmd5' => '',                    //文件md5值
            'ftype' => '',                   //文件类型
            'fname' => '',                   //文件名
            'fpages' => '',                  //文件页数
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::StorageUpload, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 为PDF文件添加元素
     */
    public function storageAddPDFElements()
    {
        $param = [
            'account' => '',                 //用户唯一标识
            'fid' => '',                     //源文件编号,源文件必须是 PDF 文件格式
            'elements' => '',                //要添加的元素集合,json array 格式
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::StorageAddPDFElements, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 下载文件
     */
    public function storageDownload()
    {
        $param = [
            'fid' => '',                     //上传合同文件得到的文件编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::StorageDownload, 'get');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * PDF文件验签
     */
    public function PDFVerifySignatures()
    {
        $param = [
            'pdfData' => '',                     //PDF 文件 base64 编码过的字符串
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::PDFVerifySignatures, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 上传并创建合同
     */
    public function storageContractUpload()
    {
        $param = [
            'account' => '',                //用户唯一标识
            'fmd5' => '',                   //文件 md5 值
            'ftype' => '',                  //文件类型
            'fname' => '',                  //文件名
            'fpages' => '',                 //文件页数
            'fdata' => '',                  //文件数据， base64 编码
            'title' => '',                  //合同标题
            'expireTime' => '',             //合同能够签署 的截止时间
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::StorageContractUpload, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 签署合同（即自动签）
     */
    public function contractSignCert()
    {
        $param = [
            'contractId' => '',                 //合同编号
            'signer' => '',                     //签署者
            'signaturePositions' => [           //指定的签署 位 置 ， json array 格式
                'x' => '',                      //横坐标，按页面尺寸 的百分比计算，取值 0.0 - 1.0。以左上角为 原点
                'y' => '',                      //纵坐标
            ],
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractSignCert, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 发送合同（即手动签，指定图片大小）
     */
    public function contractSend()
    {
        $param = [
            'contractId' => '',                 //合同编号
            'signer' => '',                     //签署者
            'signaturePositions' => [           //指定的签署 位 置 ， json array 格式
                'x' => '',                      //横坐标，按页面尺寸 的百分比计算，取值 0.0 - 1.0。以左上角为 原点
                'y' => '',                      //纵坐标
                'pageNum' => '',                //页码
                'rptPageNums' => '',            //当前位置的签名需要复制到的目标页 码列表。该参数用于控制是否将当前位 置的签名复制到其他页
                'type' => '',                   //日期类型专用，当签署位置是“日期” 类型的签名时，本参数需要填写 “date”
                'dateTimeFormat' => '',         //日期
                'fontSize' => '',               //日期的字号大小
            ],
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractSend, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 撤销合同
     */
    public function contractCancel()
    {
        $param = [
            'contractId' => '',                 //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractCancel, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 锁定并结束合同
     */
    public function storageContractLock()
    {
        $param = [
            'contractId' => '',                 //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::StorageContractLock, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 查询合同信息
     */
    public function contractGetInfo()
    {
        $param = [
            'contractId' => '',                 //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractGetInfo, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 获取合同签署参数
     */
    public function contractGetSignerConfig()
    {
        $param = [
            'account' => '',                 //签署者
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractGetSignerConfig, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 查询合同签署者状态
     */
    public function contractGetSignerStatus()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractGetSignerStatus, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 下载合同文件
     */
    public function contractDownload()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractDownload, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 、获取预览页URL
     */
    public function contractGetPreviewURL()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractGetPreviewURL, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 创建合同
     */
    public function contractCreate()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractCreate, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 生成合同附页
     */
    public function contractCreateAttachment()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractCreateAttachment, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 下载合同附页文件
     */
    public function contractDownloadAttachment()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractDownloadAttachment, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 关键字定位签署合同
     */
    public function contractSignKeywords()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractSignKeywords, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 在线验签（通过合同ID和哈希值）
     */
    public function contractVerifyContractFileHash()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractVerifyContractFileHash, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 获取存证页URL
     */
    public function contractGetApplyArbitrationURL()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractGetApplyArbitrationURL, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 合同PDF文件上添加附件
     */
    public function distContractPdfAddAttachment()
    {
        $param = [
            'contractId' => '',              //合同编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::DistContractPdfAddAttachment, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 获取模版变量
     */
    public function templateGetTemplateVars()
    {
        $param = [
            'tid' => '',                //模版编号
            'isRetrieveAllVars' => '',  //是否获取所有变量,0 只返回变量的type 和 name；  1 返回变量的所有字段；
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::TemplateGetTemplateVars, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 通过模版生成合同文件
     */
    public function templateCreateContractPdf()
    {
        $param = [
            'account' => '',                //合同创建者账号
            'tid' => '',                    //模版编号
            'templateValues' => '',         //模版变量
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::TemplateCreateContractPdf, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 通过模版创建合同
     */
    public function contractCreateByTemplate()
    {
        $param = [
            'account' => '',               //合同创建者账号
            'tid' => '',                   //模版编号
            'templateToken' => '',         ///template/createContractPdf/ 返回的 templateToken
            'title' => '',                 //合同标题
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractCreateByTemplate, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 用模版变量签署合同
     */
    public function contractSignTemplate()
    {
        $param = [
            'account' => '',               //合同创建者账号
            'tid' => '',                   //模版编号
            'vars' => '',                  //模版变量值
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractSignTemplate, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 获取模版信息
     */
    public function templateGetTemplate()
    {
        $param = [
            'account' => '',               //合同创建者账号
            'tid' => '',                   //模版编号
            'templateToken' => '',         ///template/createContractPdf/ 返回的 templateToken
            'title' => '',                 //合同标题
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::TemplateGetTemplate, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }
    /**
     * 用模版变量的手动签
     */
    public function contractSendByTemplate()
    {
        $param = [
            'account' => '',               //合同创建者账号
            'signer' => '',                //指定给哪个用户看
            'tid' => '',                   //模版编号
            'varNames' => '',              //模板的变量名称
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::ContractSendByTemplate, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }
    /**
     * 获取创建模版的地址
     */
    public function pageTemplateCreate()
    {
        $param = [
            'account' => '',               //操作者的用户标识
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::PageTemplateCreate, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }
    /**
     * 获取编辑模版的地址
     */
    public function pageTemplateModify()
    {
        $param = [
            'account' => '',               //操作者的用户标识
            'tid' => '',               //模版编号
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::PageTemplateModify, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }
    /**
     * 获取开发者模版列表
     */
    public function templateGetTemplates()
    {
        $param = [
            'categoryName' => '',               //类别名称
            'pageSize' => '',               //每页显示的条数
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::TemplateGetTemplates, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }
    /**
     * 预览模板
     */
    public function pageTemplatePreview()
    {
        $param = [
            'tid' => '',               //模版编号
            'account' => '',               //操作者的用户标识
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::PageTemplatePreview, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }
    /**
     * 上传模板变量创建合同
     */
    public function distTemplateCreateContract()
    {
        $param = [
            'tid' => '',               //模版编号
            'account' => '',           //操作者的用户标识
            'templateValues' => '',    //模版变量
            'title' => '',             //合同标题
        ];

        //生成签名 + 组合api url
        $sign_api = $this->signApiUrl($param, self::DistTemplateCreateContract, 'post');

        //请求
        $res = $this->http_post($sign_api, $param);

        return $res;
    }

    /**
     * 生成签名 + 组合api url
     * @param $param
     * @param $uri
     * @param $http_type
     * @return string
     */
    public function signApiUrl($param, $uri, $http_type)
    {
        //生成签名
        $sign = $this->signCreate($param, $uri, $http_type);

        //组合api url
        $api_url = $this->apiUrl($uri, $sign);

        return $api_url;
    }

    /**
     * 生成签名
     */
    public function signCreate($param, $uri, $type = 'get')
    {
        //一、获取签名原文字符串

        $base_param = [
            'developerId' => $this->developerId,
            'rtick' => $this->rtick,
            'signType' => $this->signType,
        ];

        $sign_str = '';

        if ($type == 'post') {

            //需要签名计算的参数进行字典排序，得到一个字符串
            ksort($base_param);

            foreach ($base_param as $key => $val) {
                $sign_str .= $key . '=' . $val;
            }

            //把请求 path 附加到字符串后面
            $sign_str .= $uri;

            //计算request body的md5值
            $request_body = md5($param);

            //最终的签名原字符串
            $sign_str .= $request_body;
        } else {

            //需要签名计算的参数进行字典排序，得到一个字符串
            $base_param = array_merge($base_param, $param);

            ksort($base_param);

            foreach ($base_param as $key => $val) {
                $sign_str .= $key . '=' . $val;
            }

            //把请求 path 附加到字符串后面,最终的签名原字符串
            $sign_str .= $uri;

        }

        //二、rsa加密
        //获取私钥
        $private_key = $this->private_key;

        //生成签名(使用rsa算法：SHA1withRSA)
        $signature = openssl_sign($sign_str, $signature, $private_key) ? base64_encode($signature) : null;

//        $signature = urlencode($signature);

//        $signature = openssl_private_encrypt($sign_str,$signature, $private_key) ? base64_encode($signature) : null;

        return $signature;
    }

    /**
     * 组合api url
     * @param $uri
     * @param $sign
     * @return string
     */
    public function apiUrl($uri, $sign)
    {

        $url = self::ServerHost . $uri . '?';

        $url_params['sign'] = $sign;
        $url_params['developerId'] = $this->developerId;
        $url_params['rtick'] = $this->rtick;
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
     * 获取签名串
     * @param $args
     * @return
     */
    public function getRsaSign()
    {
        $pkeyid = openssl_pkey_get_private($this->private_key);
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

    private function _keyInit($rsa_pem, $pem_type = '')
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

        $this->private_key = $pem;
//        return $pem;
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
    public function sign_test()
    {
        echo 'sign contract333333';
    }

}
