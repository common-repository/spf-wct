<?php
namespace SPF_WCT;

class InputFilter{

     private function __construct(){}

     public static function sanitize( $arr ){
     if( empty($arr) ) return '';
     if( is_array($arr) ){
          $arr = array_map(function( $str ){
               return self::filter($str);
          },$arr);
     }else{
          $arr = self::filter($arr);
     } 
     return $arr;
     }

     public static function filter( $str ){
          if( empty($str) ) return '';
          return sanitize_text_field( $str );
     }

     public static function is_blank( $data ){
          if( $data == null || !isset($data) || empty($data) ) {
               return true;
          }
          return false;
     }

}
?>