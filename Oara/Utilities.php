<?php
namespace Oara;
/**
 * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
 *
 * Copyright (C) 2014  Fubra Limited
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
 * Utilities Class
 *
 * @author     Carlos Morillo Merino
 * @category   Oara
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class Utilities
{
    /**
     * confirmed status
     * @var string
     */
    const STATUS_CONFIRMED = 'confirmed';
    /**
     * pending status
     * @var string
     */
    const STATUS_PENDING = 'pending';
    /**
     * declined status
     * @var string
     */
    const STATUS_DECLINED = 'declined';
    /**
     * paid status
     * @var string
     */
    const STATUS_PAID = 'paid';

    /**
     * Clone the array.
     * @param array $cloneArray
     * @return array
     */
    public static function cloneArray(array $cloneArray)
    {
        $returnArray = array();
        foreach ($cloneArray as $element) {
            $returnArray[] = clone $element;
        }
        return $returnArray;
    }

    /**
     * Parse Double, delete odd characters.
     * @param $data
     * @return double
     */
    public static function parseDouble($data)
    {
        $data = \str_replace(" ", "", \trim($data));
        $double = 0;
        if ($data != null) {
            $bits = \explode(",", \trim($data)); // split input value up to allow checking
            $last = \strlen($bits[\count($bits) - 1]); // gets part after first comma (thousands (or decimals if incorrectly used by user)
            if ($last < 3) { // checks for comma being used as decimal place
                $convertnum = \str_replace(",", ".", \trim($data));
            } else {
                $convertnum = \str_replace(",", "", \trim($data));
            }
            $double = \number_format((float)$convertnum, 2, '.', '');
        }
        return $double;
    }

}
