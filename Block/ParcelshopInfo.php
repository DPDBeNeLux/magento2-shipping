<?php
/**
 * This file is part of the Magento 2 Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2019  DPD Nederland B.V.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace DPDBenelux\Shipping\Block;

class ParcelshopInfo extends \Magento\Framework\View\Element\Template
{
    private $parcelshop;
    private $quote;
    private $countryFactory;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Directory\Model\CountryFactory $countryFactory
    ) {
        parent::__construct($context);
        $this->countryFactory = $countryFactory;
    }

    public function setParcelshop($parcelshop)
    {
        $this->parcelshop = $parcelshop;
    }

    public function getParcelshop()
    {
        return $this->parcelshop;
    }

    public function setQuote($quote)
    {
        $this->quote = $quote;
    }

    public function getQuote()
    {
        return $this->quote;
    }

    public function getCountry($countryCode)
    {
        $country = $this->countryFactory->create()->loadByCode($countryCode);
        return $country->getName();
    }

    /**
     * Render the html for openinghours. (to keep template files clean from to much functional php)
     *
     * @param $dpdExtraInfo
     * @return string
     */
    public function getOpeningHoursHtml($dpdExtraInfo)
    {
        $html = "";
        if (is_array(json_decode($dpdExtraInfo))) {
            foreach (json_decode($dpdExtraInfo) as $openinghours) {
                $openingHoursMorning = $openinghours->openMorning . ' - ' . $openinghours->closeMorning;
                $openingHoursAfternoon = $openinghours->openAfternoon . ' - ' . $openinghours->closeAfternoon;

                if ($openingHoursMorning == '00:00 - 00:00') {
                    $openingHoursMorning = __('Closed');
                }

                if ($openingHoursAfternoon == '00:00 - 00:00') {
                    $openingHoursAfternoon = __('Closed');
                }

                $html .= '
                    <tr>
                        <td style="padding: 3px; border: none;"></td>
                        <td style="padding: 3px; width: 25%;"><strong>' . __(strtolower($openinghours->weekday)) . '</strong></td>
                        <td style="padding: 3px; width: 25%; text-align: center;">' . $openingHoursMorning . '</td>
                        <td style="padding: 3px; width: 25%; text-align: center;">' . $openingHoursAfternoon . '</td>
                    </tr>';
            }
        } else {
            $html .= $dpdExtraInfo;
        }
        return $html;
    }
}
