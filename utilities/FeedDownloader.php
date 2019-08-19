<?php
namespace FeedDownloader {
  
  class Downloader {
    public static function run( $feed_url ) {
        $headers[] = 'User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n';

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $feed_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        //curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $output = curl_exec($ch);
        if ($output === false)  { echo 'Curl error: '.curl_error($ch); }
        else { 'Status: '.curl_getinfo($ch)['http_code']; $code = curl_getinfo($ch)['http_code']; }
        curl_close($ch);

        return (object) ['out' => $output, 'code' => $code];
    }

    public static function run_local( $feed_path ) {
      $xml = simplexml_load_file( $feed_path );
      return $xml;
    }

    public static function run_local_json( $feed_path ) {
      $string = file_get_contents($feed_path);
      $json = json_decode($string);
      return $json;
    }
    
  }
}



?>