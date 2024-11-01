<?php
namespace SPF_WCT;
/**
 * ストライプ決済API連携クラス
 */
class StripeAjx{	

	public function __construct() {
	
	}

	public function setAction(){

		// 注文確定
		add_action( 'wp_ajax_vs_capture_stripe', array( $this , 'vs_capture_stripe' ));
		add_action( 'wp_ajax_nopriv_vs_capture_stripe',array( $this , 'vs_capture_stripe' ));
		// 注文取り消し
		add_action( 'wp_ajax_vs_cancel_stripe', array( $this , 'vs_cancel_stripe' ));
		add_action( 'wp_ajax_nopriv_vs_cancel_stripe',array( $this , 'vs_cancel_stripe' ));
		// 返金
		add_action( 'wp_ajax_vs_refund_stripe', array( $this , 'vs_refund_stripe' ));
		add_action( 'wp_ajax_nopriv_vs_refund_stripe',array( $this , 'vs_refund_stripe' ));

	}

	protected function get_acting_settings() {
		global $usces;
		$acting_settings = ( isset( $usces->options['acting_settings']['stripe'] ) ) ? $usces->options['acting_settings']['stripe'] : array();
		return $acting_settings;
	}

	// 公開キー、シークレットキーを設定
	public function getActive_Stripekey(){
		$acting_opts =  $this->get_acting_settings();
		$active_stripe_key = '';
		$active_stripe_secretkey = '';

        if( $acting_opts['ope'] === 'public' ){
			// 本番環境
			$active_stripe_key = $acting_opts['merchant_id'];
			$active_stripe_secretkey = $acting_opts['merchanthash'];
        }else{
			// テスト環境
			$active_stripe_key = $acting_opts['test_merchant_id'];
			$active_stripe_secretkey = $acting_opts['test_merchanthash'];
        }

		return compact('active_stripe_key' , 'active_stripe_secretkey' );
	}

	public function getSecretKey(){
		$key = $this->getActive_Stripekey();
		return $key['active_stripe_secretkey'];
	}

	public function response( $result , $o ){

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode( array('error_code'=>  $o->getError_code(), 'res' => $o->is_success() ,'val' => $result ,'failure' => $o->getErrorDispMessage() , 'success' => $o->getSuccessDispMessage() ) );
		wp_die();
	}


	/**
	 * キャプチャ処理(売上請求)
	 *
	 * @return void
	 */
	public function vs_capture_stripe(){
	
		$chargeId = InputFilter::sanitize($_POST['cahrge_id']);
		$amount = !empty($_POST['amount']) ? InputFilter::sanitize($_POST['amount']) : 0;     // 返金額

		$o = new StripeOrder( $this->getSecretKey() );
		$result = $o->capture( $chargeId , $amount );
	    $this->response( $result , $o );

	}

	/**
	 * 支払いキャンセル(注文取消し)
	 *
	 * @return void
	 */
	public function vs_cancel_stripe(){

		$chargeId = InputFilter::sanitize($_POST['cahrge_id']);

		$o = new StripeOrder( $this->getSecretKey() );
		$result = $o->cancel( $chargeId );
		// 受注ステータスを”キャンセル”に更新
		// order_status
		$this->response( $result , $o );

	}

	/**
	 * 返金処理
	 *
	 * @return void
	 */
	public function vs_refund_stripe(){

		$chargeId = InputFilter::sanitize($_POST['cahrge_id']);
		$amount = !empty($_POST['amount']) ? InputFilter::sanitize($_POST['amount']) : 0;     // 返金額

		$o = new StripeOrder( $this->getSecretKey() );
		$result = $o->refund( $chargeId , $amount );
		// 全額返金でも受注ステータスは更新しない
		$this->response( $result , $o );

	}
	
}


?>