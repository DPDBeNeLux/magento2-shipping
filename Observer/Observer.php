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
namespace DPDBenelux\Shipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;

class Observer implements ObserverInterface
{
	/**
	 * @var OrderRepository
	 */
	private $orderRepository;
	/**
	 * @var \Magento\Checkout\Model\Session
	 */
	private $checkoutSession;

	/**
	 * @var Order\AddressRepository
	 */
	private $addressRepository;
	

	public function __construct(
		OrderRepository $orderRepository,
		Order\AddressRepository $addressRepository,
		\Magento\Checkout\Model\Session $checkoutSession)
	{
		$this->orderRepository = $orderRepository;
		$this->checkoutSession = $checkoutSession;
		$this->addressRepository = $addressRepository;
	}

	public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$quote = $observer->getEvent()->getQuote();
		$order = $observer->getEvent()->getOrder();

		//file_put_contents('quote.txt', print_r($quote->debug(), true), FILE_APPEND);
		//file_put_contents('order.txt', print_r($order->debug(), true), FILE_APPEND);

		/** @var Order $order */
		if($order->getShippingMethod() == 'dpdpickup_dpdpickup')
		{
			//Get Object Manager Instance
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();

			//Load product by product id
			$order = $objectManager->create('Magento\Sales\Model\Order')->load($order->getId());
			/**  @var Order $order */
			//	echo '<pre>';
			//	print_r($order->debug());
			$shippingAddress = $order->getShippingAddress();
			$shippingAddress->setFirstname('DPD ParcelShop: ');
			$shippingAddress->setLastname($quote->getDpdCompany());
			$shippingAddress->setStreet($quote->getDpdStreet());
			$shippingAddress->setCity($quote->getDpdCity());
			$shippingAddress->setPostcode($quote->getDpdZipcode());
			$shippingAddress->setCountryId($quote->getDpdCountry());
			$shippingAddress->setTelephone('');
			$shippingAddress->save();

			$order->setDpdShopId($quote->getDpdParcelshopId());
			$order->save();

			//$order->setShippingAddress();
			//	print_r($order->getShippingAddress()->debug() );

//		$order->setDpdShopId(342342);
//		$order->save();
		}
	}
}