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
namespace DPDBenelux\Shipping\Controller\Parcelshops;

use DPDBenelux\Shipping\Helper\Data;
use Magento\Framework\View\Asset\Repository;

class Save extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \DPDBenelux\Shipping\Helper\Data
     */
    protected $data;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    protected $assetRepo;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Data $data,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        Repository $assetRepo,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {
        parent::__construct($context);

        $this->data = $data;
        $this->resultPageFactory = $resultPageFactory;
        $this->assetRepo = $assetRepo;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $parcelData = $this->getRequest()->getPostValue();

        $resultPage = $this->resultPageFactory->create();

        $quote = $this->checkoutSession->getQuote();

        $quote
        ->setDpdParcelshopId($parcelData['parcelShopId'])
        ->setDpdCompany($parcelData['company'])
        ->setDpdStreet($parcelData['houseno'])
        ->setDpdZipcode($parcelData['zipcode'])
        ->setDpdCity($parcelData['city'])
        ->setDpdCountry($parcelData['country'])
        ->setDpdExtraInfo($parcelData['extra_info']);

        $this->quoteRepository->save($quote);

        $block = $resultPage->getLayout()
            ->createBlock('DPDBenelux\Shipping\Block\ParcelshopInfo')
            ->setTemplate('DPDBenelux_Shipping::checkout/shipping/parcelshop-info.phtml');
        $block->setParcelshop($parcelData);
        $block->setQuote($quote);
        $blockHtml = $block->toHtml();
        $this->getResponse()->setBody($blockHtml);
    }
}
