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
        $headers = ["User-Agent: " . self::USER_AGENT];
        curl_setopt($s, CURLOPT_HTTPHEADER, $headers);
        return $this->doMethod($s);
    }

    public function setPayloadParameters() {
        $this->postParams = file_get_contents('php://input');
    }
    public function doPost($url, $queryString = NULL)
    {
        $url = is_null($queryString) ? $url : $url . '?' . $queryString;
        $s = curl_init($url);
        curl_setopt($s, CURLOPT_CUSTOMREQUEST, "POST");
        $this->setPayloadParameters();

        curl_setopt($s, CURLOPT_POSTFIELDS, $this->postParams);
        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($s, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($this->postParams),
            'User-Agent: ' . self::USER_AGENT
            )
        );

        return $this->doMethod($s);


    }

    public function doPut($url, $queryString = NULL)
    {
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, $url);
        curl_setopt($s, CURLOPT_CUSTOMREQUEST, 'PUT');
        if (!is_null($queryString)) {
            curl_setopt($s, CURLOPT_POSTFIELDS, parse_str($queryString));
        }

        $headers = ["User-Agent: " . self::USER_AGENT];
        curl_setopt($s, CURLOPT_HTTPHEADER, $headers);

        return $this->doMethod($s);
    }

    public function doDelete($url, $queryString = NULL)
    {
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, is_null($queryString) ? $url : $url . '?' . $queryString);
        curl_setopt($s, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!is_null($queryString)) {
            curl_setopt($s, CURLOPT_POSTFIELDS, parse_str($queryString));
        }
        $headers = ["User-Agent: " . self::USER_AGENT];
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
        curl_close($s);

        list($this->responseHeaders, $content) = $this->decodeOut($out);
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
        foreach ($data as $line) {
            if (trim($line) == '') {
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