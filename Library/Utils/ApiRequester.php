<?php

namespace FF\Library\Utils;

use FF\Framework\Common\Code;
use FF\Framework\Common\Format;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Log;

class ApiRequester
{
    protected $url;
    protected $method;
    protected $headers;
    protected $timeout;
    protected $format;
    protected $handle;

    public function __construct($options = array())
    {
        $this->url = $options['url'] ?? '';
        $this->headers = $options['headers'] ?? array();
        $this->method = strtoupper($options['method'] ?? 'GET');
        $this->timeout = isset($options['timeout']) ? $options['timeout'] : 5;
        $this->format = $options['format'] ?? '';
    }

    public function __destruct()
    {
        if ($this->handle) {
            curl_close($this->handle);
        }
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getHandle()
    {
        if ($this->handle) {
            return $this->handle;
        }

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

        if ($this->format == Format::JSON) {
            $this->headers[] = 'Content-Type: application/json';
        }

        if ($this->headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        }

        $this->handle = $curl;

        return $curl;
    }

    public function setOption($option, $value)
    {
        curl_setopt($this->getHandle(), $option, $value);
    }

    public function requestData($parameter = array(), $method = '')
    {
        if (!$this->url) {
            FF::throwException(Code::FAILED, 'Api url is empty');
        }

        $url = $this->url;
        $method = $method ?: $this->method;
        $curl = $this->getHandle();

        switch (strtoupper($method)) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($parameter) {
                    $postData = $parameter;
                    if ($this->format == Format::JSON) {
                        $postData = json_encode($parameter);
                    }
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
                }
                break;
            case "GET":
                curl_setopt($curl, CURLOPT_POST, 0);
                if ($parameter) {
                    if (strpos($this->url, '?')) {
                        $url .= '&' . http_build_query($parameter);
                    } else {
                        $url .= '?' . http_build_query($parameter);
                    }
                }
                break;
        }

        curl_setopt($curl, CURLOPT_URL, $url);

        $retry = 3;
        $result = null;
        $time = _microtime();

        while ($retry) { //增加请求失败重试机制
            $result = curl_exec($curl);
            if ($result !== false) break;
            $retry--;
        }

        $cost = (int)(_microtime() - $time);
        if ($cost > 200) {
            Log::info([$cost, $url, $parameter, $result], 'api.log');
        }

        if (curl_errno($curl)) {
            Log::error([$url, $parameter, curl_error($curl)], 'api.log');
        }

        return $result;
    }

    public function requestAsync($parameter = array(), $method = '')
    {
        if (!$this->url) return;

        $cmd = 'curl';
        $url = $this->url;
        $method = strtoupper($method ?: $this->method);

        if ($parameter) {
            if ($method == 'POST') {
                $cmd .= ' -X POST';
                if ($this->format == Format::JSON) {
                    $cmd .= ' -H "Content-Type: application/json"';
                    $content = str_replace('\\', '\\\\', json_encode($parameter));
                    $content = str_replace('"', '\"', $content);
                    $cmd .= ' -d "' . $content . '"';
                } else {
                    $cmd .= ' -d "' . http_build_query($parameter) . '"';
                }
            } else {
                if (strpos($this->url, '?')) {
                    $url .= '&' . http_build_query($parameter);
                } else {
                    $url .= '?' . http_build_query($parameter);
                }
            }
        }

        $cmd .= ' --connect-timeout 3 --max-time ' . $this->timeout;
        $cmd .= ' "' . $url . '"';

        exec_command($cmd, true);
    }
}
