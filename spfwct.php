<?php
/**
 * Plugin Name: SPF_WCT
 * Plugin URI: ec.sys-creator.org
 * Description: このプラグインは Welcart（ウェルカート）にStripe決済サービスが組み込めるプラグインです。利用するには"Welcart e-Commerce"プラグインがインストールされている必要があります。
 * Author: t.tsukamoto
 * Author URI: ec.sys-creator.org
 * Version: 1.0.0
 * Copyright (C) 2015-2021 RoidLab.
 */

define('SPFW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define('SPFW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define('SPFW_LOG_OUTPUT',SPFW_PLUGIN_DIR . '/logs/stripe.log');
define('SPFW_API_ENDPOINT','https://js.stripe.com/v3/');


require_once(SPFW_PLUGIN_DIR."/classes/Logger.class.php");
require_once(SPFW_PLUGIN_DIR."/vendor/stripe-php-7.75.0/init.php");
require_once(SPFW_PLUGIN_DIR."/classes/EmptyApiKeyException.class.php");
require_once(SPFW_PLUGIN_DIR."/classes/InputFilter.class.php");
require_once(SPFW_PLUGIN_DIR."/classes/StripeConfig.class.php");
require_once(SPFW_PLUGIN_DIR."/classes/ErrorBundle.class.php");
require_once(SPFW_PLUGIN_DIR."/classes/stripejs.php");
require_once(SPFW_PLUGIN_DIR."/classes/spfwct_function.php");
require_once(SPFW_PLUGIN_DIR."/classes/StripeOrder.class.php");
require_once(SPFW_PLUGIN_DIR."/classes/StripeAjx.class.php");
require_once(SPFW_PLUGIN_DIR."/classes/paymentStripe.class.php");
require_once(SPFW_PLUGIN_DIR."/classes/MainController.class.php");

$controller = new SPF_WCT\MainController();
register_activation_hook( __FILE__, array( $controller, 'activate' ) );
register_deactivation_hook( __FILE__, array( $controller, 'deactivate' ) );

$controller->Initialize();