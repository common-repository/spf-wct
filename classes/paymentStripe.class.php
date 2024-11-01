<?php
namespace SPF_WCT;
/**
 *
 * @class    STRIPE_SETTLEMENT
 * @author   t.tsukamoto.
 * @version  1.0.0
 * @since    1.0.0
 */
class STRIPE_SETTLEMENT
{
	/**
	 * Instance of this class.
	 */
	protected static $instance = null;

    protected $acting_card;
	protected $paymod_id;			//決済代行会社ID
	protected $pay_method;			//決済種別
	protected $acting_name;			//決済代行会社略称
	protected $acting_formal_name;	//決済代行会社正式名称
	protected $acting_company_url;	//決済代行会社URL
	protected $field_meta_keys = ['stripeStatus' ,'resultCode','stripeID','fAmount','captureFlg'];
	protected $active_stripe_key;      //設定された公開キー
	protected $active_stripe_secretkey;//設定されたシークレットキー
	protected $stripeOrder;            // ストライプ決済注文インスタンス
	protected $acting_stripe_cardData; // 決済処理結果データ

	protected $error_mes;


	public function __construct() {

		$this->paymod_id = 'stripe';
		$this->pay_method = array(
			'acting_stripe_card'
		);
        $this->acting_card = 'acting_stripe_card';//デフォルトの決済方法
		$this->acting_name = 'ストライプ';
		$this->acting_formal_name = 'ストライプ決済';
		$this->acting_company_url = 'https://stripe.com/jp';
		$this->initialize_data();

		$payments = get_option( 'usces_available_settlement' );

		// クレジット決済設定にStripe決済を追加
		if( isset($payments['stripe']) == false ){
			// ※毎回呼ばれるので改善の余地あり
			$usces_available_settlement = array_merge($payments , ['stripe' => 'ストライプ']);
			update_option( 'usces_available_settlement', $usces_available_settlement );
		}

		// クレジット設定より保存された 公開、シークレットキーを適用
		$this->setActive_Stripekey();

		// 管理画面側フック
		if( is_admin() ) {
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );
		}

        // カードを有効に設定している場合
		if( $this->is_activate_card() ) {

			add_action( 'usces_filter_completion_settlement_message', array( $this, 'completion_settlement_message' ), 11, 2 );// 決済完了時のメッセージ
			

			if( is_admin() ) { 

				// 受注管理など	で実行
				add_action( 'admin_enqueue_scripts', array( $this , 'stripe_admin_enqueue_scripts') , 11 );
				add_filter( 'usces_filter_settle_info_field_meta_keys', array( $this, 'settlement_info_field_meta_keys' ) );
				add_filter( 'usces_filter_settle_info_field_keys', array( $this, 'settlement_info_field_keys' ) );
				///add_filter( 'usces_filter_settle_info_field_value', array( $this, 'settlement_info_field_value' ), 10, 3 );
				add_action('usces_action_order_edit_form_detail_top', array( $this , 'stripe_action_order_edit_form_detail_top') , 11 , 3); //受注データ編集
				add_action('usces_action_endof_order_edit_form', array( $this , 'stripe_usces_action_endof_order_edit_form') , 11 , 2);     //受注データ編集


			} else {

				// フロント側で実行
				add_filter( 'usces_filter_check_acting_return_results', array( $this, 'check_acting_return_results' ), 99, 1 ); //決済後の受注データチェック
				add_filter( 'usces_filter_confirm_inform', array( $this, 'confirm_inform' ), 99, 6 );// 確認画面の注文ボタンカスタマイズ
		
				if( defined( 'WCEX_COUPON' ) ) {
					//add_filter( 'wccp_filter_coupon_inform', array( $this, 'point_inform' ), 10, 5 );
				}

				add_action( 'usces_action_acting_processing', array( $this, 'acting_processing' ), 11, 3 );// 決済処理カスタマイズ
				add_filter( 'usces_filter_check_acting_return_duplicate', array( $this, 'check_acting_return_duplicate' ), 11, 2 ); // 重複オーダー禁止処理
				add_action( 'usces_action_reg_orderdata', array( $this, 'register_orderdata' ) ,11,1);
				add_filter( 'usces_filter_get_error_settlement', array( $this, 'error_page_message' ) );

			}


		}
	}


	public static function get_instance() {
		if( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Initialize
	 */
	public function initialize_data() {

		$options = get_option( 'usces' );
		if( !isset( $options['acting_settings'] ) || !isset( $options['acting_settings']['stripe'] ) ) {
			$options['acting_settings']['stripe']['merchant_id'] = '';
			$options['acting_settings']['stripe']['merchanthash'] = '';
            $options['acting_settings']['stripe']['test_merchant_id'] = '';
            $options['acting_settings']['stripe']['test_merchanthash'] = '';
			$options['acting_settings']['stripe']['ope'] = '';// 稼働環境
			$options['acting_settings']['stripe']['receipt_anme'] = '';	
			$options['acting_settings']['stripe']['mailaddress'] = '';
			$options['acting_settings']['stripe']['card_activate'] = 'off';
			$options['acting_settings']['stripe']['card_capture_flag'] = 'capture';// 即時決済
			$options['acting_settings']['stripe']['conv_activate'] = 'off';// 使う予定ない
			$options['acting_settings']['stripe']['conv_timelimit'] = '60';// 使う予定ない
			update_option( 'usces', $options );
		}
	}

	/**
	 * 注文後の決済処理結果データを取得
	 */
	public function getStripeSettlementData( $order_id ){
		global $usces;

		if( empty($this->acting_stripe_cardData) ){

			$stripeData = $usces->get_order_meta_value('acting_stripe_card', $order_id );
			$dataArray = [];
			if( is_serialized($stripeData) ){
				$dataArray = maybe_unserialize( $stripeData );
				$this->acting_stripe_cardData = $dataArray;
			}

			return $dataArray;

		}else{

			return $this->acting_stripe_cardData;

		}
	
		
	}


	/**
	 * 管理画面側
	 * 重複インスタンス生成防止
	 */
	private function getStripeOrder(){
		
		if( $this->stripeOrder == null || ($this->stripeOrder instanceof StripeOrder == false) ){

			$this->stripeOrder = new StripeOrder( $this->get_active_secret_stripekey() );
			$this->stripeOrder->setEnvCondition( $this->getOpeState() );

		}

		return $this->stripeOrder;
	}


	/**
	 * 決済IDの存在確認
	 *
	 * @param [type] $order_id
	 * @return boolean
	 */
	private function hasStripeSettlementData( $order_id ){
		$stripeData = $this->getStripeSettlementData( $order_id );
		return ( !empty($stripeData) && !empty($stripeData['stripeID']) );
	}



    /**
	 * 
	 * 管理画面 ⇒ 受注詳細フック ⇒ HTML出力
	 *
	 * @param [type] $data
	 * @param [type] $csod_meta
	 * @param [type] $filter_args
	 * @return void
	 */
	public function stripe_action_order_edit_form_detail_top( $data, $csod_meta, $filter_args ){
			
		if( $this->hasStripeSettlementData( $data['ID'] ) ){
			global $usces;
			//注文当初のカード売上フラグ
			$capture_stated = $usces->get_order_meta_value('captureFlg', $data['ID']);
			$capture = true;
			if( $capture_stated == 'auhtorize' ){
				$capture = false;
			}

			$stripeData = $this->getStripeSettlementData( $data['ID'] );
			$stripeOrder = $this->getStripeOrder();
			echo $stripeOrder->order_edit_table_row($stripeData['stripeID'] , $capture );

		}

	}


	/**
	 * 管理画面 ⇒ 受注データ編集 フッター
	 * @fook   usces_action_endof_order_edit_form
	 *
	 */
	public function stripe_usces_action_endof_order_edit_form( $data , $action_args ){
		
		if( $this->hasStripeSettlementData( $data['ID'] ) ){

			$stripeData = $this->getStripeSettlementData( $data['ID'] );
			$stripeOrder = $this->getStripeOrder();
			echo $stripeOrder->order_edit_footer_script( $data );

		}

	}

	/**
	 * @fook admin_enqueue_scripts
	 *
	 * @return void
	 */
	public function stripe_admin_enqueue_scripts(){

		$order_action = ( isset( $_GET['order_action'] ) ) ? InputFilter::sanitize($_GET['order_action']) : '';
		$admin_page = ( isset( $_GET['page'] ) ) ? InputFilter::sanitize($_GET['page']) : '';
		$acting_opts =  $this->get_acting_settings();

		switch( $admin_page ):

		case 'usces_orderlist':
			// 受注データ編集画面
			if( $order_action === 'edit') :

				wp_enqueue_style( 'spfw_vue-js-modal-css', SPFW_PLUGIN_URL.'/js/vue-js-modal/styles.css',array(), '', false);
				wp_enqueue_style( 'spfw_vue-modal-css', SPFW_PLUGIN_URL.'/css/modal.css',array(), '', false);

				if( $acting_opts['ope'] == 'public' ){
					// 本番モード
					wp_enqueue_script( 'spfw_vue-js', SPFW_PLUGIN_URL.'/js/vue.js',array(), '', false);
					wp_enqueue_script( 'spfw_vue-modal-js', SPFW_PLUGIN_URL.'/js/vue-js-modal/index.js',array(), '', false);

				}else{
					// 開発モード
					wp_enqueue_script( 'spfw_vue-js', SPFW_PLUGIN_URL.'/js/vue_debug.js',array(), '', false);
					wp_enqueue_script( 'spfw_vue-modal-js', SPFW_PLUGIN_URL.'/js/vue-js-modal/index.js',array(), '', false);

				}

			endif;
			break;
		endswitch;
	}


	
	/**
	 * 受注データから取得する決済情報のキー
	 * @fook   usces_filter_settle_info_field_meta_keys
	 * @param  $keys
	 * @return array $keys
	 */
	public function settlement_info_field_meta_keys( $keys ) {

		$keys = array_merge( $keys, $this->field_meta_keys );
		return $keys;
	}

	/**
	 * 受注編集画面に表示する決済情報のキー
	 * @fook   usces_filter_settle_info_field_keys
	 * @param  $keys
	 * @return array $keys
	 */
	public function settlement_info_field_keys( $keys ) {

		$keys = array_merge( $keys, $this->field_meta_keys );
		return $keys;
	}


    /**
	 * 処理日付生成
	 *
	 * @return string 'YYYYMMDD'
	 */
	protected function get_transaction_date() {

		$transactiondate = date_i18n( 'Ymd', current_time( 'timestamp' ) );
		return $transactiondate;
	}

	protected function getOpeState()
	{
		$acting_opts =  $this->get_acting_settings();
		return $acting_opts['ope'];
	}


	/**
	 * 購入完了メッセージ
	 * @fook   usces_filter_completion_settlement_message
	 * @param  $html, $usces_entries
	 * @return string $html
	 */
	public function completion_settlement_message( $html, $usces_entries ) {
		global $usces;

		if( isset( $_REQUEST['acting'] ) && 'acting_stripe_card' == $_REQUEST['acting'] ) {

		}


		return $html;

	}

	/**
	 * 決済エラーメッセージ
	 * @fook   usces_filter_get_error_settlement
	 * @param  $html
	 * @return string $html
	 */
	public function error_page_message( $html ) {

		if( isset( $_REQUEST['acting'] ) && ( 'acting_stripe_card' == $_REQUEST['acting'] ) ) {
			if( 'acting_stripe_card' == $_REQUEST['acting'] ) {

				echo ErrorBundle::card_error_message();

			} else {
				// それ以外
			}
		}
		return $html;
	}


	/**
	 *  カスタマイズ
	 *  welcartに処理結果通知処理
	 * 引数を上書き
	 *  @fuck usces_check_acting_return
	 * flow : acting_processing ⇒ check_acting_return_results
	 */
	public function check_acting_return_results( $results ){

		global $usces;
		$entry = $usces->cart->get_entry();
		$acting = ( isset($_GET['acting']) ) ? InputFilter::sanitize($_GET['acting']) : '';

		// Stripe決済の場合
		if( $acting == $this->acting_card ){

			if( isset($_GET['acting_return']) && (int)$_GET['acting_return'] <= 0 || empty($entry) ){
				//エラー全般
				$req = InputFilter::sanitize($_REQUEST);
				Logger::stripe_log( $acting.' error エラー全般: '.print_r($req,true) );
				$results[0] = 0;
				$results['reg_order'] = false;

			}else if(isset($_GET['duplicate']) && $_GET['duplicate'] == 1){
				// 重複注文エラー
				$req = InputFilter::sanitize($_REQUEST);
				Logger::stripe_log( $acting.' duplicate 重複オーダーエラー: '.print_r($req,true) );
				$results[0] = 'duplicate';
				$results['reg_order'] = false;

			}else{
				// 成功
				if( $this->getOpeState() !== 'public' ){
					//注文成功
					////Logger::stripe_log( $acting.' entry 注文成功 : '.print_r($entry, true) );
				}				
				$results[0] = 1;
				$results['reg_order'] = true;
				$results['wctid'] = InputFilter::sanitize($_GET['wctid']);

			}

		}		

		return $results;
	}

	


  
	/**
	 * 決済処理
	 * @fook   usces_action_acting_processing
	 * @param  $acting_flg $post_query
	 * @return -
	 * @flow : 
	 */
	public function acting_processing( $acting_flg, $post_query ){

		// 自身のカードブランドではない場合は処理を拒否
		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return;
		}

		$acting_opts = $this->get_acting_settings();

		// 設定が有効の場合は決済処理を実行する
		if( 'on' == $acting_opts['card_activate'] ) {

			$this->stripe_processing( $post_query );
		}


	}

	/**
	 *  新規追加
	 *  Stripe決済処理
	 */
	public function stripe_processing( $post_query ){

			global $usces;
			$usces_entries = $usces->cart->get_entry();
			$cart = $usces->cart->get_cart();


			// ショッピングカート情報の存在チェック
			if( !$usces_entries || !$cart ) {

				// カートページへリダイレクト
				$log = array( 'acting' => $this->acting_card, 'result'=>'SESSION ERROR', 'data'=>$cart );
				usces_save_order_acting_error( $log );
				$result_data = array(
					'acting' =>  $this->acting_card,
					'acting_return' => '0' ,        // 失敗値をセット
					'result' => 0
				);
				wp_redirect( add_query_arg( $result_data, USCES_CART_URL ) );
				exit();

			}


			// CSRF対策
			$key = $this->acting_card;
			if(  !isset($_POST[$key]) || !wp_verify_nonce( $_POST[$key], $this->paymod_id ) || empty($_POST['stripeToken']) ) {
				wp_redirect( USCES_CART_URL );
				exit();
			}

			// 在庫チェック
			$usces->error_message = $usces->zaiko_check();
			if ( '' != $usces->error_message || 0 == $usces->cart->num_row() ) {
				wp_redirect( USCES_CART_URL );
				exit();
			}
		
			$order_id = InputFilter::sanitize($_POST['ORDER_ID']);
			$stripeToken = InputFilter::sanitize($_POST['stripeToken']);
			$response = $this->stripe_connect( $oder_id , $stripeToken );

			//決済正常処理
			if( isset($response['charge']['id']) && !empty($response['charge']['id']) ){

				$result_data = array(
					'acting' =>  $this->acting_card,
					'acting_return' => '1',     //成功値
					'wctid' => $response['charge']['id'],
					'result' => 1,
					'statusCode' => $response['statusCode'],
					'captureFlg' => $response['captureFlg']
				);
				wp_redirect( add_query_arg( $result_data, USCES_CART_URL ) );
				exit();

			}else{

				Logger::stripe_log( 'stripe card : stripe_processing エラー値セット ' . print_r($post_query,true) );

				// 決済処理エラー
				$result_data = array(
					'acting' =>  $this->acting_card,
					'acting_return' => '0' ,        // 失敗値
					'result' => 0,
					'ecode'        => $response['statusCode'],
					'decline_code' => $response['decline_code']
				);

				$delim = '?';
				header( "location: " . USCES_CART_URL . $delim . http_build_query( $result_data ),true , 302);
				exit();

			}

	

	}


	
	/**
	 * 重複オーダー禁止処理
	 * @fook   usces_filter_check_acting_return_duplicate
	 * @param  $trans_id $results
	 * @return string $trans_id Stripe決済IDから注文番号の引き当て
	 */
	public function check_acting_return_duplicate( $trans_id, $results ) {
		global $usces;

		$acting = ( isset( $_GET['acting'] ) ) ? InputFilter::sanitize($_GET['acting']) : '';
		// カードメソッドにより分岐
		switch( $acting ) {
		case 'acting_stripe_card':
			$trans_id = ( isset( $_REQUEST['wctid'] ) ) ? InputFilter::sanitize($_REQUEST['wctid']) : '';
			break;
		}
		return $trans_id;
	}


	/**
	 * 受注データ登録(メタ情報)
	 * Call from usces_reg_orderdata() and usces_new_orderdata().
	 * @fook   usces_action_reg_orderdata
	 * @param  @array $cart, $entry, $order_id, $member_id, $payments, $charging_type, $results
	 * @return -
	 * @echo   -
	 */
	public function register_orderdata( $args ) {
		global $usces;
		extract( $args );

		$acting_flg = $payments['settlement'];
		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return;
		}

		if( !$entry['order']['total_full_price'] ) {
			return;
		}	

		if( isset( $_GET['acting'] ) && $this->acting_card == $_GET['acting'] && !empty($order_id) && isset($_GET['wctid']) && !empty($_GET['wctid']) ) {
			$usces->set_order_meta_value( 'orderId', $order_id, $order_id ); // 関連付け注文ID
			$usces->set_order_meta_value( 'wc_trans_id', InputFilter::sanitize($_GET['wctid']), $order_id );
			$usces->set_order_meta_value( 'trans_id', InputFilter::sanitize($_GET['wctid']), $order_id ); // ※重複オーダー禁止処理判定値
			// 決済処理結果データの保存
			$data['stripeStatus'] =  'success';
			$data['resultCode'] = InputFilter::sanitize($_GET['statusCode']);
			$data['stripeID'] = InputFilter::sanitize($_GET['wctid']);
			$data['fAmount'] = $entry['order']['total_full_price'];
			$data['captureFlg'] = InputFilter::sanitize($_GET['captureFlg']);
			$usces->set_order_meta_value( InputFilter::sanitize($_GET['acting']) , serialize( $data ), $order_id );

		}

	}

	/**
	 * クレジット決済設定よりカード売上フラグ値を取得
	 * オーソリ・即時売上
	 * false  オーソリ
	 * true キャプチャ
	 * 
	 */
	public function getCard_capture_state(){

		$acting_opts = $this->get_acting_settings();
		$flg = '';
		switch ( $acting_opts['card_capture_flag'] ) {
			case 'auhtorize':
				$flg = 'auhtorize';
				break;
			case 'capture';
				$flg = 'capture';
			break;
		}
		return $flg;

	}
     
	/**
	 * 新規追加
	 * Stripe決済処理
	 */
    public function stripe_connect( $order_id , $stripeToken ){

		global $usces;

		$acting_opts = $this->get_acting_settings(); // 有効カード設定情報	
		$CAPTURE = $this->getCard_capture_state();   // 有効カード設定 オーソリ可否
		$order_date = $this->get_transaction_date(); // 注文日付

		$stripeOrder = new StripeOrder( $this->get_active_secret_stripekey() );
		return $stripeOrder->frontOrder( $stripeToken , $order_date , $acting_opts , $CAPTURE  );

    }

	

	/**
	 * 決済有効判定
	 * 引数が指定されたとき、支払方法で使用している場合に「有効」とする
	 * @param  ($type)
	 * @return boolean
	 */
	public function is_validity_acting( $type = '' ) {

		$acting_opts = $this->get_acting_settings();
		if( empty( $acting_opts ) ) {
			return false;
		}

		$payment_method = usces_get_system_option( 'usces_payment_method', 'sort' );
		$method = false;

		switch( $type ) {
		case 'card':
			foreach( $payment_method as $payment ) {
				if( 'acting_stripe_card' == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_card() ) {
				return true;
			} else {
				return false;
			}
			break;

		default:
			if( 'on' == $acting_opts['activate'] ) {
				return true;
			} else {
				return false;
			}
		}
	}

	/**
	 * クレジットカード決済有効判定
	 * @param  -
	 * @return boolean $res
	 */
	public function is_activate_card() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) && 
			( isset( $acting_opts['card_activate'] ) && ( 'on' == $acting_opts['card_activate'] ) ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**
	 * コンビニ決済有効判定
	 * @param  -
	 * @return boolean $res
	 */
	public function is_activate_conv() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}


	/**
	 * 決済オプション登録・更新
	 * @fook   usces_action_admin_settlement_update
	 * @param  -
	 * @return -
	 */
	public function settlement_update() {
		global $usces;

		if( $this->paymod_id != $_POST['acting'] ) {
			return;
		}

		$this->error_mes = '';
		$options = get_option( 'usces' );
		$payment_method = usces_get_system_option( 'usces_payment_method', 'settlement' );

		unset( $options['acting_settings']['stripe'] );
		$options['acting_settings']['stripe']['merchant_id'] = ( isset( $_POST['merchant_id'] ) ) ? InputFilter::sanitize( $_POST['merchant_id'] ) : '';
		$options['acting_settings']['stripe']['merchanthash'] = ( isset( $_POST['merchanthash'] ) ) ? InputFilter::sanitize( $_POST['merchanthash'] ) : '';

        $options['acting_settings']['stripe']['test_merchant_id'] = ( isset( $_POST['test_merchant_id'] ) ) ? InputFilter::sanitize( $_POST['test_merchant_id'] ) : '';
		$options['acting_settings']['stripe']['test_merchanthash'] = ( isset( $_POST['test_merchanthash'] ) ) ? InputFilter::sanitize( $_POST['test_merchanthash'] ) : '';

		$options['acting_settings']['stripe']['receipt_anme'] = ( isset( $_POST['receipt_anme'] ) ) ? InputFilter::sanitize( $_POST['receipt_anme'] ) : '';

		$options['acting_settings']['stripe']['ope'] = ( isset( $_POST['ope'] ) ) ? InputFilter::sanitize($_POST['ope']) : '';
		
		$options['acting_settings']['stripe']['mailaddress'] = ( isset( $_POST['mailaddress'] ) ) ? InputFilter::sanitize( $_POST['mailaddress'] ) : '';
		$options['acting_settings']['stripe']['card_activate'] = ( isset( $_POST['card_activate'] ) ) ? InputFilter::sanitize($_POST['card_activate']) : 'off';
		$options['acting_settings']['stripe']['card_capture_flag'] = ( isset( $_POST['card_capture_flag'] ) ) ? InputFilter::sanitize($_POST['card_capture_flag']) : '';
		$options['acting_settings']['stripe']['conv_activate'] = ( isset( $_POST['conv_activate'] ) ) ? InputFilter::sanitize($_POST['conv_activate']) : 'off';
		$options['acting_settings']['stripe']['conv_timelimit'] = ( isset( $_POST['conv_timelimit'] ) ) ? InputFilter::sanitize($_POST['conv_timelimit']) : '60';

		if( $options['acting_settings']['stripe']['ope'] == 'public'){

			if( InputFilter::is_blank( $options['acting_settings']['stripe']['merchant_id'] ) ) {
				$this->error_mes .= '※公開キー[本番環境]を入力してください<br />';
				$options['acting_settings']['stripe']['ope'] == 'test';
			}
			if( InputFilter::is_blank( $options['acting_settings']['stripe']['merchanthash'] ) ) {
				$this->error_mes .= '※シークレットキー[本番環境]を入力してください<br />';
				$options['acting_settings']['stripe']['ope'] == 'test';
			}

		}else{

			if( InputFilter::is_blank( $options['acting_settings']['stripe']['test_merchant_id'] ) ) {
			$this->error_mes .= '※公開キー[TEST環境]を入力してください<br />';
			}
			if( InputFilter::is_blank( $options['acting_settings']['stripe']['test_merchanthash'] ) ) {
				$this->error_mes .= '※シークレットキー[TEST環境]を入力してください<br />';
			}

		}
        
		if( InputFilter::is_blank( $options['acting_settings']['stripe']['ope'] ) ) {
			$this->error_mes .= '※稼働環境を選択してください<br />';
		}
		if( 'on' == $options['acting_settings']['stripe']['card_activate'] ) {
			if( InputFilter::is_blank( $options['acting_settings']['stripe']['card_capture_flag'] ) ) {
				$this->error_mes .= '※カード売上フラグを選択してください<br />';
			}
		}

		if( '' == $this->error_mes ) {
			$usces->action_status = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			if( 'on' == $options['acting_settings']['stripe']['card_activate'] ) {
				$options['acting_settings']['stripe']['activate'] = 'on';
				$options['acting_settings']['stripe']['regist_url'] = "";
				$options['acting_settings']['stripe']['payment_url'] = "";
				$toactive = array();
				if( 'on' == $options['acting_settings']['stripe']['card_activate'] ) {
					$usces->payment_structure['acting_stripe_card'] = 'カード決済（'.$this->acting_name.'）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_stripe_card' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_stripe_card'] );
				}
			
				usces_admin_orderlist_show_wc_trans_id();
				if( 0 < count( $toactive ) ) {
					$usces->action_message .= __( "Please update the payment method to \"Activate\". <a href=\"admin.php?page=usces_initial#payment_method_setting\">General Setting > Payment Methods</a>", 'usces' );
				}
			} else {
				$options['acting_settings']['stripe']['activate'] = 'off';
				unset( $usces->payment_structure['acting_stripe_card'] );
			}
			$deactivate = array();
			foreach( $payment_method as $settlement => $payment ) {
				if( !array_key_exists( $settlement, $usces->payment_structure ) ) {
					if( 'deactivate' != $payment['use'] ) {
						$payment['use'] = 'deactivate';
						$deactivate[] = $payment['name'];
						usces_update_system_option( 'usces_payment_method', $payment['id'], $payment );
					}
				}
			}
			if( 0 < count( $deactivate ) ) {
				$deactivate_message = sprintf( __( "\"Deactivate\" %s of payment method.", 'usces' ), implode( ',', $deactivate ) );
				$usces->action_message .= $deactivate_message;
			}
		} else {
			$usces->action_status = 'error';
			$usces->action_message = __( 'Data have deficiency.', 'usces' );
			$options['acting_settings']['stripe']['activate'] = 'off';
			unset( $usces->payment_structure['acting_stripe_card'] );
			$deactivate = array();
			foreach( $payment_method as $settlement => $payment ) {
				if( in_array( $settlement, $this->pay_method ) ) {
					if( 'deactivate' != $payment['use'] ) {
						$payment['use'] = 'deactivate';
						$deactivate[] = $payment['name'];
						usces_update_system_option( 'usces_payment_method', $payment['id'], $payment );
					}
				}
			}
			if( 0 < count( $deactivate ) ) {
				$deactivate_message = sprintf( __( "\"Deactivate\" %s of payment method.", 'usces' ), implode( ',', $deactivate ) );
				$usces->action_message .= $deactivate_message.__( "Please complete the setup and update the payment method to \"Activate\".", 'usces' );
			}
		}
		ksort( $usces->payment_structure );
		update_option( 'usces', $options );
		update_option( 'usces_payment_structure', $usces->payment_structure );
	}

	/**
	 * クレジット決済設定画面タブ
	 * @fook   usces_action_settlement_tab_title
	 * @param  -
	 * @return -
	 * @echo   html
	 */
	public function settlement_tab_title() {

		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( $this->paymod_id, (array)$settlement_selected ) ) {
			echo '<li><a href="#uscestabs_'.$this->paymod_id.'">'.$this->acting_name.'</a></li>';
		}
	}

	/**
	 * クレジット決済設定画面フォーム
	 * @fook   usces_action_settlement_tab_body
	 * @param  -
	 * @return -
	 * @echo   html
	 */
	public function settlement_tab_body() {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( $this->paymod_id, (array)$settlement_selected ) ):
?>
	<div id="uscestabs_stripe">
	<div class="settlement_service"><span class="service_title"><?php echo $this->acting_formal_name; ?></span></div>
	<?php if( isset( $_POST['acting'] ) && 'stripe' == $_POST['acting'] ): ?>
		<?php if( '' != $this->error_mes ): ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php elseif( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ): ?>
		<div class="message">十分にテストを行ってから運用してください。</div>
		<?php endif; ?>
	<?php endif; ?>
    <style>
    .wide-text{
        width:99%;
        font-size:11px;
    }
    </style>
	<form action="" method="post" name="stripe_form" id="stripeform">
		<table class="settle_table">
            <!-- 本番環境-->
			<tr>
				<th><a class="explanation-label" id="label_ex_merchant_id_stripe">[本番環境]<br>公開可能キー</a></th>
				<td><input name="merchant_id" type="text" id="merchant_id_stripe" value="<?php echo esc_html( isset( $acting_opts['merchant_id'] ) ? $acting_opts['merchant_id'] : '' ); ?>" maxlength="128" class="wide-text" /></td>
			</tr>
			<tr id="ex_merchant_id_stripe" class="explanation"><td colspan="2"><?php echo $this->acting_name; ?>の管理画面から発行される公開可能キー</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_merchanthash_stripe">[本番環境]<br>シークレットキー</a></th>
				<td><input name="merchanthash" type="text" id="merchanthash_stripe" value="<?php echo esc_html(isset( $acting_opts['merchanthash'] ) ? $acting_opts['merchanthash'] : '' ); ?>" class="wide-text" /></td>
			</tr>
			<tr id="ex_merchanthash_stripe" class="explanation"><td colspan="2"><?php echo $this->acting_name; ?>の管理画面から発行されるシークレットキー</td></tr>
			
            
			<!-- テスト環境-->
            <tr>
				<th><a class="explanation-label" id="label_ex_merchant_id_stripe2">[テスト環境]<br>公開可能キー</a></th>
				<td><input name="test_merchant_id" type="text" id="merchant_id_stripe2" value="<?php echo esc_html( isset( $acting_opts['test_merchant_id'] ) ? $acting_opts['test_merchant_id'] : '' ); ?>" maxlength="128" class="wide-text" /></td>
			</tr>
			<tr id="ex_merchant_id_stripe2" class="explanation"><td colspan="2"><?php echo $this->acting_name; ?>の管理画面から発行される公開可能キー</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_merchanthash_stripe2">[テスト環境]<br>シークレットキー</a></th>
				<td><input name="test_merchanthash" type="text" id="merchanthash_stripe2" value="<?php echo esc_html(isset( $acting_opts['test_merchanthash'] ) ? $acting_opts['test_merchanthash'] : '' ); ?>" class="wide-text" /></td>
			</tr>
			<tr id="ex_merchanthash_stripe2" class="explanation"><td colspan="2"><?php echo $this->acting_name; ?>の管理画面から発行されるシークレットキー</td></tr>


			<tr>
				<th><a class="explanation-label" id="label_ex_ope_stripe">稼働環境</a></th>
				<td><label><input name="ope" type="radio" id="ope_stripe_1" value="test"<?php if( isset( $acting_opts['ope'] ) && $acting_opts['ope'] == 'test' ) echo ' checked="checked"'; ?> /><span>テスト環境</span></label><br />
					<label><input name="ope" type="radio" id="ope_stripe_2" value="public"<?php if( isset( $acting_opts['ope'] ) && $acting_opts['ope'] == 'public' ) echo ' checked="checked"'; ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_ope_stripe" class="explanation"><td colspan="2">動作環境を切り替えます。</td></tr>

		</table>
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><label><input name="card_activate" type="radio" id="card_activate_stripe_1" value="on"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="card_activate" type="radio" id="card_activate_stripe_2" value="off"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_receipt_anme">領収書のタイトル</a></th>
				<td><input name="receipt_anme" type="text" id="receipt_anme" value="<?php echo esc_html( isset( $acting_opts['receipt_anme'] ) ? $acting_opts['receipt_anme'] : '' ); ?>" maxlength="64" class="wide-text" /></td>
			</tr>
			<tr id="ex_receipt_anme" class="explanation"><td colspan="2"><?php echo $this->acting_name; ?> 側に設定する領収書のタイトル</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_card_capture_flag_stripe">カード売上フラグ</a></th>
				<td><label><input name="card_capture_flag" type="radio" id="card_capture_flag_stripe_0" value="auhtorize"<?php if( isset( $acting_opts['card_capture_flag'] ) && $acting_opts['card_capture_flag'] == 'auhtorize' ) echo ' checked'; ?> /><span>オーソリ(仮売上)</span></label><br />
					<label><input name="card_capture_flag" type="radio" id="card_capture_flag_stripe_1" value="capture"<?php if( isset( $acting_opts['card_capture_flag'] ) && $acting_opts['card_capture_flag'] == 'capture' ) echo ' checked'; ?> /><span>即時売上</span></label>
				</td>
			</tr>
			<tr id="ex_card_capture_flag_stripe" class="explanation"><td colspan="2">決済の処理方式を指定します。</td></tr>	
			
		</table>

		<input name="acting" type="hidden" value="stripe" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="<?php echo $this->acting_name; ?>の設定を更新する" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<ol>
			<li>
				<a href="<?php echo $this->acting_company_url; ?>" target="_blank"><?php echo $this->acting_name; ?>の公式サイト</a>
			</li>	
			<li>
				<a href="https://dashboard.stripe.com/" target="_blank">Stripe管理画面</a>
			</li>
			<li>
				<a href="https://stripe.com/docs/testing" target="_blank">テストカード情報 </a>	
			</li>
		</ol>		
	</div>
	</div><!--uscestabs_stripe-->
<?php
		endif;
	}

	

	/**
	 * 決済オプション取得
	 * @param  -
	 * @return array $acting_settings
	 */
	protected function get_acting_settings() {
		global $usces;

		$acting_settings = ( isset( $usces->options['acting_settings'][$this->paymod_id] ) ) ? $usces->options['acting_settings'][$this->paymod_id] : array();
		return $acting_settings;
	}


    /**
	 * 内容確認ページ [注文する] ボタン
	 * usces_filter_confirm_inform
	 *
	 * @param  string $html
	 * @param  array  $payments
	 * @param  string $acting_flg
	 * @param  string $rand
	 * @param  string $purchase_disabled
	 * @return string $html
	 */
	public function confirm_inform( $html, $payments, $acting_flg, $rand, $purchase_disabled ) {

		global $usces;

		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return $html;
		}

		$usces_entries = $usces->cart->get_entry();
		if( !$usces_entries['order']['total_full_price'] ) {
			return $html;
		}

        switch( $acting_flg ) {
			case 'acting_stripe_card':
				
				$usces->save_order_acting_data( $rand );
				usces_save_order_acting_data( $rand );

				$checkout_button_value = apply_filters( 'usces_filter_confirm_checkout_button_value', __( 'Checkout', 'usces' ) );
				$dir = SPFW_PLUGIN_URL;
				$card_icons = [ 
					$dir . '/images/icons/icon-visa.png' ,
					$dir . '/images/icons//icon_mastercard.png',
					$dir . '/images/icons/icon_american-express.png'
				];
				$card_icons_url = apply_filters( 'spf_wct_card_icons_output', $card_icons );
				
				$html = '<div class="stripe-frame">';
				$html .= '<div class="form-row">';
				$html .= '<label for="card-element">';
				$html .= '<ul class="cre-card">';
				foreach ($card_icons_url as $key => $url) {
					$html .= sprintf('<li><img src="%s"></li>', InputFilter::sanitize($url));
				}
				$html .= '</ul>';
				$html .= '</label>';
				$html .= '<div id="card-element"></div>';
				$html .= '<div id="card-errors" role="alert"></div>';
				$html .= '</div>';
				$html .= '</div>';

				$amount = usces_crform( $usces_entries['order']['total_full_price'], false, false, 'return', false);
				$sendurl = USCES_CART_URL;

				$html .= '<form id="purchase_form" name="purchase_form" action="'.$sendurl.'" method="post" onKeyDown="if (event.keyCode == 13) {return false;}">
					<input type="hidden" name="ORDER_ID" value="'.esc_attr( $rand ).'">
					<input type="hidden" name="AMOUNT" value="'.esc_attr( $amount ).'">';
					
				$html .= wp_nonce_field( $this->paymod_id , $this->acting_card ,false);

				$html .= '<div class="send"><input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.$checkout_button_value.'"'.apply_filters('usces_filter_confirm_nextbutton', '').$purchase_disabled.' /></div>';
				$html .= '</form>';
				$html .= '<form action="'.USCES_CART_URL.'" method="post" onKeyDown="if (event.keyCode == 13) {return false;}">
					<div class="send"><input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__('Back', 'usces').'"'.apply_filters('usces_filter_confirm_prebutton', NULL).' /></div>
					<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flg ).'">';
				$html .= '</form>';

				break;

			}

        return $html;
    }

	// 公開キー、シークレットキーを設定
	public function setActive_Stripekey(){
		$acting_opts =  $this->get_acting_settings();

		if( empty($acting_opts) ){
			return false;
		}

        if( $acting_opts['ope'] == 'public' ){
			// 本番環境
			$this->active_stripe_key = $acting_opts['merchant_id'];
			$this->active_stripe_secretkey = $acting_opts['merchanthash'];
        }else{
			// テスト環境
			$this->active_stripe_key = $acting_opts['test_merchant_id'];
			$this->active_stripe_secretkey = $acting_opts['test_merchanthash'];
        }
	}


    /**
     * Stripe決済JS
     */
    public function setCardElement(){

        global $usces, $usces_entries;
        // 選択されている「支払い方法」がクレジットカードでない場合かまたは合計金額ゼロの場合は何もしない
        $payments = usces_get_payments_by_name($usces_entries['order']['payment_name']);
        if( 'acting' != substr($payments['settlement'], 0, 6) || 0 == $usces_entries['order']['total_full_price'] ){
            return false;
        }

        // JSスクリプト配置
		spfw_put_footer_script( $this->get_active_stripe_key() );

    }

	// 設定されている公開キー取得
	public function get_active_stripe_key(){
		return $this->active_stripe_key;
	}
	// 設定されているシークレットキー取得
	public function get_active_secret_stripekey(){
		return $this->active_stripe_secretkey;
	}


} //class