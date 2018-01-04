<?php
class CashU {
	public $settings = array(
		'description' => 'Accept CashU payments.',
	);
	function payment_button($params) {
		global $billic, $db;
		$html = '';
		if (get_config('cashu_merchant_id') == '') {
			return $html;
		}
		if ($billic->user['verified'] == 0 && get_config('cashu_require_verification') == 1) {
			return 'verify';
		} else {
			$html.= '<form action="https://www.cashu.com/cgi-bin/pcashu.cgi" method="post">' . PHP_EOL;
			$html.= '<input type="hidden" name="merchant_id" value="' . get_config('cashu_merchant_id') . '">' . PHP_EOL;
			$html.= '<input type="hidden" name="token" value="' . md5(strtolower(get_config('cashu_merchant_id') . ':' . $params['charge'] . ':' . get_config('billic_currency_code') . ':') . get_config('cashu_secret')) . '">' . PHP_EOL;
			$html.= '<input type="hidden" name="display_text" value="' . get_config('billic_companyname') . ' - Invoice #' . $params['invoice']['id'] . '">' . PHP_EOL;
			$html.= '<input type="hidden" name="currency" value="' . get_config('billic_currency_code') . '">' . PHP_EOL;
			$html.= '<input type="hidden" name="amount" value="' . $params['charge'] . '">' . PHP_EOL;
			$html.= '<input type="hidden" name="language" value="en">' . PHP_EOL;
			$html.= '<input type="hidden" name="email" value="' . $billic->user['email'] . '">' . PHP_EOL;
			$html.= '<input type="hidden" name="session_id" value="' . $params['invoice']['id'] . '">' . PHP_EOL;
			$html.= '<input type="hidden" name="txt1" value="Invoice #' . $params['invoice']['id'] . '">' . PHP_EOL;
			$html.= '<input type="submit" class="btn btn-default" value="CashU">' . PHP_EOL;
			$html.= '</form>' . PHP_EOL;
		}
		return $html;
	}
	function payment_callback() {
		global $billic, $db;
		$xml = $_POST['sRequest'];
		$xml = simplexml_load_string($xml);
		$xml = @json_decode(@json_encode($xml) , 1);
		$cashutoken = md5(strtolower(get_config('cashu_merchant_id') . ':' . $xml['cashU_trnID'] . ':' . get_config('cashu_secret')));
		if ($cashutoken != $xml['cashUToken']) {
			return 'invalid transaction hash (1)';
		}
		$token = md5(strtolower(get_config('cashu_merchant_id')) . ':' . $xml['amount'] . ':' . strtolower($xml['currency']) . ':' . strtolower($xml['session_id']) . ':' . get_config('cashu_secret'));
		if ($token != $xml['token']) {
			return 'invalid transaction hash (2)';
		}
		// Response back to CashU
		$post = array(
			'sRequest' => '<cashUTransaction><merchant_id>' . get_config('cashu_merchant_id') . '</merchant_id><cashU_trnID>' . $xml['cashU_trnID'] . '</cashU_trnID><cashUToken>' . $xml['cashUToken'] . '</cashUToken><responseCode>OK</responseCode><responseDate>' . date('Y-m-d H:i:s') . '</responseDate></cashUTransaction>',
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, 'https://www.cashu.com/cgi-bin/notification/MerchantFeedBack.cgi');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$data = curl_exec($ch);
		$billic->module('Invoices');
		return $billic->modules['Invoices']->addpayment(array(
			'gateway' => 'CashU',
			'invoiceid' => $xml['session_id'],
			'amount' => $xml['amount'],
			'currency' => $xml['currency'],
			'transactionid' => $xml['cashU_trnID'],
		));
	}
	function settings($array) {
		global $billic, $db;
		if (empty($_POST['update'])) {
			echo '<form method="POST"><input type="hidden" name="billic_ajax_module" value="CashU"><table class="table table-striped">';
			echo '<tr><th>Setting</th><th>Value</th></tr>';
			echo '<tr><td>Require Verification</td><td><input type="checkbox" name="cashu_require_verification" value="1"' . (get_config('cashu_require_verification') == 1 ? ' checked' : '') . '></td></tr>';
			echo '<tr><td>CashU Merchant ID</td><td><input type="text" class="form-control" name="cashu_merchant_id" value="' . safe(get_config('cashu_merchant_id')) . '"></td></tr>';
			echo '<tr><td>CashU Secret</td><td><input type="text" class="form-control" name="cashu_secret" value="' . safe(get_config('cashu_secret')) . '"></td></tr>';
			echo '<tr><td colspan="2" align="center"><input type="submit" class="btn btn-default" name="update" value="Update &raquo;"></td></tr>';
			echo '</table></form>';
		} else {
			if (empty($billic->errors)) {
				set_config('cashu_require_verification', $_POST['cashu_require_verification']);
				set_config('cashu_merchant_id', $_POST['cashu_merchant_id']);
				set_config('cashu_secret', $_POST['cashu_secret']);
				$billic->status = 'updated';
			}
		}
	}
}
