<?php
namespace Oara\Network\Publisher;
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
/**
 * Export Class
 *
 * @author     Slawek Naczynski
 * @category   Cj
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class CommissionJunctionGraphQL extends \Oara\Network
{
    private $_website_id  = null;
    private $_apiPassword = null;

    /**
     * @param $credentials
     */
    public function login($credentials)
    {
        if(isset($credentials['apipassword']) && isset($credentials['id_site'])){
            $this->_apiPassword = $credentials['apipassword'];
            $this->_website_id  = $credentials['id_site'];
        }
        else{
            return false;
        }
    }

    /**
     * @return bool
     */
    public function checkConnection()
    {
        $connection = true;
        $result = self::apiCall(new \DateTime('2000-01-01'), new \DateTime('2000-01-02'));
        if ($result === false) {
            return false;
        }
        return $connection;
    }

    private function apiCall($startDate, $endDate)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://commissions.api.cj.com/query');
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{ publisherCommissions(forPublishers: ["' . $this->_website_id . '"], sincePostingDate:"' . $startDate->format("Y-m-d") . 'T00:00:00Z",beforePostingDate:"' . $endDate->format("Y-m-d") . 'T23:59:59Z"){count payloadComplete records {actionStatus originalActionId commissionId advertiserId shopperId pubCommissionAmountPubCurrency original aid orderId eventDate saleAmountPubCurrency actionType }  } }');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $this->_apiPassword));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        
        $result = curl_exec($ch);
        curl_close ($ch);
        return json_decode($result, true);
    }

    /**
     * @return array
     */
    public function getMerchantList()
    {
        $merchants = array();
        return $merchants;
    }

    /**
     * @param null $merchantList
     * @param \DateTime|null $dStartDate
     * @param \DateTime|null $dEndDate
     * @return array
     * @throws Exception
     */
    public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
    {
        $result       = self::apiCall($dStartDate, $dEndDate);
        $transactions = array();
        $i            = 0;

        // IF THERE ARE ANY RESULTS
        if(isset($result) && count($result) > 0 && $result['data']['publisherCommissions']['count'] > 0){
            foreach($result['data']['publisherCommissions']['records'] as $singleTransaction){
                $transactions[$i]['unique_id']  = $singleTransaction['commissionId'];
                $transactions[$i]['action']     = $singleTransaction['actionType'];
                $transactions[$i]['merchantId'] = $singleTransaction['advertiserId'];
                $transactions[$i]['date']       = $singleTransaction['eventDate'];
                $transactions[$i]['custom_id']  = $singleTransaction['shopperId'];
                $transactions[$i]['amount']     = \Oara\Utilities::parseDouble($singleTransaction['saleAmountPubCurrency']);
                $transactions[$i]['commission'] = \Oara\Utilities::parseDouble($singleTransaction['pubCommissionAmountPubCurrency']);
                
                if ($singleTransaction['actionStatus'] == 'locked' || $singleTransaction['actionStatus'] == 'closed') {
                    $transactions[$i]['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                } else if ($singleTransaction['actionStatus'] == 'extended' || $singleTransaction['actionStatus'] == 'new') {
                    $transactions[$i]['status'] = \Oara\Utilities::STATUS_PENDING;
                } else if ($singleTransaction['actionStatus'] == 'corrected') {
                    $transactions[$i]['status'] = \Oara\Utilities::STATUS_DECLINED;
                }
            
                if ($singleTransaction['pubCommissionAmountPubCurrency'] == 0) {
                    $transactions[$i]['status'] = \Oara\Utilities::STATUS_DECLINED;
                }	
            
                $transactions[$i]['aid']                = $singleTransaction['aid'];
                $transactions[$i]['order-id']           = $singleTransaction['orderId'];
                $transactions[$i]['original']           = ($singleTransaction['original'] === true);
                $transactions[$i]['original-action-id'] = $singleTransaction['originalActionId'];
                $i++;
            }
        }
        return $transactions;
    }
}
