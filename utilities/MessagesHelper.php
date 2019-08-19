<?php

class MessagesHelper {
  protected $handle;
  
  public function __construct() {
    // create/open file
    $this->handle = fopen('log/'.date("h-i-s-d-m-Y").'.html','w') or die('Unable to open file');
    fwrite($this->handle, "<link rel='stylesheet' href='../../assets/css/feed.css'>\n");
    fwrite($this->handle, "<meta charset=\"UTF-8\">");

    //flush buffer at start to make stream run consistent.
    if (ob_get_level() > 0) { ob_end_flush(); };
  }

  public function out( string $message='', string $class='') {
    $message = "<div class='$class'>$message</div>";
    echo "data: $message\n\n";
    flush();
    fwrite($this->handle, $message."\n");
  }

  public function info( string $message, string $property) {
    $message = $message;
    echo 'data: {"'. $property .'":"'. $message .'"}'."\n\n";
    flush();
    fwrite($this->handle, $message."\n");
  }
}

?>