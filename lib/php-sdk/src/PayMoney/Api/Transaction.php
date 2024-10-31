<?php namespace PayMoney\Api;

use PayMoney\Common\PayMoneyModel;

/**
 * Class Transaction
 * 
 * @property \PayMoney\Api\Amount amount
 *
 */

class Transaction extends PayMoneyModel {

	/**
	 * Set Amount
	 * 
	 * @param \PayMoney\Api\Amount $amount
	 *
	 * @return $this
	 */
	public function setAmount( $amount) {
		$this->amount = $amount;
		return $this;
	}

	public function getAmount() {
		return $this->amount;
	}
}
