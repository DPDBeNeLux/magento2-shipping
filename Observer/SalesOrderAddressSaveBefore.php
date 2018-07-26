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
namespace DPDBenelux\Shipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\OrderRepository;

class SalesOrderAddressSaveBefore implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    public function __construct(\Magento\Framework\App\State $state)
    {
        $this->state = $state;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // Ignore adminhtml
        if ($this->state->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
            return;
        }

        $shippingAddress = $observer->getEvent()->getAddress();
        /** @var Address $shippingAddress */

        $order = $shippingAddress->getOrder();
        /** @var Order $order */

        // Ignore all orders that aren't dpd pickup
        if ($order->getShippingMethod() != 'dpdpickup_dpdpickup') {
            return;
        }

        // If the address isn't the shipping address
        if ($shippingAddress->getAddressType() != 'shipping') {
            return;
        }

        $shippingAddress->setFirstname('DPD ParcelShop: ');
        $shippingAddress->setLastname($order->getDpdCompany());
        $shippingAddress->setStreet($order->getDpdStreet());
        $shippingAddress->setCity($order->getDpdCity());
        $shippingAddress->setPostcode($order->getDpdZipcode());
        $shippingAddress->setCountryId($order->getDpdCountry());

        // empty this otherwise you'd get customer data and DPD parcelshop data mixed up
        $shippingAddress->setCompany('');
        $shippingAddress->setTelephone('');
    }
}
