<?php
namespace Oara\Network\Publisher;
use Oara\Utilities;

/**
 * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
 *
 * Copyright (C) 2016  Fubra Limited
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Contact
 * ------------
 * Fubra Limited <support@fubra.com> , +44 (0)1252 367 200
 **/

class Inis extends \Oara\Network
{
	private $_user_id = null;
	private $_api_password = null;

	/**
	 * API URL
	 * @var string
	 */
	protected $_apiUrl = 'https://system.inis360.com/api-publisher/v1.0';


	/**
	 * @param $credentials
	 * @throws Exception
	 */
	public function login($credentials)
	{
        $this->_user_id = $credentials['user'];
        $this->_api_password = $credentials['password'];
	}



	/**
	 * @return array
	 */
	public function getNeededCredentials()
	{
		$credentials = array();

		$parameter = array();
		$parameter["description"] = "User Log in";
		$parameter["required"] = true;
		$parameter["name"] = "User";
		$credentials["user"] = $parameter;

		$parameter = array();
		$parameter["description"] = "Password to Log in";
		$parameter["required"] = true;
		$parameter["name"] = "Password";
		$credentials[] = $parameter;

		return $credentials;
	}

	/**
	 * @return bool
	 */
	public function checkConnection()
	{
		$connection = true;
		return $connection;
	}



	/**
	 * @param null $merchantList
	 * @param \DateTime|null $dStartDate
	 * @param \DateTime|null $dEndDate
	 * @return array
	 * @throws \Exception
	 */
	public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
	{
		/**
         * https://system.inis360.com/api-publisher/v1.0/programs/XX/actions?secureCode=XX&count=1000
         */
        $totalTransactions = array();
        if (isset($_ENV['INIS_PROGRAMS'])) {
            $programs = $_ENV['INIS_PROGRAMS'];
            $a_programs = explode(",", $programs);
            foreach ($a_programs as $program_id) {
                try{
                    $start_date = $dStartDate->getTimestamp();
                    $end_date = $dEndDate->getTimestamp();
                    $page = 1;
                    $limit = 1000;
                    $loop = true;

                    while ($loop){
                        $transactionsList = $this->_requestTransactions(
                            'programs',
                            $program_id,
                            'actions',
                            array(
                                'from' => $start_date, //format timestamp
                                'to' => $end_date, //format timestamp
                                'secureCode' => $this->_api_password,
                                'page' => $page, //Specifies the page of the results set that is currently being viewed.(default:1)
                                'count' => $limit, //Specifies the number of records to be viewed per page.(default:50)
                            )
                        );
                        if (isset($transactionsList['actions'])){
                            if (empty($transactionsList['actions'])) {
                                $loop = false;
                                break;
                            }
                            foreach ($transactionsList['actions'] as $transaction) {
                                $a_transaction['unique_id'] = $transaction['guid'];
                                $a_transaction['date'] = new \DateTime();
                                $a_transaction['date']->setTimestamp($transaction['time']); //microtimestamp - float
                                $a_transaction['custom_id'] = $transaction['subId1'];
                                $a_transaction['commission'] = Utilities::parseDouble($transaction['profit']);
                                //currency
                                $a_transaction['currency'] = 'PLN';
                                //type
                                if (isset($transaction['type']) && $transaction['type'] != 3){
                                    echo '[php-oara][Oara][Network][Publisher][Inis][getTransactionList] Transaction type unexpected ' . $transaction['type'];
                                }
                                if (isset($transaction['actionValue'])){
                                    //This field is only returned for conversions - Actions with type = 3 Action type (1 - click, 2 - kik, 3 - conversion / sale)
                                    $a_transaction['amount'] = Utilities::parseDouble($transaction['actionValue']);
                                }
                                switch ($transaction['status']){
                                    case 0:
                                        $a_transaction['status'] = Utilities::STATUS_PENDING;
                                        break;
                                    case 2:
                                        $a_transaction['status'] = Utilities::STATUS_DECLINED;
                                        break;
                                    case 1:
                                        $a_transaction['status'] = Utilities::STATUS_CONFIRMED;
                                        break;
                                }
                                $totalTransactions[] = $a_transaction;
                            }
                            if (isset($transactionsList['nextPageUrl']) && empty($transactionsList['nextPageUrl'])) {
                                //string|null
                                $loop = false;
                                break;
                            }
                            else{
                                $page = (int)(1 + $page);
                            }
                        }
                    }
                }
                catch (\Exception $e){
                    throw new \Exception($e);
                }
            }
        }
        else{
            throw new \Exception('[Inis][getTransactionList] INIS_PROGRAMS empty key');
        }
		return $totalTransactions;

	}


	/**
	 * @return string
	 */
	protected function _getApiBaseUrl() {
		return $this->_apiUrl;
	}


    /**
     * @param $service
     * @param $call
     * @param array $a_options
     * @return false|\stdClass|string
     * @throws \Exception
     */
    protected function _requestTransactions($service, $program_id, $call, $a_options) {
        $url = $this->_getApiBaseUrl() . '/' . $service . '/' . $program_id . '/' . $call . '?';

        foreach ($a_options as $key => $value) {
            $url .= '&' . $key . '=' . $value;
        }

        $data = file_get_contents($url);
        if (strlen($data) == 0) {
            throw new \Exception('[Inis][_requestTransactions] invalid result received');
        }

        $data = $this->_decode($data);
        if (isset($data['actions'])){
            return $data;
        } else {
            throw new \Exception($data);
        }
    }

	/**
	 * @param  string $data
	 * @param  string $format (Optional) Format
	 * @return \stdClass
	 */
	protected function _decode($data, $format = 'json'){
		if ($format == 'json') {
			return json_decode($data, true);
		}
	}




}
