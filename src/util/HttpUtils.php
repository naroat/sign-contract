<?php
namespace RanPack\SignContract;

class HttpUtils
{
    const DEFAULT_CONNECT_TIMEOUT = 5; //默认连接超时
    const DEFAULT_READ_TIMEOUT = 6000; //默认读取超时
    const MAX_REDIRECT_COUNT = 10;
    
    private static $_instances;
    
    private $_default_user_agent = '';
    private $_response_headers = '';

    public function setDefaultUserAgent($user_agent) {
        $this->_default_user_agent = $user_agent;
        return $this;
    }
    
    public function get($url, array $headers = array(), $auto_redirect = true, $cookie_file = null) 
    {
        return $this->_request($url, "GET", null, null, $headers, $auto_redirect, $cookie_file);
    }
    
    public function post($url, $post_data = null, $post_files = null, array $headers = array(), $cookie_file = null)
    {
        return $this->_request($url, "POST", $post_data, $post_files, $headers, $cookie_file);
    }
    
    private function _headerCallback($ch, $data)
    {
        $this->_response_headers .= $data;
        return strlen($data);
    }
    
    private function _request($url, $method = "GET", $post_data = null, $post_files = null, array $headers = array(), $auto_redirect = true, $cookie_file = null)
    {
        //$url = 'http://localhost/ssq/test.php';
        if (strcasecmp($method, "POST") == 0) {
            $method = 'POST';
        }
        else {
            $method = 'GET';
        }

        if (!empty($post_files) && !is_array($post_files))
        {
            $post_files = array();
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::DEFAULT_CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::DEFAULT_READ_TIMEOUT);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        
        if (!empty($cookie_file))
        {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);     
        }
        
        // set location
        if ($auto_redirect)
        {
            curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, self::MAX_REDIRECT_COUNT);
        }
        
        // set callback
        $this->_response_headers = '';
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, '_headerCallback'));
        
        // set https
        if (0 == strcasecmp('https://', substr($url, 0, 8)))
        {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);    
        }
        
        // set headers
        if (!is_array($headers))
        {
            $headers = array();
        }
        if (!empty($this->_default_user_agent))
        {
            $has_user_agent = false;
            foreach ($headers as $line)
            {
                $row = explode(':', $line);
                $name = trim($row[0]);
                if (strcasecmp($name, 'User-Agent') == 0)
                {
                    $has_user_agent = true;
                    break;
                }
            }
            if (!$has_user_agent)
            {
                $headers[] = "User-Agent: " . $this->_default_user_agent;
            }
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // set post
        if ($method == 'POST')
        {

            curl_setopt($ch, CURLOPT_POST, 1);
            if (!empty($post_data) || !empty($post_files))
            {
                $post = array();
                if (!empty($post_files)) {
                    foreach ($post_files as $name => $file_path) {
                        if (is_file($file_path)) {
                            $post[$name] = "@{$file_path}";    
                        }
                    }
                    if (!is_array($post_data)) {
                        $tmp_post_data_list = implode('&', $post_data);
                        $post_data = array();
                        foreach ($tmp_post_data_list as $line) {
                            $item = explode('=', $line);
                            $name = $item[0];
                            $value = isset($item[1]) ? rawurldecode($item[1]) : '';
                            $post[$name] = $value;
                        }
                    }
                }
                else {
                    $post = $post_data;
                }
                
                if (!empty($post)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                }
            }
        }
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $http_code = $info['http_code'];
        $errno = 0;
        $errmsg = '';
        
        $errno = curl_errno($ch);
            $errmsg = curl_error($ch);
        
        if (false === $response) {
            $errno = curl_errno($ch);
            $errmsg = curl_error($ch);
        }
        curl_close($ch);
        
        if ($errno != 0) {
            throw new \Exception("Http Request Wrong: {$errno} - {$errmsg}");
        }
        
        $result = array(
            'http_code' => $http_code,
            'errno' => $errno,
            'errmsg' => $errmsg,
            'headers' => $this->_response_headers,
            'response' => $response,
        );
        
        return $result;
    }
}