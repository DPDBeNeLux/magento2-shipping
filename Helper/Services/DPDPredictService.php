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

use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use DPDBenelux\Shipping\Helper\DPDClient;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Filesystem\DirectoryList;
use DPDBenelux\Shipping\Model\ShipmentLabelsFactory;

class DPDPredictService extends AbstractHelper
{
    const DPD_PRINT_FORMAT = 'dpdshipping/account_settings/print_format';
    const DPD_PRINT_PHONE_NUMBER = 'dpdshipping/account_settings/printPhoneNumber';
    const DPD_PRINT_ORDER_ID = 'dpdshipping/account_settings/printOrderId';

    /**
     * Used to access the accesstoken, depot and delisId
     * @var AuthenticationService
     */
    private $authenticationService;

    /**
     * @var \DPDBenelux\Shipping\Model\ShipmentLabelsFactory
     */
    private $shipmentLabelsFactory;

    /**
     * @var DPDClient
     */
    private $dpdClient;

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    private $filesystem;
    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    private $directoryList;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \DPDBenelux\Shipping\Helper\Services\AuthenticationService $authenticationService,
        \Magento\Framework\Filesystem\Io\File $filesystem,
        DPDClient $DPDClient,
        \Magento\Framework\Filesystem\DirectoryList $directoryList,
        ShipmentLabelsFactory $shipmentLabelsFactory
    ) {
        $this->authenticationService = $authenticationService;
        $this->shipmentLabelsFactory = $shipmentLabelsFactory;
        $this->dpdClient = $DPDClient;
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
        parent::__construct($context);
    }


    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDPredictOrder(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        if ($shippingMethod == 'dpdpredict_dpdpredict') {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDPickupOrder(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        if ($shippingMethod == 'dpdpickup_dpdpickup') {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDSaturdayOrder(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        if ($shippingMethod == 'dpdsaturday_dpdsaturday') {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDClassicSaturdayOrder(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        if ($shippingMethod == 'dpdclassicsaturday_dpdclassicsaturday') {
            return true;
        }

        return false;
    }


    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDGuarantee18Order(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        if ($shippingMethod == 'dpdguarantee18_dpdguarantee18') {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDExpress12Order(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        if ($shippingMethod == 'dpdexpress12_dpdexpress12') {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDExpress10Order(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        if ($shippingMethod == 'dpdexpress10_dpdexpress10') {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDClassicOrder(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        if ($shippingMethod == 'dpdclassic_dpdclassic') {
            return true;
        }

        return false;
    }


    /**
     * @param Order $order
     * @param bool $isReturn
     * @param Order\Shipment $shipment
     * @param int $parcels
     * @return array
     * @throws \Exception
     */
    public function generateLabel(Order $order, $isReturn = false, Order\Shipment $shipment = null, $parcels = 1)
    {
        $accessToken = $this->authenticationService->getAccessToken();
        $delisId = $this->authenticationService->getDelisId();
        $depot = $this->authenticationService->getDepot();

        $senderData = $this->getSenderData($order);
        if ($senderData['zipCode'] == '' || $senderData['street'] == '' || $senderData['city'] == '') {
            throw new \Exception(__('[DPD] Your store address is empty. Please open the configuration and set an address'));
        }
        $receiverData = $this->getReceiverData($order);
        $orderWeight = $this->getOrderWeight($order);
        $predictEmail = $this->getPredictEmail($order);

        // Fallback option for 1.0.6 to 1.0.7 change
        $parcelShopId = $order->getDpdParcelshopId();
        if ($parcelShopId == '') {
            $parcelShopId = $order->getDpdShopId();
        }

        $shipmentData = [
            'printOptions' => [
                'printerLanguage' => 'PDF',
                'paperFormat' => $this->getWebsiteConfig(self::DPD_PRINT_FORMAT, $order),
            ],
            'order' => [
                'generalShipmentData' => [
                    'sendingDepot' => $depot,
                    'product' => 'CL',
                    'sender' => $senderData,
                    'recipient' => $receiverData,
                ]
            ],
        ];

        $shipmentData['order']["parcels"] = [];
        for ($x = 1; $x <= $parcels; $x++) {
            $shipmentData['order']["parcels"][] = [
                'customerReferenceNumber1' => ($this->getWebsiteConfig(self::DPD_PRINT_ORDER_ID, $order, true) ? $order->getIncrementId() : null),
                'customerReferenceNumber2' => $parcelShopId,
                'weight' => $orderWeight / $parcels,
                'returns' => $isReturn,
            ];
        }

        $shipmentData['order']['productAndServiceData']['orderType'] = 'consignment';

        // Specific shippingmethod data
        if ($this->isDPDPredictOrder($order) || $this->isDPDSaturdayOrder($order)) {
            $shipmentData['order']['productAndServiceData']['predict'] = [
                'channel' => 1,
                'value' => $predictEmail,
            ];
        } elseif ($this->isDPDPickupOrder($order)) {
            $shipmentData['order']['productAndServiceData']['parcelShopDelivery'] = [
                'parcelShopId' => $parcelShopId,
                'parcelShopNotification' => array(
                    'channel' => 1,
                    'value' => $predictEmail
                )
            ];
        } elseif ($this->isDPDGuarantee18Order($order)) {
            $shipmentData['order']['generalShipmentData']['product'] = 'E18';
        } elseif ($this->isDPDExpress12Order($order)) {
            $shipmentData['order']['generalShipmentData']['product'] = 'E12';
        } elseif ($this->isDPDExpress10Order($order)) {
            $shipmentData['order']['generalShipmentData']['product'] = 'E10';
        }

        if ($this->isDPDGuarantee18Order($order) || $this->isDPDExpress12Order($order) || $this->isDPDExpress10Order($order)) {
            if ($this->isDPDPickupOrder($order)) {
                $phone = $order->getBillingAddress()->getTelephone();
            } else {
                $phone = $order->getShippingAddress()->getTelephone();
            }

            $shipmentData['order']['generalShipmentData']['recipient']['contact'] = "Contact";
            $shipmentData['order']['generalShipmentData']['recipient']['phone'] = $phone;
            $shipmentData['order']['generalShipmentData']['recipient']['email'] = $order->getCustomerEmail();
        }

        if (!$isReturn && ($this->isDPDSaturdayOrder($order) || $this->isDPDClassicSaturdayOrder($order))) {
            $shipmentData['order']['productAndServiceData']['saturdayDelivery'] = true;
        }

        $result = $this->dpdClient->storeOrders($shipmentData, $delisId, $accessToken);
        $parcelInformation = $result->orderResult->shipmentResponses->parcelInformation;
        if (is_object($parcelInformation)) {
            $parcelInformation = [$parcelInformation];
        }


        $labelData = array();
        foreach ($parcelInformation as $information) {
            $labelData[] = [
                'parcel_number' => $information->parcelLabelNumber,
                'weight' => $orderWeight / $parcels
            ];
        }
        $labelDataSerialized = serialize($labelData);

        $saveLabelAsFile = $this->getWebsiteConfig('dpdshipping/account_settings/save_label_file', $order, true);
        $labelPath = $this->getWebsiteConfig('dpdshipping/account_settings/label_path', $order);

        if (empty($labelPath)) {
            $labelPath = $this->directoryList->getRoot() . '/var/dpd_labels/';
        }

        $labelPath = rtrim($labelPath, '/') . '/';

        $labelName = sprintf('%s-%s.pdf', $order->getIncrementId(), $result->orderResult->shipmentResponses->mpsId);

        // Save the label to the database
        $shipmentLabels = $this->shipmentLabelsFactory->create();
        $shipmentLabels->setLabelNumbers($labelDataSerialized);
        $shipmentLabels->setMpsId($result->orderResult->shipmentResponses->mpsId);
        $shipmentLabels->setShipmentId($shipment->getId());
        $shipmentLabels->setShipmentIncrementId($shipment->getIncrementId());
        $shipmentLabels->setOrderId($order->getId());

        if (!$saveLabelAsFile) {
            $shipmentLabels->setLabel($result->orderResult->parcellabelsPDF);
        } else {
            $shipmentLabels->setLabelPath($labelPath . $labelName);
        }

        $shipmentLabels->setIsReturn($isReturn ? "1" : "0");
        $shipmentLabels->save();

        // Write file to a directory
        if ($saveLabelAsFile) {
            if (!file_exists($labelPath)) {
                mkdir($labelPath, 0755, true);
            }
            $this->filesystem->write($labelPath . $labelName, $result->orderResult->parcellabelsPDF);
        }

        return [
            'senderData' => $senderData,
            'parcellabelsPDF' => $result->orderResult->parcellabelsPDF,
            'parcelInformation' => $parcelInformation,
            'mpsId' => $result->orderResult->shipmentResponses->mpsId,
        ];
    }

    public function getSenderData(\Magento\Sales\Model\Order $order)
    {
        $name = $this->getStoreConfig('dpdshipping/sender_address/name1', $order);
        $street = $this->getStoreConfig('dpdshipping/sender_address/street', $order);
        $houseNo = $this->getStoreConfig('dpdshipping/sender_address/houseNo', $order);
        $zipCode = $this->getStoreConfig('dpdshipping/sender_address/zipCode', $order);
        $city = $this->getStoreConfig('dpdshipping/sender_address/city', $order);
        $country = $this->getStoreConfig('dpdshipping/sender_address/country', $order);
        
        return [
            'name1' => $name,
            'street' => $street,
            'houseNo' => $houseNo,
            'country' => $country,
            'zipCode' => $zipCode,
            'city' => $city
        ];
    }

    public function getReceiverData(\Magento\Sales\Model\Order $order)
    {
        if ($this->isDPDPickupOrder($order)) {
            $billingAddress = $order->getBillingAddress();

            $street = $billingAddress->getStreet();
            $fullStreet = implode(' ', $street);

            $recipient = array(
                'name1'            => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
                'name2'            => $billingAddress->getCompany(),
                'street'           => $fullStreet,
                'houseNo'          => '',
                'zipCode'          => strtoupper(str_replace(' ', '', $billingAddress->getPostcode())),
                'city'             => $billingAddress->getCity(),
                'country'          => $billingAddress->getCountryId(),
                'phone'             => ($this->getWebsiteConfig(self::DPD_PRINT_PHONE_NUMBER, $order, true) ? $billingAddress->getTelephone() : null)
            );
        } else {
            $shippingAddress = $order->getShippingAddress();

            $street = $shippingAddress->getStreet();
            $fullStreet = implode(' ', $street);

            $recipient = array(
                'name1'            => $shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname(),
                'name2'            => $shippingAddress->getCompany(),
                'street'           => $fullStreet,
                'houseNo'          => '',
                'zipCode'          => strtoupper(str_replace(' ', '', $shippingAddress->getPostcode())),
                'city'             => $shippingAddress->getCity(),
                'country'          => $shippingAddress->getCountryId(),
                'phone'            => ($this->getWebsiteConfig(self::DPD_PRINT_PHONE_NUMBER, $order, true) ? $shippingAddress->getTelephone() : null)
            );
        }

        return $recipient;
    }

    public function getOrderWeight(\Magento\Sales\Model\Order $order)
    {
        $orderWeight = $order->getWeight();

        $weightUnit = $this->scopeConfig->getValue('general/locale/weight_unit');
        if ($weightUnit == '') {
            $weightUnit = 'lbs';
        }

        if ($weightUnit == 'lbs') {
            $orderWeight *=  0.45359237;
        }

        // Weight is in KG so multiply with 100
        $orderWeight *= 100;

        if ($orderWeight == 0) {
            $orderWeight = 600;
        }

        return round($orderWeight, 0);
    }

    public function getPredictEmail(\Magento\Sales\Model\Order $order)
    {
        return $order->getCustomerEmail();
    }

    public function getWebsiteConfig($path, \Magento\Sales\Model\Order $order, $isFlag = false)
    {
        if ($isFlag) {
            return $this->scopeConfig->isSetFlag(
                $path,
                ScopeInterface::SCOPE_WEBSITE,
                $order->getStore()->getWebsiteId()
            );
        }

        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_WEBSITE,
            $order->getStore()->getWebsiteId()
        );
    }

    public function getStoreConfig($path, \Magento\Sales\Model\Order $order, $isFlag = false)
    {
        if ($isFlag) {
            return $this->scopeConfig->isSetFlag(
                $path,
                ScopeInterface::SCOPE_STORE,
                $order->getStoreId()
            );
        }

        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );
    }
}
