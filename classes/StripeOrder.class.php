<?php
namespace SPF_WCT;
use DateTime;
use DateTimeZone;
use Exception;

class StripeOrder implements StirpeConfig
{
	private $error_message = '';            // Stripe側出力エラー内容
	private $error_code = '';               // Stripe側出力エラーコード
	private $error_type = '';               // Stirpe側エラークラス
	private $api_key = '';
	private $id = 0;                        // 決済ID(チャージID)
	private $customer = 0;                  // 顧客ID
	private $amount = 0;                    // 決済時の請求金額(売上)
	private $amount_captured = 0;           // キャプチャ金額
	private $amount_refunded = 0;           // 返金が成功した累計金額
	private $zankin = 0;                    // 残金
	private $invoice = '';                  // 請求書
	private $refunds = 0;                   // 払い戻し金
	private $receipt_url  = '';             // 最新の領収書URL(メール文に記載OK)
	private $metadata = array();
	private $balance_transaction_id  = '';  // 支払いの詳細ID
	private $transaction_fee = 0;           // 手数料
	private $net_sales = 0;                 // 純売上
	private $net = 0;                       // ver 7.75 以降で導入値
	private $captured;                      // キャプチャ状態(bool値)
	private $active_lang = 'jp';            // 利用する国際エイリアス
	private $chargeExpired;                 // オーソリー期限フラグ
	private $env = '';

	private $success = false;


	/**
	 * オーソリー期限
	 * カードブランドに応じて可変的
	 * 
	 * true =  期限切れ
	 * false = 取引可能
	 * @return boolean
	 */
	public function isChargeExpired(){
		return $this->chargeExpired;
	}

	// キャプチャ状態
	public function getCaptured()
	{
		return $this->captured;
	}

	// 全額返金済み = true
	public function refunded(){
		return (  $this->getAmount() === $this->getAmount_refunded() );
	}

	// キャプチャ済の金額(売上)
	public function getAmountCaptured(){
		return $this->amount_captured;
	}

	public function getZankin()
	{
	    return $this->zankin;
	}

	public function getCustomer()
	{
	    return $this->customer;
	}

	public function getId()
	{
	    return $this->id;
	}
	public function getAmount()
	{
	    return $this->amount;
	}

	public function getAmount_refunded()
	{
	    return $this->amount_refunded;
	}

	public function getMetadata()
	{
	    return $this->metadata;
	}

	public function getReceipt_url()
	{
	    return $this->receipt_url;
	}


	public function getRefunds()
	{
	    return $this->refunds;
	}


	public function getBalance_transaction_ID()
	{
	    return $this->balance_transaction_id;
	}

	public function get_transaction_fee()
	{
	    return $this->transaction_fee;
	}

	public function getNet_sales()
	{
	    return $this->net_sales;
	}

	public function getNet()
	{
	    return $this->net;
	}

	public function getErrorDispMessage(){

		if( empty($this->error_code) ){
			return '';
		}

		$msg = '';
		switch ($this->active_lang) {
			case 'jp':
				$msg = self::STRIPE_ERR_JP_MSG[$this->error_code];
				break;
			
			default:
				$msg = self::STRIPE_ERR_JP_MSG[$this->error_code];
			break;
		}
		return $msg;
	}

	public function getSuccessDispMessage()
	{
		if( $this->is_success() ){
			return '正常に処理が完了しました!';
		}
		return '';
	}

	public function getErrorMessage(){
		return $this->error_message;
	}

	public function getError_type(){
		return $this->error_type;
	}

	public function getError_code(){
		return $this->error_code;
	}

	public function hasError() : bool
	{
		return ( !empty($this->error_message) );
	}

	public function is_success() : bool
	{
		return $this->success;
	}

	public function setEnvCondition( $state ){
		$this->env = $state;
	}

	private function error_reset(){

		$this->error_code = '';
		$this->error_type = '';
		$this->error_message = '';
		
	}
	


	function __construct( $api_key )
	{

        $this->api_key = $api_key;

	}

	/**
	 * タイムスタンプから日時変換
	 */
	public function unixtimeToDate( $unixtime ){
		$date = new DateTime();
		$date->setTimezone(new DateTimeZone('Asia/Tokyo'));
		$date->setTimestamp( $unixtime );
		return $date->format('Y/m/d H:i');
	}

	/**
	 * StripeAPI連携処理 エラーログ出力
	 *
	 * @param [type] $title
	 * @return void
	 */
	public function setErrorLog( $title ){

		$this->success = false;

		$log = [];

		if( is_array($this->error_message) ){
			$log = [
			'stripe card log' => $title,
			'cid'            => $this->getId(),
			'error_code'     => $this->error_code,
			'error_type'     => $this->error_type,
			'error_message'  => print_r($this->error_message,true)
			];

		}else{

			$log = [
			'stripe card log' => $title,
			'cid'            => $this->getId(),
			'error_code'     => $this->error_code,
			'error_type'     => $this->error_type,
			'error_message'  => $this->error_message
			];
		}

		Logger::stripe_log( $log );
	
	}

	/**
	 * StripeAPIインスタンス生成
	 * 共通処理
	 *
	 * @return void
	 */
	private function getStripeInstanse(){

		$stripe = null;
		$this->error_message = '';

		try{

			$stripe = new \Stripe\StripeClient(
				$this->api_key
			);
		
	
		} catch (\Stripe\Exception\AuthenticationException $e) {

			$this->error_type = 'AuthenticationException';
			$this->error_message = $e->getError();
			$this->error_code = 'authentication_error';

		} catch (\Stripe\Exception\ApiConnectionException $e) {

			$this->error_type = 'ApiConnectionException';
			$this->error_message = $e->getError();
			$this->error_code = 'api_connection_error';

		} catch (\Stripe\Exception\ApiErrorException $e) {
			
			$this->error_type = 'ApiErrorException';
			$this->error_message = $e->getError();
			$this->error_code = 'api_error';
	
		} catch (Exception $e) {

			$this->error_message = $e->getMessage();
		
		}finally{

			if( !empty( $this->error_message ) ){
				$this->setErrorLog('StripeOrder::getStripeInstanse');
			}
			$this->error_reset();

		}
		

		return $stripe;
	}


	/**
	 * 取引イベント取得
	 *
	 * @return void
	 */
	public function getEventLog( $chargeId = ''){

		
		$eventData = [];
		$this->id = $chargeId;

		try{

			if( empty($this->api_key) )  throw new EmptyApiKeyException();

			$stripe = $this->getStripeInstanse();

			$eventData = $stripe->events->all(['limit' => 20]);


		} catch (EmptyApiKeyException $e) {

			$this->error_code = 'not_api_key';
			$this->error_type = 'EmptyApiKeyException';
			$this->error_message = $e->getMessage();

		} catch(\Stripe\Exception\CardException $e) {
					
				$this->error_type = 'CardException';
				$this->error_message = $e->getError();
				$this->error_code = $e->getStripeCode();

		} catch (\Stripe\Exception\RateLimitException $e) {

			$this->error_type = 'RateLimitException';
			$this->error_message = $e->getError();
			$this->error_code = 'rate_limit_error';

		} catch (\Stripe\Exception\InvalidRequestException $e) {

			$this->error_type = 'InvalidRequestException';
			$this->error_message = $e->getError();
			$this->error_code = 'invalid_request_error';
	
		} catch (\Stripe\Exception\ApiErrorException $e) {
			
			$this->error_type = 'ApiErrorException';
			$this->error_message = $e->getError();
			$this->error_code = 'api_error';	

		} catch (Exception $e) {


			$this->error_message = $e->getMessage();
		
		}finally{

			if( !empty( $this->error_message ) ){
				$this->setErrorLog('StripeOrder::events');
			}else{
				$this->success = true;
			}
		}

		return $eventData;

	}



	/**
	 *
	 * 管理画面 ⇒ 受注データ編集
	 *
	 * @param integer $chargeId
	 * @return void
	 */
	public function get( $chargeId = '' ){

		
		$this->id = $chargeId;
		$stripe = [];

        try {


				if( empty($this->api_key) )  throw new EmptyApiKeyException();

				$stripe = $this->getStripeInstanse();

				$result = $stripe->charges->retrieve(
					$chargeId
				);

				$this->captured = (bool)$result['captured'];
				$this->amount = (int)$result['amount'];
				$this->zankin = (int)$result['amount'];
				$this->amount_captured = (int)$result['amount_captured'];
				$this->amount_refunded = (int)$result['amount_refunded'];
				$this->id = $chargeId;
				$this->receipt_url = $result['receipt_url'];
				$this->refunds = $result['refunds'];
				$this->metadata = $result['metadata'];
				$this->customer = $result['customer'];
				$this->balance_transaction_id = $result['balance_transaction'];

				// 返金済完了 かつ キャプチャ済みなら取引終了
				$this->chargeExpired = ( (bool)$result['refunded'] === TRUE && (bool)$result['captured'] === FALSE );
			

				$refunds_list = $this->refunds['data'];

				if( !empty($refunds_list) ) :
					$zankin = (int)$result['amount'];
					foreach ($refunds_list as $n => $val) :
						// 返金が確定している場合
						if( $val['object'] === 'refund' && $val['status'] === 'succeeded' ){
							$zankin -= (int)$val['amount'];
						}

					endforeach;
					$this->zankin = $zankin;
				endif;

			//残金に変更があったら注文履歴残高を更新する??

			} catch (EmptyApiKeyException $e) {

				$this->error_code = 'not_api_key';
				$this->error_type = 'EmptyApiKeyException';
				$this->error_message = $e->getMessage();

			} catch(\Stripe\Exception\CardException $e) {
					
				$this->error_type = 'CardException';
				$this->error_message = $e->getError();
				$this->error_code = $e->getStripeCode();

			} catch (\Stripe\Exception\RateLimitException $e) {

				$this->error_type = 'RateLimitException';
				$this->error_message = $e->getError();
				$this->error_code = 'rate_limit_error';

			} catch (\Stripe\Exception\InvalidRequestException $e) {

				$this->error_type = 'InvalidRequestException';
				$this->error_message = $e->getError();
				$this->error_code = 'invalid_request_error';
		
			} catch (\Stripe\Exception\ApiErrorException $e) {
				
				$this->error_type = 'ApiErrorException';
				$this->error_message = $e->getError();
				$this->error_code = 'api_error';	

			} catch (Exception $e) {


			$this->error_message = $e->getMessage();
		
		}finally{

			if( !empty( $this->error_message ) ){
				$this->setErrorLog('StripeOrder::get()::retrieve');
				return false;
			}else{
				$this->success = true;
			}
		}


		// トランザクション履歴を取得 ⇒ トランザクションが発生した場合のみ実行
		if( isset($result['balance_transaction']) && !empty($result['balance_transaction']) ){


			try{

				$balance_transaction = $stripe->balanceTransactions->retrieve( 
					$result['balance_transaction'] 
				);
				// トランザクション料金
				$this->transaction_fee = (int)$balance_transaction['fee'];
				// 純売上計算
				$this->net_sales = (int)$this->zankin - (int)$balance_transaction['fee'];
				$this->net = (int)$balance_transaction['net'];


			} catch(\Stripe\Exception\CardException $e) {
					
				$this->error_type = 'CardException';
				$this->error_message = $e->getError();
				$this->error_code = $e->getStripeCode();

			} catch (\Stripe\Exception\RateLimitException $e) {

				$this->error_type = 'RateLimitException';
				$this->error_message = $e->getError();
				$this->error_code = 'rate_limit_error';

			} catch (\Stripe\Exception\InvalidRequestException $e) {

				$this->error_type = 'InvalidRequestException';
				$this->error_message = $e->getError();
				$this->error_code = 'invalid_request_error';
		
			} catch (\Stripe\Exception\ApiErrorException $e) {
				
				$this->error_type = 'ApiErrorException';
				$this->error_message = $e->getError();
				$this->error_code = 'api_error';	

			} catch (Exception $e) {


				$this->error_message = $e->getMessage();
			
			}finally{

				if( !empty( $this->error_message ) ){
					$this->setErrorLog('StripeOrder::get()::balance_transaction');
				}else{
					$this->success = true;
				}
			}

		}

        return $result;

	}


	/**
	 * 返金処理
	 * 全額返金後に再度実行すると例外がスローされる
	 *
	 * @param [type] $chargeId
	 * @param integer $amount
	 * @param string $description
	 * @return void
	 */
	public function refund( $chargeId , $amount = 0 ){

		$refund = array();
		$this->id = $chargeId;
		

		try{

			if( empty($this->api_key) )  throw new EmptyApiKeyException();

			$stripe = $this->getStripeInstanse();

			$stripe->refunds->create([
			    'charge' => $chargeId,
			    'amount' => $amount
			]);

		} catch (EmptyApiKeyException $e) {

			$this->error_code = 'not_api_key';
			$this->error_type = 'EmptyApiKeyException';
			$this->error_message = $e->getMessage();

		} catch(\Stripe\Exception\CardException $e) {
					
			$this->error_type = 'CardException';
			$this->error_message = $e->getError();
			$this->error_code = $e->getStripeCode();

		} catch (\Stripe\Exception\RateLimitException $e) {

			$this->error_type = 'RateLimitException';
			$this->error_message = $e->getError();
			$this->error_code = 'rate_limit_error';

		} catch (\Stripe\Exception\InvalidRequestException $e) {

			$this->error_type = 'InvalidRequestException';
			$this->error_message = $e->getError();
			$this->error_code = 'invalid_request_error';
	
		} catch (\Stripe\Exception\ApiErrorException $e) {
			
			$this->error_type = 'ApiErrorException';
			$this->error_message = $e->getError();
			$this->error_code = 'api_error';

		} catch (Exception $e) {

			$this->error_message = $e->getMessage();
		
		}finally{

			if( !empty( $this->error_message ) ){
				$this->setErrorLog('StripeOrder::refund');
			}else{
				$this->success = true;
			}
		}

        return $refund;

	}



    /**
	 * キャプチャ処理
	 * 
	 * 仮売上金額より上回る金額は指定できない
	 * ※仮売上金額からの減額のみ
	 * 
	 * @param [type] $chargeId
	 * @param integer $amount  返金額
	 * @return void
	 */
	public function capture( $chargeId , $amount = 0 ){

		$this->id = $chargeId;
		$response = array();	
		
		$this->get( $chargeId );

		// 初回請求額がを上回る金額指定をはじく(Stirpe側で受付ないため)

		try{

			 if( empty($this->api_key) )  throw new EmptyApiKeyException();

			 $stripe = $this->getStripeInstanse();

			if( !empty($amount)){
				// 返金+キャプチャ処理
				$response = $stripe->charges->capture(
					$chargeId,
					[
						'amount' => (int)$amount
					]
				);

			}else{

				$response = $stripe->charges->capture(
					$chargeId
				);

			}

		} catch (EmptyApiKeyException $e) {

			$this->error_code = 'not_api_key';
			$this->error_type = 'EmptyApiKeyException';
			$this->error_message = $e->getMessage();

		} catch(\Stripe\Exception\CardException $e) {
				
			$this->error_type = 'CardException';
			$this->error_message = $e->getError();
			$this->error_code = $e->getStripeCode();

		} catch (\Stripe\Exception\RateLimitException $e) {

			$this->error_type = 'RateLimitException';
			$this->error_message = $e->getError();
			$this->error_code = 'rate_limit_error';

		} catch (\Stripe\Exception\InvalidRequestException $e) {

			$this->error_type = 'InvalidRequestException';
			$this->error_message = $e->getError();
			$this->error_code = 'invalid_request_error';
	
		} catch (\Stripe\Exception\ApiErrorException $e) {
			
			$this->error_type = 'ApiErrorException';
			$this->error_message = $e->getError();
			$this->error_code = 'api_error';

		} catch (Exception $e) {

			$this->error_message = $e->getMessage();
		
		}finally{

			if( !empty( $this->error_message ) ){
				$this->setErrorLog('StripeOrder::getStripeInstanse');
			}else{
				$this->success = true;
			}
		}

        return $response;


	}


	/**
	 * 注文キャンセル(全額返金)
	 * 全額返金後に再度実行すると例外がスローされる
	 *
	 * @param [type] $chargeId
	 * @param integer $amount
	 * @param string $description
	 * @return void
	 */
	public function cancel( $chargeId ){

		$this->id = $chargeId;
		$refund = array();

		// 履歴を取得・セット
		$this->get( $chargeId );

		// 既に全額返金済み
		if( $this->refunded() ){

			$this->error_type = 'cancelException';
			$this->error_code = 'cancel_01';
			$this->error_message = 'すでに全額返金されています';
			$this->setErrorLog('StripeOrder::cancel');
			return false;
		}
		

		try{

			if( empty($this->api_key) )  throw new EmptyApiKeyException();

			$stripe = $this->getStripeInstanse();

			$stripe->refunds->create([
			    'charge' => $chargeId,
			    'amount' => $this->getZankin()
			]);

		} catch (EmptyApiKeyException $e) {

			$this->error_code = 'not_api_key';
			$this->error_type = 'EmptyApiKeyException';
			$this->error_message = $e->getMessage();

		} catch(\Stripe\Exception\CardException $e) {
						
			$this->error_type = 'CardException';
			$this->error_message = $e->getError();
			$this->error_code = $e->getStripeCode();

		} catch (\Stripe\Exception\RateLimitException $e) {

			$this->error_type = 'RateLimitException';
			$this->error_message = $e->getError();
			$this->error_code = 'rate_limit_error';

		} catch (\Stripe\Exception\InvalidRequestException $e) {

			$this->error_type = 'InvalidRequestException';
			$this->error_message = $e->getError();
			$this->error_code = 'invalid_request_error';

		} catch (\Stripe\Exception\ApiErrorException $e) {
			
			$this->error_type = 'ApiErrorException';
			$this->error_message = $e->getError();
			$this->error_code = 'api_error';

		} catch (Exception $e) {

			$this->error_message = $e->getMessage();
		
		}finally{

			if( !empty( $this->error_message ) ){
				$this->setErrorLog('StripeOrder::cancel');
			}else{
				$this->success = true;
			}
		}

        return $refund;

	}


	/**
	 * 決済処理
	 * フロントページ
	 * @param [type] $stripeToken
	 * @param [type] $order_date
	 * @param [type] $acting_opts
	 * @param [type] $CAPTURE
	 * @return void
	 */
	public function frontOrder( $stripeToken , $order_date , $acting_opts , $CAPTURE  ){

			global $usces;

			$charge = [];
			$usces_entries = $usces->cart->get_entry();
			$cart = $usces->cart->get_cart();
			$member = $usces->get_member();

		   
			$amount = (int)$usces_entries['order']['total_full_price']; //総合計金額
			$receipt = $acting_opts['receipt_anme']; //レシート名
			$shipping_total = 0;

			//お客様情報
			$meta_cus_name = $member['name1'];
			$meta_cus_furi = $member['name3'] . $member['name4'];
			$meta_cus_tel = $member['tel'];
			$tomail = $member['mailaddress1'];

			$capture = true;
			if( $CAPTURE == 'auhtorize' ){
				$capture = false;
			}

			// フォームから情報を取得:
			try {


				if( empty($this->api_key) )  throw new EmptyApiKeyException();

				$stripe = $this->getStripeInstanse();
		
				// 顧客を作成
				$customer = $stripe->customers->create([
					'name'     => $meta_cus_name,
					'email'    => $tomail,
					'source'   => $stripeToken
				]);
				

				$charge = $stripe->charges->create(array(
					"capture" => $capture,              // オーソリ(仮売上)の場合は 次回キャプチャ処理までの有効期限がStripeで扱うカードブランドに応じて変化する
					"amount"   => $amount,              // 金額(税込合計)
					"currency" => "jpy",
					"customer" => $customer->id,        // 顧客(常に新規)
					"description" => $receipt,          // レシート名
					"receipt_email" => $tomail,
					"metadata" => [
						'order_date' => $order_date,
						'cus_id'   => $member['ID'],
						'cus_name' => $meta_cus_name ,
						'cus_furi' => $meta_cus_furi ,
						'cus_tel'  => $meta_cus_tel 
						]
				));

				$this->id = $charge['id'];

			} catch (EmptyApiKeyException $e) {
				
				$this->error_type = 'EmptyApiKeyException';
				$this->error_message = $e->getMessage();
				$this->error_code = 'not_api_key';
				
			} catch(\Stripe\Exception\CardException $e) {
					
				$this->error_type = 'CardException';
				$this->error_message = $e->getError();
				$this->error_code = $e->getStripeCode();

			} catch (\Stripe\Exception\RateLimitException $e) {

				$this->error_type = 'RateLimitException';
				$this->error_message = $e->getError();
				$this->error_code = 'rate_limit_error';

			} catch (\Stripe\Exception\InvalidRequestException $e) {

				$this->error_type = 'InvalidRequestException';
				$this->error_message = $e->getError();
				$this->error_code = 'invalid_request_error';
		
			} catch (\Stripe\Exception\ApiErrorException $e) {
				
				$this->error_type = 'ApiErrorException';
				$this->error_message = $e->getError();
				$this->error_code = 'api_error';	

			} catch (Exception $e) {

				$this->error_message = $e->getMessage();
			
			}finally{

				if( !empty( $this->error_message ) ){
					$this->setErrorLog('StripeOrder::frontOrder');

					if( empty($this->error_code) ){
						$this->error_code = $this->error_type;
					}

				}else{
					$this->success = true;
				}
			}

		$statusCode = empty($this->error_code) ? 'true' : $this->error_code;

		return  ['captureFlg' => $CAPTURE ,'charge' => $charge , 'statusCode' => $statusCode ,'decline_code' => $this->getErrorObjectCode() ];

	}


	/**
	 * エラー情報取得
	 *
	 * @param string $error_code
	 * @return void
	 */
	private function getErrorObjectCode( $error_code = '' ){

		if( empty($error_code) ){
			$error_code = $this->error_code;
		}

		$res = '';
		switch ($error_code) {
			case 'card_declined':
				$res = $this->error_message->decline_code;
				break;
		}

	}


	/**
	 * Undocumented function
	 *
	 * @param [type] $chargeId
	 * @param [type] $capture_state
	 * @return void
	 */
	public function order_edit_table_row( $chargeId , $capture_state ){

		$paymantDatas = $this->get( $chargeId );
		ob_start();
	?>
	<tr>
		<td class="border" style="background-color: #ffa300;text-align:center;"><strong>決済ID</strong> </td>
		<td class="col1 border">チャージID<br><div class="rod"><?php echo $chargeId?></div></td>
		<td class="border" style="background-color: #ffa300;text-align:center;"><strong>取引ログ</strong> </td>
		<td colspan="1" class="border">
		<div>
			<span>動作モード : <?php echo ($paymantDatas['livemode'] == false) ? '【テスト稼働】':'【本番稼働】';?></span>
			<span>カード売上フラグ : <?php echo ($capture_state == FALSE) ? 'オーソリ(仮売上)': '即時決済';?></span>
		</div>
		<div class="sales-status">
			<div>返金額 <span class="rod"> &yen; <?php echo number_format($this->getAmount_refunded());?> &nbsp; </div>
			<div>売上   <span class="rod"> &yen; <?php echo number_format($this->getZankin());?></span> &nbsp; </div>
			<div>手数料 <span class="rod"> &yen; <?php echo number_format($this->get_transaction_fee());?></span> &nbsp; </div>
			<div>純売上 <span class="rod"> &yen; <?php echo number_format($this->getNet_sales());?></span> &nbsp; </div>
			<?php 
			// "全額返金済" または "オーソリー期限"が失効している 場合は表示する
			if( $this->isChargeExpired() || $this->refunded() ) {
			?>
			<div class="completion_message">
				<?php 
					$tag_str = '取引終了';
					echo apply_filters( 'sstp_transaction_completion_message',$tag_str);
				?>
			</div>
			<?php 
			}else{
			?>
			<div class="tranc_message">
				<?php 
					$tag_str = '取引中';
					echo apply_filters( 'sstp_transaction_ope_message',$tag_str);
				?>
			</div>
			<?php 
			}
			?>
		</div>
			<div class="rod v-scroll" style="overflow-y: scroll;height:80px;">
				<ul class="stripe-log">

				<?php
				$refunds_list = $this->getRefunds()['data'];
					
				// 返金取引履歴
				if( !empty($refunds_list) ) :
					foreach ($refunds_list as $n => $val) :
					?>
					<li>
						<!-- 処理日付 -->
						<time><?php echo $this->unixtimeToDate($val['created'])?></time>
						<span>
							&yen; <?php echo number_format($val['amount'])?>
							<?php 
							if( $val['object'] === 'refund' ){
								echo ($val['status'] === 'succeeded') ? ' が返金されました':' の返金に失敗しました';
							}else{
								echo ($val['status'] === 'succeeded') ? ' の支払いが成功':' の支払いが失敗';
							}
							?>
						</span>
					</li>
					<?php
					endforeach;
					?>

				<?php endif; ?>

					<!-- 初回決済処理 -->
					<li class="start-created">
						<!-- 処理日付 -->
						<time><?php echo $this->unixtimeToDate($paymantDatas['created'])?></time>
						<span>
						<?php
							if( (bool)$paymantDatas['captured'] === TRUE ) {
								echo sprintf('&yen; %s %s',number_format($this->getAmount()),'が請求されました');
							}else{
								echo '未キャプチャ（仮売上）';
							}
						?>
						</span>
					</li>
				</ul>
			</div><!-- time-line -->
		</td>
		<td colspan="6" class="wrap_td label border">
			<div id="stripeButtons" class="flex-box">
				<?php 
					// "未キャプチャ" または "全額返金済" または "オーソリー期限"が失効している 場合は無効化
					$disabled = ( FALSE === $this->getCaptured() || $this->refunded() || $this->isChargeExpired() ) ? 'disabled' : '';
				?>
				<div class="flex-h">
					<input id="stripRefund" class="button btn" type="button" v-on:click="refund" value="返金" <?php echo $disabled?>>
				<?php 
				    // "全額返金済" または "オーソリー期限"が失効している 場合は無効化
					$disabled = ( $this->refunded() || $this->isChargeExpired() ) ? 'disabled' : '';
				?>
					<input id="stripCancel" class="button btn" type="button" v-on:click="cancel" value="支払取消し" <?php echo $disabled?>>
				</div>
				<div class="flex-h">
				<?php 

				    // オーソリー設定の場合
					if( $capture_state == FALSE ) {

						$disabled = ( TRUE === $this->getCaptured() || $this->isChargeExpired()) ? 'disabled' : '';
				?>

					<input id="stripCapture" class="button btn" type="button" v-on:click="capture" value="注文確定" <?php echo $disabled?>>
					
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<?php
	$html = ob_get_contents();
	ob_end_clean();
	return $html;
	}


/**
 * Undocumented function
 *
 * @param [type] $oderData
 * @return void
 */
	public function order_edit_footer_script( $oderData ){
		//////////////$json_data = json_encode($oderData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		ob_start();
?>
<style>
[v-cloak] { display: none }
</style>
<div id="stripe_modal-app">
	<input type="hidden" v-model="post_id" v-init:post_id="<?php echo $oderData['ID']?>">
	<input type="hidden" id="cahrge_id" value="<?php echo $this->getId()?>">
	<modal name="refund-confirm" :width="450" :height="450" :draggable="true" :resizable="false" :scrollable="false">
		<div class="modal-header">
			<h2 v-text="h2">{{ h2 }}</h2>
			<button class="btn" v-on:click="hide">閉じる</button>
		</div>
		<div class="modal-body">
			<form id="refund_from" action="" method="post">
				<div class="row">
					<div class="alert alert-warning text-center" v-text="warning">{{ warning }}</div>
					<div class="form-group">
						<span>現在の請求金額</span> &nbsp; <input class="" type="text" v-model="billing_amount" v-init:billing_amount="<?php echo $this->getZankin()?>" readonly="readonly" style="background-color: #eee;border: none;"> 円
					</div>
					<div class="form-group">
						<span>返金額</span>  &nbsp; <input class="text-right" type="text" pattern="^[0-9]+" maxlength="8" value="0" v-model="refunds_amount" v-on:change="limit" style="background-color: #eee;border: none"> 円
					</div>
					<div class="alert text-center">
						※処理が成功すると画面が更新されます。
					</div>
					<div class="text-center">
						<button type="button" class="commit-btn" v-on:click="send">実行する</button>
					</div>
				</div>
			</form>
		</div>
	</modal>
	<modal name="dialog-confirm" :width="450" :height="350" :draggable="true" :resizable="false" :scrollable="false">
		<div class="modal-header">
			<h2 v-text="h2">{{ h2 }}</h2>
			<button class="btn" v-on:click="hide">閉じる</button>
		</div>
		<div class="modal-body">
			<form id="dialog_from" action="" method="post">
				<div class="row">
					<div class="alert text-center">
						※処理が成功すると画面が更新されます。
					</div>
					<div class="form-group" v-if="confirm_type=='vs_capture_stripe'">
						<span>返金額</span>  &nbsp; <input class="text-right" type="text" pattern="^[0-9]+" maxlength="8" value="0"  v-model="refunds_amount" v-on:change="limit" style="background-color: #eee;border: none"> 円
					</div>
					<div class="text-center">
						<button type="button" class="commit-btn" v-on:click="commit">確定する</button>
					</div>
				</div>
			</form>
		</div>
	</modal>
</div>
<div id="send-app">
	<modal name="send-result" :width="400" :height="80" :draggable="false" :resizable="false" :scrollable="false">
		<div class="alert alert-success text-center">
			<span v-text="resultMsg">{{ resultMsg }}</span>
		</div>
	</modal>
</div>
<!--<script src="https://www.promisejs.org/polyfills/promise-done-7.0.4.min.js"></script>-->
<script>
	var _ajax_url = '<?php echo admin_url( 'admin-ajax.php')?>';
</script>
<script src="<?php echo SPFW_PLUGIN_URL?>/js/vue_stripe.js"></script>
<?php
		$html = ob_get_contents();
		ob_end_clean();
		return $html;

	}


}

