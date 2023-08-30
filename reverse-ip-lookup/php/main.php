<?php

class ReverseIpLookup {
    const REVERSE_LOOKUP_URL = 'https://domains.yougetsignal.com/domains.php';

    public function __construct(
        private string $host_file,
        private string $output_file
    ) { }

    public function start() {
        $time_start = microtime(true);

        foreach($this->read_file($this->host_file) as $host) {
            $response = $this->do_lookup($host);

            if (!$response) continue;

            $response_string = implode("\n", $response);

            echo sprintf("url: %s\n", $hosts);
            echo "hosts: \n";
            echo sprintf("%s\n", $response_string);

            $this->save_content($response_string);
        }

        $time_end = microtime(true);
        echo sprintf("Total time: %02d mins.", ($time_end - $time_start)/60);
    }

    private function do_lookup($url) {
        $url_parts = parse_url($url);

        if (preg_match("/http/", $url) && isset($url_parts['host'])) {
            $url = $url_parts['host'];
        }

        if ($result = $this->in_cache($url)) {
            return $result;
        }

        $response = $this->request($url);
        $result = $this->parse_response($response);

        if (!$result) return null;

        $this->save_cache($url, $result);
        return $result;
    }

    private function request($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::REVERSE_LOOKUP_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => sprintf('remoteAddress=%s&key=&_=', $url),
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36'
        ]);

        $response = curl_exec($ch);
        return $response;
    }

    private function parse_response($response) {
        if (!preg_match("/Success/", $response)) {
            return null;
        }

        $data = json_decode($response, true);
        $domains = $data['domainArray'];
        $final_domain_list = [];

        foreach ($domains as $domain) {
            $final_domain_list[] = $domain[0];
        }

        return $final_domain_list;
    }

    private function in_cache($url) {
        $key = md5($url);

        if (file_exists($key)) {
            return explode("\n", file_get_contents($key));
        }
    }

    private function save_cache($url, $content) {
        $key = md5($url);
        file_put_contents(sprintf('cache/%s', $key), implode("\n", $content));
    }

    private function read_file($file) {
        $fp = fopen($file, 'r');
        while (!feof($fp)) yield fgets($fp);
    }

    private function save_content($content) {
        file_put_contents($this->$output_file, $content, FILE_APPEND);
    }
}

$rlookup = new ReverseIpLookup($argv[1], $argv[2]);
$rlookup->start();