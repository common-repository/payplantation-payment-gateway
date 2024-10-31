<?php
namespace PayMoney\Api;

use PayMoney\Common\PayMoneyModel;

/**
 * Class Amount
 * 
 * @property double totalAmount
 * @property string currency
 *
 */
class Amount extends PayMoneyModel {

	/**
	 * Set Total
	 * 
	 * @param  double  $amount
	 * @return $this
	 */
	public function setTotal( $amount) {
		$this->totalAmount = $amount;
		return $this;
	}

	public function getTotal() {
		return $this->totalAmount;
	}

	/**
	 * Set Currency
	 * 
	 * @param  string  $currency
	 * @return $this
	 */
	public function setCurrency( $currency) {
		$this->currency = $currency;
		return $this;
	}

	public function getCurrency() {
		return $this->currency;
	}

}
