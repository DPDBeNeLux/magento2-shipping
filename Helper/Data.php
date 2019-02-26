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
namespace DPDBenelux\Shipping\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Sales\Model\Order;
use DPDBenelux\Shipping\Helper\Services\DPDPredictService;
use \Magento\Sales\Model\Convert\Order as OrderConvert;
use DPDBenelux\Shipping\Model\ShipmentLabelsFactory;

class Data extends AbstractHelper
{
    const DPD_GOOGLE_MAPS_API = 'carriers/dpdpickup/google_maps_api';

    /**
     * @var DPDPredictService
     */
    private $dpdPredictService;

    /**
     * @var OrderConvert
     */
    private $orderConvert;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var Order\Shipment\TrackFactory
     */
    private $trackFactory;

    /**
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    private $configWriter;

    /**
     * @var ShipmentLabelsFactory
     */
    private $shipmentLabelsFactory;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        DPDPredictService $DPDPredictService,
        OrderConvert $orderConvert,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        ShipmentLabelsFactory $shipmentLabelsFactory
    ) {
        $this->dpdPredictService = $DPDPredictService;
        $this->orderConvert = $orderConvert;
        $this->transactionFactory = $transactionFactory;
        $this->trackFactory = $trackFactory;
        $this->configWriter = $configWriter;
        $this->shipmentLabelsFactory = $shipmentLabelsFactory;

        parent::__construct($context);
    }

    public function getGoogleMapsApiKey()
    {
        return $this->scopeConfig->getValue(self::DPD_GOOGLE_MAPS_API);
    }

    /**
     * @param Order $order
     * @param Order\Shipment|null $shipment
     * @param int $parcels
     * @param bool $isReturn
     * @return array
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createShipment(Order $order, Order\Shipment $shipment = null, $parcels = 1, $isReturn = false)
    {
        $includeReturnLabel = $this->scopeConfig->getValue('dpdshipping/account_settings/includeReturnLabel');

        // Check if shipment variable if set, if not, check if order has a snipment
        if ($shipment === null) {
            // If this order doesn't have a shipment we create one
            if ($order->getShipmentsCollection()->count() == 0) {
                $orderShipment = $this->orderConvert->toShipment($order);

                // Loop through order items
                foreach ($order->getAllItems() as $orderItem) {
                    $qtyShipped = $orderItem->getQtyOrdered();

                    // Create shipment item with qty
                    $shipmentItem = $this->orderConvert->itemToShipmentItem($orderItem);
                    $shipmentItem->setQty($qtyShipped);

                    // Add shipment item to shipment
                    $orderShipment->addItem($shipmentItem);
                }

    //              $shipment->getOrder()->setIsInProcess(true);

                $orderShipment->register();

                $shipmentTransaction = $this->transactionFactory->create()
                    ->addObject($orderShipment)
                    ->addObject($orderShipment->getOrder());
                $shipmentTransaction->save();

                $shipment = $orderShipment;

            // If this has one shipment, use this one.
            } elseif ($order->getShipmentsCollection()->count() == 1) {
                $shipment = $order->getShipmentsCollection()->getFirstItem();
            }
        }

        // Delete old labels and tracking numbers if exists
        $dpdShipments = $this->shipmentLabelsFactory->create()->getCollection();
        $dpdShipments->addFieldToFilter("shipment_id", $shipment->getId());
        $dpdShipments->getData();

        foreach ($dpdShipments as $dpdShipment) {
            $dpdShipment->delete();
        }

        foreach ($order->getTracksCollection() as $tracking) {
            if ($tracking->getParentId() === $shipment->getId() && strpos($tracking->getCarrierCode(), 'dpd') === 0) {
                $tracking->delete();
            }
        }

        // Check if there's a problem with the session, if that's the case, reset the timestamp so we create a new one
        try {
            $result = $this->dpdPredictService->generateLabel($order, $isReturn, $shipment, $parcels);
        } catch (\Exception $ex) {
            if (strpos($ex->getMessage(), 'De client sessie is verlopen') !== false) {
                $this->configWriter->save('dpdshipping/accesstoken_created', 0);
                $result = $this->dpdPredictService->generateLabel($order, $isReturn, $shipment, $parcels);
            } else {
                throw $ex;
            }
        }

        // If the return label option is enable we create a return label
        if ($includeReturnLabel) {
            $resultReturnLabel = $this->dpdPredictService->generateLabel($order, $includeReturnLabel, $shipment, $parcels);
        }

        /**
         * @var Order\Shipment $shipment
         */
        $carrierCode = explode("_", $order->getShippingMethod())[0];

        $carrierTitle = $this->scopeConfig->getValue(
            'carriers/' . $carrierCode  . '/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $shipment->getStoreId()
        );

        $shipment->getResource()->save($shipment);

        foreach ($result["parcelInformation"] as $parcelLabelNumber) {
            $track = $this->trackFactory->create();
            $track->setShipment($shipment);
            $track->setTitle($carrierTitle);
            $track->setNumber($parcelLabelNumber->parcelLabelNumber);
            $track->setCarrierCode($carrierCode);
            $track->setOrderId($order->getId());
            $track->save();
        }

        // Merge the pdf request if a return label was found
        $pdfResult = array();
        $pdfResult[] = $result['parcellabelsPDF'];
        if ($includeReturnLabel) {
            $pdfResult[] = $resultReturnLabel['parcellabelsPDF'];
        }

        return $pdfResult;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDOrder(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        // if the shipping method starts with dpd
        if (strpos($shippingMethod, 'dpd') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Supply and array of binary PDF file and it'll combine them into one
     * @param array $pdfFiles
     */
    public function combinePDFFiles(array $pdfFiles)
    {
        $outputPdf = new \Zend_Pdf();
        foreach ($pdfFiles as $content) {
            if (stripos($content, '%PDF-') !== false) {
                $pdfLabel = \Zend_Pdf::parse($content);
                foreach ($pdfLabel->pages as $page) {
                    $outputPdf->pages[] = clone $page;
                }
            }
        }
        return $outputPdf->render();
    }
}
