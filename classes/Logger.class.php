<?php 
namespace SPF_WCT;

class Logger
{
 /**
 * ログ出力設定
 */
    public static function stripe_log($message){
        $log_message = sprintf("%s:%s\n", date_i18n('Y-m-d H:i:s'), print_r($message,true));
        error_log($log_message, 3, SPFW_LOG_OUTPUT );
    }

}


