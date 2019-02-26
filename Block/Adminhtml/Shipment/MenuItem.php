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

namespace DPDBenelux\Shipping\Block\Adminhtml\Shipment;

use Magento\Sales\Api\ShipmentRepositoryInterface;

class MenuItem
{
    private $shipmentRepository;

    public function __construct(ShipmentRepositoryInterface $shipmentRepository)
    {
        $this->shipmentRepository = $shipmentRepository;
    }

    public function beforePushButtons(
        \Magento\Backend\Block\Widget\Button\Toolbar\Interceptor $subject,
        \Magento\Framework\View\Element\AbstractBlock $context,
        \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
    ) {
        if (!$context instanceof \Magento\Shipping\Block\Adminhtml\View\Interceptor) {
            if (!$context instanceof \Magento\Shipping\Block\Adminhtml\View) {
                return [$context, $buttonList];
            }
        }
        $this->_request = $context->getRequest();

        if ($this->_request->getFullActionName() == 'adminhtml_order_shipment_view') {
            $buttonList->add(
                'dpd_generate_label',
                ['label' => __('DPD - Create label(s)'), 'class' => 'reset'],
                -1
            );
        }
    }
}
