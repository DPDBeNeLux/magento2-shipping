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
namespace DPDBenelux\Shipping\Helper\Services;

use Magento\Framework\App\Helper\AbstractHelper;
use DPDBenelux\Shipping\Helper\DPDClient;

class DPDPickupService extends AbstractHelper
{
    const DPD_MAX_PARCELSHOPS = 'carriers/dpdpickup/map_max_shops';

    /**
     * Used to access the accesstoken, depot and delisId
     * @var AuthenticationService
     */
    private $authenticationService;

    private $dpdClient;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \DPDBenelux\Shipping\Helper\Services\AuthenticationService $authenticationService,
        DPDClient $DPDClient
    ) {
        $this->authenticationService = $authenticationService;
        $this->dpdClient = $DPDClient;
        parent::__construct($context);
    }

    /**
     * @param $longitude
     * @param $latitude
     * @return array
     * @throws \Exception
     */
    public function getParcelShops($latitude, $longitude)
    {
        $accessToken = $this->authenticationService->getAccessToken();
        $delisId = $this->authenticationService->getDelisId();

        $limit = $this->scopeConfig->getValue(self::DPD_MAX_PARCELSHOPS);

        $parameters = array(
            'latitude' => $latitude,
            'longitude' => $longitude,
            'limit' => $limit,
            'consigneePickupAllowed' => 'true'
        );

        try {
            $result = $this->dpdClient->findParcelShopsByGeoData($parameters, $delisId, $accessToken);
        } catch (\Exception $ex) {
            // retry once with a new access token
            $accessToken = $this->authenticationService->getAccessToken(true);
            $result = $this->dpdClient->findParcelShopsByGeoData($parameters, $delisId, $accessToken);
        }
        return $result->parcelShop;
    }
}
