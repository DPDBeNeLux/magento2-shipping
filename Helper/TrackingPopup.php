<?php
/**
 * This file is part of the Magento 2 Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2018  DPD Nederland B.V.
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
namespace DPDBenelux\Shipping\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;

class TrackingPopup extends AbstractHelper
{
    public function __construct(\Magento\Framework\App\Helper\Context $context)
    {
        parent::__construct($context);
    }

    public function getCarrierTitle()
    {
        $carrierCode = 'dpdpredict';

        $carrierTitle = $this->scopeConfig->getValue(
            'carriers/' . $carrierCode  . '/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $carrierTitle;
    }

    public function getErrorMessage()
    {
        return __('Tracking not available');
    }

    public function getProgressdetail()
    {
        return '';
    }

    public function getTracking()
    {
        return '';
    }
}
