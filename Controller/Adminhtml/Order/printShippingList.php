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
namespace DPDBenelux\Shipping\Controller\Adminhtml\Order;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\Order;
use Magento\Framework\View\Result\PageFactory;
use \DPDBenelux\Shipping\Model\ShipmentLabelsFactory;
use DPDBenelux\Shipping\Helper\Services\DPDPredictService;

class printShippingList extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Ui\Component\MassAction\Filter
     */
    protected $filter;

    /**
     * @var object
     */
    protected $collectionFactory;

    /**
     * @var \DPDBenelux\Shipping\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var DPDPredictService
     */
    protected $predictService;

    /**
     * @var \DPDBenelux\Shipping\Model\ShipmentLabelsFactory
     */
    private $shipmentLabelsFactory;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param OrderCollectionFactory $collectionFactory
     * @param \DPDBenelux\Shipping\Helper\Data $dataHelper
     * @param ShipmentLabelsFactory $shipmentLabelsFactory
     * @param PageFactory $pageFactory
     * @param DPDPredictService $predictService
     */
    public function __construct(
        Context $context,
        Filter $filter,
        OrderCollectionFactory $collectionFactory,
        \DPDBenelux\Shipping\Helper\Data $dataHelper,
        ShipmentLabelsFactory $shipmentLabelsFactory,
        PageFactory $pageFactory,
        DPDPredictService $predictService
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->dataHelper = $dataHelper;
        $this->resultPageFactory = $pageFactory;
        $this->shipmentLabelsFactory = $shipmentLabelsFactory;
        $this->predictService = $predictService;

        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection = $this->filter->getCollection($collection);

            $orders = [];
            $orders["list"] = [];

            $count = 0;
            foreach ($collection as $order) {
                if (!$this->dataHelper->isDPDOrder($order)) {
                    continue;
                }

                $shipmentLabels = $this->shipmentLabelsFactory->create()
                    ->getCollection()
                    ->addFieldToFilter('order_id', ["eq", $order->getId()])
                    ->addFieldToFilter('is_return', ["eq", '0']);

                foreach ($shipmentLabels as $shipmentLabel) {
                    $paracelNumbers = unserialize($shipmentLabel->getLabelNumbers());

                    foreach ($paracelNumbers as $paracelData) {
                        $count++;
                        $orders["list"][] = [
                            "count" => $count,
                            "parcelLabelNumber" => $paracelData['parcel_number'],
                            "weight" => round($paracelData['weight'], 2)."g",
                            "carrierName" => $order->getShippingDescription(),
                            "customerName" => $order->getShippingAddress()->getName(),
                            "address" => implode($order->getShippingAddress()->getStreet(), ' '),
                            "zipCode" => $order->getShippingAddress()->getPostcode(),
                            "city" => $order->getShippingAddress()->getCity(),
                            "referenceNumber" => $order->getIncrementId(),
                            "referenceNumber2" => $shipmentLabel->getShipmentIncrementId()
                        ];
                    }
                }
            }


            $resultPage = $this->resultPageFactory->create();
            $blockInstance = $resultPage->getLayout()->getBlock("printshippinglist");
            $blockInstance->setTemplate("DPDBenelux_Shipping::printshippinglist.phtml");

            $blockInstance->assign([
                'sender' => $this->predictService->getSenderData(),
                'orders' => $orders
            ]);

            echo $blockInstance->toHtml();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->_redirect($this->_redirect->getRefererUrl());
        }
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function redirect()
    {
        $redirectPath = 'sales/order/index';

        $resultRedirect = $this->resultRedirectFactory->create();

        $resultRedirect->setPath($redirectPath);

        return $resultRedirect;
    }
}
