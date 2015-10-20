<?php
namespace RestProxy;

class CurlWrapper
{
    const HTTP_OK = 200;
    const USER_AGENT = 'okinet/rest-proxy';

    private $responseHeaders = [];
    private $status;
    private $postParams = [];

    public function doGet($url, $queryString = NULL)
    {
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, is_null($queryString) ? $url : $url . '?' . $queryString);
        curl_setopt($s,CURLOPT_ENCODING , "gzip");

        $headers = array_merge(
            array(
                'User-Agent: ' . self::USER_AGENT
            ), $this->getClientHeaders()
        );

        curl_setopt($s, CURLOPT_HTTPHEADER, $headers);
        return $this->doMethod($s);
    }

    public function setPayloadParameters() {
        $this->postParams = file_get_contents('php://input');
    }

    public function getClientHeaders() {
        $result = array();
        $disabled = array('Content-Type', 'Content-Length', 'User-Agent', 'Host', 'Connection', 'Origin');
        foreach(getallheaders() as $name=>$value) {
            if(!in_array($name, $disabled)) {
                $result[] = $name.': '.$value;
            }
        }
        return $result;
    }

    public function doPost($url, $queryString = NULL)
    {
        $url = is_null($queryString) ? $url : $url . '?' . $queryString;
        $s = curl_init($url);
        curl_setopt($s, CURLOPT_CUSTOMREQUEST, "POST");
        $this->setPayloadParameters();

        curl_setopt($s, CURLOPT_POSTFIELDS, $this->postParams);
        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($s, CURLOPT_ENCODING , "gzip");

        $headers = array_merge(
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($this->postParams),
                'User-Agent: ' . self::USER_AGENT
            ), $this->getClientHeaders()
        );

        curl_setopt($s, CURLOPT_HTTPHEADER, $headers);

        return $this->doMethod($s);


    }

    public function doPut($url, $queryString = NULL)
    {
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, $url);
        curl_setopt($s, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($s, CURLOPT_ENCODING , "gzip");
        $this->setPayloadParameters();

        curl_setopt($s, CURLOPT_POSTFIELDS, $this->postParams);
        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);


        $headers = array_merge(
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($this->postParams),
                'User-Agent: ' . self::USER_AGENT
            ), $this->getClientHeaders()
        );

        curl_setopt($s, CURLOPT_HTTPHEADER, $headers);

        return $this->doMethod($s);
    }

    public function doDelete($url, $queryString = NULL)
    {
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, is_null($queryString) ? $url : $url . '?' . $queryString);
        curl_setopt($s, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($s, CURLOPT_ENCODING , "gzip");
        if (!is_null($queryString)) {
            curl_setopt($s, CURLOPT_POSTFIELDS, parse_str($queryString));
        }

        $headers = array_merge(
            array(
                'User-Agent: ' . self::USER_AGENT
            ), $this->getClientHeaders()
        );

        curl_setopt($s, CURLOPT_HTTPHEADER, $headers);

        return $this->doMethod($s);
    }

    private function doMethod($s)
    {
        curl_setopt($s, CURLOPT_HEADER, TRUE);
        curl_setopt($s, CURLOPT_RETURNTRANSFER, TRUE);
        $out                   = curl_exec($s);
        $this->status          = curl_getinfo($s, CURLINFO_HTTP_CODE);
        $this->responseHeaders = curl_getinfo($s, CURLINFO_HEADER_OUT);

        $header_size = curl_getinfo($s, CURLINFO_HEADER_SIZE);
        $header = substr($out, 0, $header_size);
        $content = substr($out, $header_size);




        list($this->responseHeaders, $content) = array(explode("\n", $header), $content);
        if ($this->status != self::HTTP_OK) {
            throw new \Exception("http error: {$this->status}", $this->status);
        }



        return $content;
    }

    private function decodeOut($out)
    {


        // It should be a fancy way to do that :(
        $headersFinished = FALSE;
        $headers         = $content = [];
        $data            = explode("\n", $out);

        foreach ($data as $key => $line) {
            if (trim($line) == '' && (array_key_exists($key+1, $data) && strpos($data[$key+1], 'HTTP') === false)) {
                $headersFinished = TRUE;
            } else {
                if ($headersFinished === FALSE && strpos($line, ':') > 0) {
                    $headers[] = $line;
                }

                if ($headersFinished) {
                    $content[] = $line;
                }
            }
        }

        return [$headers, implode("\n", $content)];
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getHeaders()
    {
        return $this->responseHeaders;
    }
}