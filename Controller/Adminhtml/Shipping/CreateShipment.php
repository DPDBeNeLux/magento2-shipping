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
namespace DPDBenelux\Shipping\Controller\Adminhtml\Shipping;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory;

class CreateShipment extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction
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
	 * @var FileFactory
	 */
	protected $fileFactory;

	/**
	 * @param Context $context
	 * @param Filter $filter
	 */
	public function __construct(Context $context,
								Filter $filter,
								CollectioNFactory $collectionFactory,
								\DPDBenelux\Shipping\Helper\Data $dataHelper,
								FileFactory $fileFactory)
	{
		$this->filter = $filter;
		$this->dataHelper = $dataHelper;
		$this->fileFactory = $fileFactory;
		$this->collectionFactory = $collectionFactory;
		parent::__construct($context, $filter);
	}

	public function massAction(AbstractCollection $collection)
	{
		try
		{
			$labelPDFs = array();

			if ($collection->getSize())
			{
				/** @var \Magento\Sales\Model\Order\Shipment $shipment */
				foreach ($collection as $shipment)
				{
					$order = $shipment->getOrder();
					if($this->dataHelper->isDPDPredictOrder($order))
					{
						$labelPDFs = array_merge($labelPDFs, $this->dataHelper->createShipment($order, false, $shipment));
					}
					if($this->dataHelper->isDPDPickupOrder($order))
					{
						$labelPDFs = array_merge($labelPDFs, $this->dataHelper->createShipment($order, false, $shipment));
					}
					if($this->dataHelper->isDPDSaturdayOrder($order))
					{
						$labelPDFs = array_merge($labelPDFs, $this->dataHelper->createShipment($order, true, $shipment));
					}
				}
			}

			if(count($labelPDFs) == 0)
			{
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
