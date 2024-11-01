<?php 
namespace SPF_WCT\FUNC;
use SPF_WCT;

function setCardElement(){
    SPF_WCT\STRIPE_SETTLEMENT::get_instance()->setCardElement(); 
}

add_shortcode( 'SPF_WCT_CARD_INPUT', function(){
    SPF_WCT\FUNC\setCardElement();
} );