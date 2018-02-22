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
namespace DPDBenelux\Shipping\Model\Carrier;
 
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;

class Dpdpickup extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'dpdpickup';

	/**
	 * @var \Magento\Shipping\Model\Tracking\ResultFactory
	 */
    protected $_trackFactory;

	/**
	 * @var \DPDBenelux\Shipping\Helper\TrackingPopupFactory
	 */
    protected $_trackingPopupFactory;

	/**
	 * @var \Magento\Shipping\Model\Tracking\Result\ErrorFactory
	 */
	protected $_trackErrorFactory;

    /**
     * @var string
     */
    protected $_defaultConditionName = 'package_weight';
	
	
    /**
     * @var \DPDBenelux\Shipping\Model\ResourceModel\Carrier\TablerateFactory
     */
    protected $_tablerateFactory;

	
    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
		\Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
		\Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \DPDBenelux\Shipping\Helper\TrackingPopupFactory $trackingPopupFactory,
        \DPDBenelux\Shipping\Model\ResourceModel\TablerateFactory $tablerateFactory,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_trackFactory = $trackFactory;
        $this->_trackingPopupFactory = $trackingPopupFactory;
        $this->_trackErrorFactory = $trackErrorFactory;
        $this->_tablerateFactory = $tablerateFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

	/**
	 * Needed for shipping and tracking information
	 * @return bool
	 */
	public function isTrackingAvailable()
	{
		return true;
	}

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['dpdpickup' => $this->getConfigData('name')];
    }

	/*public function getTrackingInfo($trackings)
	{
		return $this->getTracking($trackings);
	}*/

    public function getTracking($trackings)
	{
		$result = $this->_trackFactory->create();

		$error = $this->_trackErrorFactory->create();
		$error->setCarrier('dpdpickup');
		$error->setCarrierTitle($this->getConfigData('title'));
		$error->setTracking('');
		$error->setErrorMessage('Tracking not available');

		$result->append($error);
		return $result;
	}

    // This is not how it's supposed to be but there is possibly a bug in magento which prevents
	// the tracking popup to function correctly, this may be fixed in Magento 2.2
	// The proper way to do this is to implement the getTracking method
    public function getTrackingInfo()
	{
		return $this->_trackingPopupFactory->create();
	}

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return array|bool
     */
    public function getRate(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        return $this->_tablerateFactory->create()->getRate($request);
    }
	
    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }       
		
		/** @var \Magento\Shipping\Model\Rate\Result $result */
		$result = $this->_rateResultFactory->create();
		
		/** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
		$method = $this->_rateMethodFactory->create();
 
		$method->setCarrier('dpdpickup');
		$method->setCarrierTitle($this->getConfigData('title'));
 
		$method->setMethod('dpdpickup');
		$method->setMethodTitle($this->getConfigData('name'));
 
		if($this->getConfigData('rate_type') == 'table')
		{
			// Possible bug in Magento, new sessions post no data when fetching the shipping methods, only country_id: US
			// This prevents the tablerates from showing a 0,00 shipping price
			if(!$request->getDestPostcode() && $request->getDestCountryId() == 'US') {
				return false;
			}
			// Free shipping by qty
			$freeQty = 0;
			$freePackageValue = 0;
			if ($request->getAllItems()) {
				foreach ($request->getAllItems() as $item) {
					if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
						continue;
					}
					if ($item->getHasChildren() && $item->isShipSeparately()) {
						foreach ($item->getChildren() as $child) {
							if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
								$freeShipping = is_numeric($child->getFreeShipping()) ? $child->getFreeShipping() : 0;
								$freeQty += $item->getQty() * ($child->getQty() - $freeShipping);
							}
						}
					} elseif ($item->getFreeShipping()) {
						$freeShipping = is_numeric($item->getFreeShipping()) ? $item->getFreeShipping() : 0;
						$freeQty += $item->getQty() - $freeShipping;
						$freePackageValue += $item->getBaseRowTotal();
					}
				}
				$oldValue = $request->getPackageValue();
				$request->setPackageValue($oldValue - $freePackageValue);
			}

			$conditionName = $this->_scopeConfig->getValue('dpdshipping/tablerate/condition_name', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);
			$request->setConditionName($conditionName ? $conditionName : $this->_defaultConditionName);

			// Package weight and qty free shipping
			$oldWeight = $request->getPackageWeight();
			$oldQty = $request->getPackageQty();
			
			$request->setPackageWeight($request->getFreeMethodWeight());
			$request->setPackageQty($oldQty - $freeQty);
			$request->setShippingMethod('dpdpickup');

			$rate = $this->getRate($request);

			//print_r($rate);
			
			$method->setPrice($rate['price']);
			$method->setCost($rate['cost']);
			$result->append($method);
		}
		else
		{
			/*you can fetch shipping price from different sources over some APIs, we used price from config.xml - xml node price*/
			$amount = $this->getConfigData('price');
			
			$method->setPrice($amount);
			$method->setCost($amount);
			$result->append($method);
		}
 
 
        return $result;
    }
}