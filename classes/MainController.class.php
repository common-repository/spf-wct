<?php
namespace SPF_WCT;

class MainController
{
    private $startFlg = false;
   
    public function __construct()
    {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        global $usces;

        if( is_plugin_active( 'usc-e-shop/usc-e-shop.php' ) && $usces != null ){
            $this->startFlg = true;
        } else {
            $this->startFlg = false;
        }

    }

    public function activate() {
        $payments = get_option( 'usces_available_settlement' );
		// クレジット決済設定にStripe決済を追加
		if(  $this->startFlg && isset($payments['stripe']) == false ){
            
			$usces_available_settlement = array_merge($payments , ['stripe' => 'ストライプ']);
			update_option( 'usces_available_settlement', $usces_available_settlement );
		}
     }

 
    public function deactivate() {
        $this->startFlg = false;
    }


    public function Initialize(){

        if( $this->startFlg ){
      
            if( !file_exists(SPFW_LOG_OUTPUT) ){

                /////$pem = substr( sprintf( '%o', fileperms(SPFW_LOG_OUTPUT)), -4);
                add_action('admin_notices', array($this,'logfile_does_not_exist'),10);

            }else{

                if( is_admin() ){
                    $o = new StripeAjx();
                    $o->setAction();
                }

                add_action('usces_main', array( $this , 'stripe_usces_main' ) , 12);

            }

        }else{

            add_action('admin_notices', array($this,'welcart_does_not_exist'),10);

        }

    }

    public function stripe_usces_construct(){
        $this->activate();
    }

    public function logfile_does_not_exist(){

         echo '<div class="message error"><p>Stripe for Welcartプラグインはログ出力ファイルが存在しないと動作しません。プラグインフォルダ内に”/logs/stripe.log”を作成してください。</p></div>';

    }


    public function welcart_does_not_exist(){

         echo '<div class="message error"><p>Stripe for Welcartプラグインを利用するには Welcart e-Commerce（ウェルカート） が必要です。</p></div>';

    }


    public function front_action(){
        // Expand later・・・ 
    }


    public function backend_action(){
        // Expand later・・・

    }


    /**
     * 
     * 起動処理
     * welcartメイン処理にフックする
     *
     * @return void
     */
    public function stripe_usces_main(){

        // フロントと管理側
        STRIPE_SETTLEMENT::get_instance();
        
        // 管理側のみ
        if( is_admin() ){

            $this->backend_action();

        }else{
            // フロントのみ
            $this->front_action();
        }

    }



}


