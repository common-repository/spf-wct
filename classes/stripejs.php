<?php 
namespace SPF_WCT;


function spfw_put_footer_script( $stripe_key ){
?>
<style>
.stripe-frame{
	background-color:#fff;
	margin: 1em auto;
	padding: 30px;
	border-radius:0.2em;
}

.cre-card{
	display:flex;
	margin-bottom:1em;
}
.cre-card li{
	padding:0 0.5em;
}

.stripe-frame .form-row{
	display: block;
	margin-left: auto;
	margin-right: auto;
	max-width: 600px;
}

.stripe-frame #nextBtn{
	margin-top: 30px;
	letter-spacing: 1px;
}

#card-errors{
	font-size: 12px;
	padding-top: 3px;
	color: red;
}

#confirm_table th, #confirm_table td{
	padding: 1em 1.5em;
	border-color:#333;
}
</style>
<script src="<?php echo SPFW_API_ENDPOINT; ?>"></script>
<!--<script src="https://polyfill.io/v3/polyfill.min.js?version=3.52.1&features=fetch"></script>-->
<script type="text/javascript">
(function(){
	function stripe_callback(){
		var stripe = Stripe('<?php echo $stripe_key; ?>');
		var elements = stripe.elements("ja");

		var style = {
		base: {
			color: '#32325d',
			fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
			fontSmoothing: 'antialiased',
			fontSize: '16px',
			'::placeholder': {
			color: '#aab7c4'
			}
		},
		invalid: {
			color: '#fa755a',
			iconColor: '#fa755a'
		}
		};


		var card = elements.create('card', {style: style});
		card.mount('#card-element');


		card.addEventListener('change', function(event) {
			var displayError = document.getElementById('card-errors');
			if (event.error) {
				displayError.textContent = event.error.message;
			} else {
				displayError.textContent = '';
			}
		});


		var form = document.getElementById('purchase_form');
		form.addEventListener('submit', function(event) {
			event.preventDefault();

			stripe.createToken(card).then(function(result) {
				if (result.error) {
				var errorElement = document.getElementById('card-errors');
				errorElement.textContent = result.error.message;
				} else {
				stripeTokenHandler(result.token);
				}
			});
		});


		function stripeTokenHandler(token) {
			var form = document.getElementById('purchase_form');
			var hiddenInput = document.createElement('input');
			hiddenInput.setAttribute('type', 'hidden');
			hiddenInput.setAttribute('name', 'stripeToken');
			hiddenInput.setAttribute('value', token.id);
			form.appendChild(hiddenInput);
			var purchase_inp = document.createElement('input');
			purchase_inp.setAttribute('type', 'hidden');
			purchase_inp.setAttribute('name', 'purchase');
			purchase_inp.setAttribute('value', 1);
			form.appendChild(purchase_inp);
			form.submit();
		}

	}

	document.addEventListener('readystatechange',function(){

		if( document.readyState == 'complete'){
			stripe_callback();
		}

	}, false);

})();
</script>

<?php
}

