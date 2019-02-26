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

namespace DPDBenelux\Shipping\Plugin\Api\Data;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderExtensionInterface;
use Magento\Sales\Api\Data\OrderInterface;

class OrderInterfacePlugin
{
    /** @var OrderExtensionFactory $extensionFactory */
    private $extensionFactory;

    /**
     * @param OrderExtensionFactory $extensionFactory
     */
    public function __construct(OrderExtensionFactory $extensionFactory)
    {
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * @param OrderInterface               $entity
     * @param OrderExtensionInterface|null $extensionAttributes
     *
     * @return OrderExtensionInterface
     */
    public function afterGetExtensionAttributes(
        OrderInterface $entity,
        OrderExtensionInterface $extensionAttributes = null
    ): OrderExtensionInterface {
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->extensionFactory->create();
            $entity->setExtensionAttributes($extensionAttributes);
        }

        return $extensionAttributes;
    }
}
