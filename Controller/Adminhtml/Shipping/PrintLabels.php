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
namespace DPDBenelux\Shipping\Controller\Adminhtml\Shipping;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Framework\View\Result\PageFactory;
use DPDBenelux\Shipping\Model\ShipmentLabelsFactory;
use DPDBenelux\Shipping\Helper\Services\DPDPredictService;
use Magento\Framework\App\Response\Http\FileFactory;

class PrintLabels extends \Magento\Backend\App\Action
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
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var \DPDBenelux\Shipping\Model\ShipmentLabelsFactory
     */
    private $shipmentLabelsFactory;

    /**
     * @var ShipmentFactory
     */
    private $shipmentFactory;

    /**
     * @var ShipmentRepository
     */
    private $shipmentRepository;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param OrderCollectionFactory $collectionFactory
     * @param \DPDBenelux\Shipping\Helper\Data $dataHelper
     * @param ShipmentLabelsFactory $shipmentLabelsFactory
     * @param PageFactory $pageFactory
     * @param ShipmentFactory $shipmentFactory
     * @param ShipmentRepository $shipmentRepository
     * @param DPDPredictService $predictService
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        OrderCollectionFactory $collectionFactory,
        \DPDBenelux\Shipping\Helper\Data $dataHelper,
        ShipmentLabelsFactory $shipmentLabelsFactory,
        PageFactory $pageFactory,
        ShipmentFactory $shipmentFactory,
        ShipmentRepository $shipmentRepository,
        DPDPredictService $predictService,
        FileFactory $fileFactory
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->dataHelper = $dataHelper;
        $this->resultPageFactory = $pageFactory;
        $this->shipmentLabelsFactory = $shipmentLabelsFactory;
        $this->predictService = $predictService;
        $this->shipmentFactory = $shipmentFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->fileFactory = $fileFactory;

        parent::__construct($context);
    }

    public function execute()
    {
        $shipment_id = $this->getRequest()->getParam("current_shipment");
        $parcels = $this->getRequest()->getParam("parcels");

        if (!$shipment_id || !$parcels) {
            $this->messageManager->addErrorMessage(__("Invalid method"));
            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        try {
            $shipment = $this->shipmentRepository->get($shipment_id);
            $order = $shipment->getOrder();

            $label = $this->dataHelper->createShipment($order, $shipment, $parcels);

            $labelPDFs = [];
            $labelPDFs = array_merge($labelPDFs, $label);

            if (count($labelPDFs) == 0) {
                $this->messageManager->addErrorMessage(
                    __('DPD - There are no shipping labels generated.')
                );

                return $this->_redirect($this->_redirect->getRefererUrl());
            }

            $resultPDF = $this->dataHelper->combinePDFFiles($labelPDFs);

            return $this->fileFactory->create(
                'DPD-shippinglabels.pdf',
                $resultPDF,
                DirectoryList::VAR_DIR,
                'application/pdf'
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->_redirect($this->_redirect->getRefererUrl());
        }
    }
}
