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
namespace DPDBenelux\Shipping\Model;

use Magento\Framework\UrlInterface;

class CheckoutConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    const DPD_GOOGLE_MAPS_WIDTH = 'carriers/dpdpickup/map_width';
    const DPD_GOOGLE_MAPS_HEIGHT = 'carriers/dpdpickup/map_height';

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    private $scopeConfig;

    public function __construct(
        UrlInterface $urlBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {
        $output['dpd_parcelshop_url'] = $this->urlBuilder->getUrl('dpd/parcelshops', ['_secure' => true]);
        $output['dpd_parcelshop_save_url'] = $this->urlBuilder->getUrl('dpd/parcelshops/save', ['_secure' => true]);
        $output['dpd_googlemaps_width'] = $this->scopeConfig->getValue(self::DPD_GOOGLE_MAPS_WIDTH);
        $output['dpd_googlemaps_height'] = $this->scopeConfig->getValue(self::DPD_GOOGLE_MAPS_HEIGHT);
        return $output;
    }
}
