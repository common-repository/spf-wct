<?php
namespace SPF_WCT;
/**
 * 残高API
 * https://stripe.com/docs/api/balance_transactions/object
 * 
 * TESTカード
 * https://stripe.com/docs/testing#international-cards
 *
 * VISA
 * 4000003920000003
 *
 * JCB
 * 3530111333300000
 * 
 */


 interface StirpeConfig{


	/**
	 * 参照: https://stripe.com/docs/error-codes
	 * エラー概要
	 */
	const STRIPE_ERR_JP_MSG = array(

            'cancel_01' => 'すでに全額返金されています',
			'capture_01' => 'すでに支払いが完了しています',
            'validation_error' => '入力検証エラー',
            'authentication_error' => '認証エラー',
            'api_connection_error' => 'アプリケーションエラー',
            'api_error' => 'アプリケーションエラー',
            'rate_limit_error' => 'アクセスサーバーが混雑しています',
            'invalid_request_error' => '無効なパラメーターエラー',
            'not_api_key' => 'アプリケーションエラー',
			'account_already_exists' => 'アカウントがすでに存在します',
			'account_country_invalid_address' => 'アカウントの国籍とビジネスを行う国籍が異なります',
			'account_invalid' => 'アカウントが不正です',
			'account_number_invalid' => '口座番号が不正です',
			'alipay_upgrade_required' => 'Alipayのアップデートが必要です',
			'amount_too_large' => '金額が多すぎます',
			'amount_too_small' => '金額が少なすぎます',
			'api_key_expired' => 'APIキーが失効しています',
			'balance_insufficient' => '残高不足です',
			'bank_account_exists' => '銀行口座がすでに存在します',
			'bank_account_unusable' => 'この銀行口座に振り込むことができません 他の口座を入力してください',
			'bank_account_unverified' => 'この口座はまだ承認されていません',
			'bitcoin_upgrade_required' => 'ビットコインのアップデートが必要です',
			'card_declined' => 'このカードはご利用できません',
			'charge_already_captured' => 'この決済はすでにキャプチャ済みです',
			'charge_already_refunded' => 'この決済はすでに返金済みです',
			'charge_disputed' => 'この決済はチャージバック中です',
			'charge_exceeds_source_limit' => 'この決済は上限を超過しています',
			'charge_expired_for_capture' => 'この決済はキャプチャ期間を過ぎています',
			'country_unsupported' => '指定された国ではサポートされていません',
			'coupon_expired' => 'クーポンが失効しています',
			'customer_max_subscriptions' => 'サブスクリプションの上限を超過しています',
			'email_invalid' => 'Emailが不正です',
			'expired_card' => 'カードの有効期限が失効しています',
			'idempotency_key_in_use' => '現在、処理が混み合っています しばらくしてから再度処理を行ってください',
			'incorrect_address' => 'カードの住所情報が誤っています 再度入力するか、他のカードをご利用ください',
			'incorrect_cvc' => 'カード裏面のセキュリティーコードが誤っています 再度入力するか、他のカードをご利用ください',
			'incorrect_number' => 'カード番号が誤っています  再度入力するか、他のカードをご利用ください',
			'incorrect_zip' => 'カードの郵便番号が誤っています  再度入力するか、他のカードをご利用ください',
			'instant_payouts_unsupported' => 'このデビットカードは即入金に対応していません  他のカードをご利用いただくか、銀行口座を入力してください',
			'invalid_card_type' => '対応していないカードタイプです 他のカードをご利用いただくか、銀行口座を入力してください',
			'invalid_charge_amount' => '不正な金額です',
			'invalid_cvc' => 'カード裏面のセキュリティーコードが誤っています',
			'invalid_expiry_month' => 'カードの有効期限(月)が誤っています',
			'invalid_expiry_year' => 'カードの有効期限(年)が誤っています',
			'invalid_number' => 'カード番号が不正です 再度入力するか、他のカードをご利用ください',
			'invalid_source_usage' => '不正な支払いソースです',
			'invoice_no_customer_line_items' => '請求書が存在しません',
			'invoice_no_subscription_line_items' => '請求書が存在しません',
			'invoice_not_editable' => 'この請求書は書き換え不可です',
			'invoice_upcoming_none' => '請求書が存在しません',
			'livemode_mismatch' => 'APIキーが不正です',
			'missing' => '支払い情報のリンクに失敗しました',
			'order_creation_failed' => '注文が失敗しました。 注文を再度確認するか、しばらくしてから再度処理を行ってください',
			'order_required_settings' => '情報に不足があるため、注文に失敗しました',
			'order_status_invalid' => '注文状態が不正なため、更新できません',
			'order_upstream_timeout' => '注文がタイムアウトしました しばらくしてから再度処理を行ってください',
			'out_of_inventory' => '在庫が無いため注文できません',
			'parameter_invalid_empty' => '情報が不足しています',
			'parameter_invalid_integer' => '不正な整数値です',
			'parameter_invalid_string_blank' => '空白文字エラーです',
			'parameter_invalid_string_empty' => '少なくとも1文字以上を入力してください',
			'parameter_missing' => '情報が不足しています',
			'parameter_unknown' => '不正なパラメータが存在します',
			'payment_method_unactivated' => '支払い方法がアクティベートされていないため、決済に失敗しました',
			'payouts_not_allowed' => 'このアカウントに入金できません 状態を確認してください',
			'platform_api_key_expired' => 'プラットフォームAPIキーが失効しています',
			'postal_code_invalid' => '郵便番号が不正です',
			'processing_error' => '処理中にエラーが発生しました 再度入力するか、他のカードをご利用ください',
			'product_inactive' => 'この商品は現在取り扱いをしていません',
			'rate_limit' => 'API上限を超過しました',
			'resource_already_exists' => 'リソースがすでに存在します',
			'resource_missing' => 'リソースが存在しません',
			'routing_number_invalid' => '口座番号、支店番号が誤っています',
			'secret_key_required' => 'シークレットキーが存在しません',
			'sepa_unsupported_account' => 'このアカウントはSEPAに対応していません',
			'shipping_calculation_failed' => '送料計算に失敗しました',
			'sku_inactive' => 'SKUに対応していません',
			'state_unsupported' => 'この州には現在対応していません',
			'tax_id_invalid' => 'TAX IDが不正です 少なくとも9桁入力する必要があります',
			'taxes_calculation_failed' => '税金計算に失敗しました',
			'testmode_charges_only' => 'テストモードの決済限定です',
			'tls_version_unsupported' => 'このTLSのバージョンに対応していません',
			'token_already_used' => 'このトークンはすでに使用済みです',
			'token_in_use' => 'このトークンは現在使用中です',
			'transfers_not_allowed' => '現在、送金が行えません',
			'upstream_order_creation_failed' => '注文に失敗しました 注文を再度確認するか、しばらくしてから再度処理を行ってください',
			'url_invalid' => 'URLが不正です'
	);

	/**
	 * エラー詳細
	 * [error_code] => card_declined の場合
	 * 指定値 [decline_code] => "fraudulent"
	 */
	const DECLINE_CODE = array(
			'authentication_required' => 'トランザクションで認証が必要なため、カードは拒否されました。|お客様は、トランザクション中にプロンプ​​トが表示されたら、再試行してカードを認証する必要があります。',
			'approve_with_id' => '支払いを承認することはできません。|支払いを再試行する必要があります。それでも処理できない場合、お客様はカード発行会社に連絡する必要があります。',
			'call_issuer|カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'card_not_supported' => 'カードはこのタイプの購入をサポートしていません。|お客様は、カード発行会社に連絡して、カードを使用してこのタイプの購入を行えることを確認する必要があります。',
			'card_velocity_exceeded' => '顧客がカードで利用可能な残高またはクレジット制限を超えました。|詳細については、カード発行会社にお問い合わせください。',
			'currency_not_supported' => 'カードは指定された通貨をサポートしていません。|お客様は、指定された種類の通貨でカードを使用できるかどうかを発行者に確認する必要があります。',
			'do_not_honor' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'do_not_try_again' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'duplicate_transaction' => 'ごく最近、同じ金額とクレジットカード情報の取引が提出されました。|最近の支払いがすでに存在するかどうかを確認してください。',
			'expired_card' => 'カードの有効期限が切れています。|お客様は別のカードを使用する必要があります。',
			'fraudulent' => 'Stripeが不正であると疑ったため、支払いは拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'generic_decline' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'incorrect_number' => 'カード番号が間違っています。|お客様は、正しいカード番号を使用して再試行する必要があります。',
			'incorrect_cvc' => 'CVC番号が正しくありません。|お客様は、正しいCVCを使用して再試行する必要があります。',
			'incorrect_pin' => '入力したPINが正しくありません。この拒否コードは、カードリーダーで行われた支払いにのみ適用されます。|お客様は、正しいPINを使用して再試行する必要があります。',
			'incorrect_zip' => '郵便番号が正しくありません。|お客様は、正しい請求先郵便番号/郵便番号を使用して再試行する必要があります。',
			'insufficient_funds' => 'カードの資金が不足しているため、購入を完了できません。|お客様は別の支払い方法を使用する必要があります。',
			'invalid_account' => 'カード、またはカードが接続されているアカウントが無効です。|お客様は、カードが正しく機能していることを確認するために、カード発行会社に連絡する必要があります。',
			'invalid_amount' => 'お支払い金額が無効であるか、許可されている金額を超えています。|金額が正しいと思われる場合、お客様はカード発行会社にその金額を購入できるかどうかを確認する必要があります。',
			'invalid_cvc' => 'CVC番号が正しくありません。|お客様は、正しいCVCを使用して再試行する必要があります。',
			'invalid_expiry_month' => '有効期限は無効です。|お客様は、正しい有効期限を使用して再試行する必要があります。',
			'invalid_expiry_year' => '有効期限が無効です。|お客様は、正しい有効期限を使用して再試行する必要があります。',
			'invalid_number' => 'カード番号が間違っています。|お客様は、正しいカード番号を使用して再試行する必要があります。',
			'invalid_pin' => '入力したPINが正しくありません。この拒否コードは、カードリーダーで行われた支払いにのみ適用されます。|お客様は、正しいPINを使用して再試行する必要があります。',
			'issuer_not_available' => 'カード発行会社に連絡できなかったため、支払いを承認できませんでした。|支払いを再試行する必要があります。それでも処理できない場合、お客様はカード発行会社に連絡する必要があります。',
			'lost_card' => 'カードの紛失が報告されたため、支払いは拒否されました。|拒否の具体的な理由は、顧客に報告するべきではありません。代わりに、一般的な衰退として提示する必要があります。',
			'merchant_blacklist' => 'Stripeユーザーのブロックリストの値と一致するため、支払いは拒否されました。|より詳細な情報を顧客に報告しないでください。代わりに、generic_decline上記のように提示してください。',
			'new_account_information_available' => 'カード、またはカードが接続されているアカウントが無効です。|詳細については、カード発行会社にお問い合わせください。',
			'no_action_taken' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'not_permitted' => '支払いは許可されていません。|詳細については、カード発行会社にお問い合わせください。',
			'offline_pin_required' => 'PINが必要なため、カードは拒否されました。|お客様は、カードを挿入してPINを入力して再試行する必要があります。',
			'online_or_offline_pin_required' => 'PINが必要なため、カードは拒否されました。|カードリーダーがオンラインPINをサポートしている場合、新しいトランザクションを作成せずにPINの入力を求めるプロンプトが表示されます。カードリーダーがオンラインPINをサポートしていない場合、お客様はカードを挿入してPINを入力して再試行する必要があります。',
			'pickup_card' => 'カードを使用してこの支払いを行うことはできません（紛失または盗難が報告されている可能性があります）。|詳細については、カード発行会社にお問い合わせください。',
			'pin_try_exceeded' => 'PINの許容試行回数を超えました。|お客様は別のカードまたはお支払い方法を使用する必要があります。',
			'processing_error' => 'カードの処理中にエラーが発生しました。|支払いを再試行する必要があります。それでも処理できない場合は、後で再試行してください。',
			'reenter_transaction' => '不明な理由により、発行者は支払いを処理できませんでした。|支払いを再試行する必要があります。それでも処理できない場合、お客様はカード発行会社に連絡する必要があります。',
			'restricted_card' => 'カードを使用してこの支払いを行うことはできません（紛失または盗難が報告されている可能性があります）。|詳細については、カード発行会社にお問い合わせください。',
			'revocation_of_all_authorizations' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'revocation_of_authorization' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'security_violation' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'service_not_allowed' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'stolen_card' => 'カードの盗難が報告されたため、支払いは拒否されました。|拒否の具体的な理由は、顧客に報告するべきではありません。代わりに、一般的な衰退として提示する必要があります。',
			'stop_payment_order' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'testmode_decline' => 'ストライプテストカード番号が使用されました。|支払いには本物のカードを使用する必要があります。',
			'transaction_not_allowed' => 'カードは不明な理由で拒否されました。|詳細については、カード発行会社にお問い合わせください。',
			'try_again_later' => 'カードは不明な理由で拒否されました。|顧客に支払いを再試行するように依頼します。その後の支払いが拒否された場合、顧客はカード発行会社に詳細を問い合わせる必要があります。',
			'withdrawal_count_limit_exceeded' => '顧客がカードで利用可能な残高またはクレジット制限を超えました。|お客様は別の支払い方法を使用する必要があります。'
    );

 }
 ?>