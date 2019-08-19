<?php

function custom_warning_handler($errno, $errstr, $errfile, $errline, $errcontext) {
  //By returning true you indicate that you have handled the error. False pushes you back to the normal, default error handler
  switch ($errno) {
      case E_WARNING:
          echo "data: CustomWarning: $errstr, File: $errfile, OnLine: $errline\n\n";
          return true;
          break;
      case E_NOTICE:
          echo "data: CustomNotice: $errstr, File: $errfile, OnLine: $errline\n\n";
          return true;
          break;
      case E_ERROR:
          echo "data: CustomError: $errstr, File: $errfile, OnLine: $errline\n\n";
          return true;
          break;
      case E_ALL: //any other errors
          echo "data: UndentifiedCustomError: $errstr, File: $errfile, OnLine: $errline\n\n";
          return true;
          break;
      default:
          return false;
          break;
      }
  }
  set_error_handler('custom_warning_handler');

?>