<?php
/**
 * This file is part of the Magento 2 Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2017  DPD Nederland B.V.
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
namespace DPDBenelux\Shipping\Controller\Parcelshops;

use Magento\Sales\Model\Order;
use DPDBenelux\Shipping\Helper\Data;
use DPDBenelux\Shipping\Helper\Services\DPDPickupService;
use Magento\Framework\View\Asset\Repository;

class Index extends \Magento\Framework\App\Action\Action
{
	private $data;

	private $DPDPickupService;

	/**
	 * @var \Magento\Framework\Controller\Result\JsonFactory
	 */
	protected $resultJsonFactory;

	private $assetRepo;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		Data $data,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		DPDPickupService $DPDPickupService,
		Repository $assetRepo
	)
	{
		parent::__construct($context);

		$this->data = $data;
		$this->resultJsonFactory = $resultJsonFactory;
		$this->DPDPickupService = $DPDPickupService;
		$this->assetRepo = $assetRepo;
	}


	public function getGoogleMapsCenter($street, $postcode, $countryId)
	{
		try
		{
			$apiKey = $this->data->getGoogleMapsApiKey();
			$addressToInsert = '';
			//foreach ($street as $str)
			//{
				//$addressToInsert .= $str . " ";
			//}
			$addressToInsert .= $postcode . " " . $countryId;
			$url = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $apiKey . '&address=' . urlencode($addressToInsert) . '&sensor=false';
			$source = file_get_contents($url);
			$obj = json_decode($source);
			$LATITUDE = $obj->results[0]->geometry->location->lat;
			$LONGITUDE = $obj->results[0]->geometry->location->lng;
			
		}
		catch(\Exception $ex)
		{
			echo $ex->getMessage();
			return null;
		}
		return [$LATITUDE, $LONGITUDE];
	}

	/**
	 * Execute view action
	 *
	 * @return \Magento\Framework\Controller\ResultInterface
	 */
	public function execute()
	{
		//$this->_view->loadLayout();
		//$this->_view->renderLayout();

		/** @var \Magento\Framework\Controller\Result\Json $result */
		$result = $this->resultJsonFactory->create();
		$resultData = array();

		$post = $this->getRequest()->getPostValue();

		if(!isset($post['street']) || !isset($post['postcode']) || !isset($post['countryId']))
		{
			$resultData['success'] = false;
			$resultData['error_message'] = __('No address found');
			return $result->setData($resultData);
		}

		$coordinates = $this->getGoogleMapsCenter($post['street'], $post['postcode'], $post['countryId']);
		if($coordinates == null)
		{
			$resultData['success'] = false;
			$resultData['error_message'] = __('No address found');
			return $result->setData($resultData);
		}

		$parcelShops = $this->DPDPickupService->getParcelShops($coordinates[0], $coordinates[1]);

		$params = array('_secure' => $this->getRequest()->isSecure());

		$resultData['success'] = true;
		$resultData['center_lat'] = $coordinates[0];
		$resultData['center_long'] = $coordinates[1];

		$resultData["gmapsIcon"] = $this->assetRepo->getUrlWithParams('DPDBenelux_Shipping::images/icon_parcelshop.png', $params);
		$resultData["gmapsIconShadow"] = $this->assetRepo->getUrlWithParams('DPDBenelux_Shipping::images/icon_parcelshop_shadow.png', $params);

		foreach($parcelShops as $shop)
		{
			if($shop->isoAlpha2 != 'NL')
				continue;
			$parcelShop = array();
			$parcelShop['parcelShopId'] = $shop->parcelShopId;
			$parcelShop['company'] = trim($shop->company);
			$parcelShop['houseno'] = $shop->street . " " . $shop->houseNo;
			$parcelShop['zipcode'] = $shop->zipCode;
			$parcelShop['city'] = $shop->city;
			$parcelShop['country'] = $shop->isoAlpha2;
			$parcelShop['gmapsCenterlat'] = $shop->latitude;
			$parcelShop['gmapsCenterlng'] = $shop->longitude;
			$parcelShop['special'] = false;

			$parcelShop['extra_info'] = json_encode(array_filter(array(
				'Opening hours' => (isset($shop->openingHours) && $shop->openingHours != "" ? json_encode($shop->openingHours) : ''),
				'Telephone' => (isset($shop->phone) && $shop->phone != "" ? $shop->phone : ''),
				'Website' => (isset($shop->homepage) && $shop->homepage != "" ? '<a href="' . 'http://' . $shop->homepage . '" target="_blank">' . $shop->homepage . '</a>' : ''),
				'Extra info' => (isset($shop->extraInfo) && $shop->extraInfo != "" ? $shop->extraInfo : ''))));

			$parcelShop['gmapsMarkerContent'] = $this->_getMarkerHtml($shop, false);

			$resultData['parcelshops'][$shop->parcelShopId] = $parcelShop;
		}

		return $result->setData($resultData);
	}

	/**
	 * Gets html for the marker info bubbles.
	 *
	 * @param $shop
	 * @param $special
	 * @return string
	 */
	protected function _getMarkerHtml($shop, $special)
	{
		$image = $this->assetRepo->getUrlWithParams('DPDBenelux_Shipping::images/dpd_parcelshop_logo.png', array('_secure' => $this->getRequest()->isSecure()));
		$routeIcon = $this->assetRepo->getUrlWithParams('DPDBenelux_Shipping::images/icon_route.png', array('_secure' => $this->getRequest()->isSecure()));

		$html = '<div class="content">
            <table style="min-width:250px" cellpadding="3" cellspacing="3" border="0">
                <tbody>
                    <tr>
                        <td rowspan="3" width="90" style="padding-top:3px; padding-bottom:3px;"><img class="parcelshoplogo bubble" style="width:80px; height:62px;" src="' . $image . '" alt="logo"/></td>
                        <td><strong>' . ($special ? $shop->getParcelshopPudoName() : $shop->company) . '</strong></td>
                    </tr>
                    <tr>
                        <td style="padding-top:3px; padding-bottom:3px;">' . ($special ? $shop->getData('parcelshop_address_1') : $shop->street . " " . $shop->houseNo) . '</td>
                    </tr>
                    <tr>
                        <td style="padding-top:3px; padding-bottom:3px;">' . ($special ? $shop->getParcelshopPostCode() . ' ' . $shop->getParcelshopTown() : $shop->zipCode . ' ' . $shop->city) . '</td>
                    </tr>
                </tbody>
            </table>
            <div class="dpdclear"></div>
            ';


		if (!$special && isset($shop->openingHours) && $shop->openingHours != "") {
			$html .= '<div class="dotted-line">
            <table>
            <tbody>';
			foreach ($shop->openingHours as $openinghours) {
				$html .= '<tr><td style="padding-right:10px; padding-top:3px; padding-bottom:3px;"><strong>' . $openinghours->weekday . '</strong></td><td style="padding-right:10px; padding-top:3px; padding-bottom:3px;">' . $openinghours->openMorning . ' - ' . $openinghours->closeMorning . '
            </td><td style="padding-right:10px; padding-top:3px; padding-bottom:3px;">' . $openinghours->openAfternoon . ' - ' . $openinghours->closeAfternoon . '</td></tr>';
			}
			$html .= '</tbody>
            </table>
            </div><div class="dpdclear"></div>';
		}
		elseif($special && $shop->getParcelshopOpeninghours() && $shop->getParcelshopOpeninghours()!=""){

			$html .= '<div class="dotted-line">
            <table>
            <tbody>';
			foreach (json_decode($shop->getParcelshopOpeninghours()) as $openinghours) {

				$html .= '<tr><td style="padding-right:10px; padding-top:3px; padding-bottom:3px;"><strong>' . $openinghours['weekday'] . '</strong></td><td style="padding-right:10px; padding-top:3px; padding-bottom:3px;">' . $openinghours['openMorning'] . ' - ' . $openinghours['closeMorning'] . '
            </td><td>' . $openinghours['openAfternoon'] . ' - ' . $openinghours['closeAfternoon'] . '</td></tr>';
			}
			$html .= '</tbody>
            </table>
            </div><div class="dpdclear"></div>';
		}


		$html .= '<div class="dotted-line">
                    <table>
                        <tbody>
                            <tr style="cursor: pointer;">
                                <td id="' . ($special ? $shop->getParcelshopDelicomId() : $shop->parcelShopId) . '" class="parcelshoplink" style="width: 25px;"><img src="'.$routeIcon .'" alt="route" width="16" height="16" ></td>
                                <td id="' . ($special ? $shop->getParcelshopDelicomId() : $shop->parcelShopId) . '" class="parcelshoplink"><strong>' . __('Ship to this Pickup point.') . '</strong></td>
                            </tr>
                        </tbody>
                    </table>
                  </div></div><div class="dpdclear"></div>';
		return $html;
	}
}