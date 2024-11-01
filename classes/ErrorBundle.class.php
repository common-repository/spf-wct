<?php
namespace SPF_WCT;

class ErrorBundle implements StirpeConfig
{

	public static function card_error_message(){

        $html = '';

		if( isset($_GET['ecode']) && !empty($_GET['ecode']) ){

				$err_code = InputFilter::sanitize( $_GET['ecode'] );
               
				$html .= '<div class="support_box">';
				$html .= '<br>エラーコード：'. $err_code;

                if( isset($_GET['decline_code']) && !empty($_GET['decline_code']) ){
                     $decline_code = InputFilter::sanitize( $_GET['decline_code'] );
                     $html .= '<br>拒否コード：' . $decline_code;
                }

				// 日本語メッセージ
				if( isset(self::STRIPE_ERR_JP_MSG[$err_code]) ){
				
					$msg =  self::STRIPE_ERR_JP_MSG[$err_code];
					
					$html .= '<br>エラー内容：' . $msg . '<br>';

				}else{
					$html .= '<br>エラー内容: 不明 <br>';
				}
				
				$html .= '<br><a style="color:black;" href="'.USCES_CUSTOMER_URL.'">》もう一度決済を行う</a><br><br>';

		}else {

			$err_code = InputFilter::sanitize( $_GET['ecode'] );

			$html .= '<br>エラーコード：'. $err_code;
			$html .= '<br>
			カード番号を再入力する場合はこちらをクリックしてください。<br>
			<br>
			<a style="color:black;" href="'.USCES_CUSTOMER_URL.'&re-enter=1">》カード番号の再入力 </a><br>';
		}

        $outhtml = $html;

        return apply_filters('spf_wct_card_error_message', $outhtml );

	}


}
?>
