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
declare(strict_types=1);

namespace DPDBenelux\Shipping\Plugin\Api;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection;

class OrderRepositoryInterfacePlugin
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param Collection               $collection
     *
     * @return Collection
     */
    public function afterGetList(OrderRepositoryInterface $orderRepository, Collection $collection): Collection
    {
        foreach ($collection->getItems() as $order) {
            /** @var OrderInterface $order */

            $this->afterGet($orderRepository, $order);
        }

        return $collection;
    }

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderInterface           $entity
     *
     * @return OrderInterface
     */
    public function afterGet(OrderRepositoryInterface $orderRepository, OrderInterface $entity): OrderInterface
    {
        $extensionAttributes = $entity->getExtensionAttributes();
        $extensionAttributes->setDpdParcelshopId($entity->getData('dpd_parcelshop_id'));
        $entity->setExtensionAttributes($extensionAttributes);

        return $entity;
    }
}
