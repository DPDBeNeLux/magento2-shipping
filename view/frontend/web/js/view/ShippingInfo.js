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
define([
    'Magento_Checkout/js/view/shipping-information/address-renderer/default',
    'ko',
    'jquery',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/model/sidebar'
], function (
    Component,
    ko,
    $,
    stepNavigator,
    sidebarModel
) {
    return Component.extend({
        /*defaults: {
            template: 'DPDBenelux_Shipping/ShippingInfo',
            isVisible: false,
            address: {
                firstname: '',
                lastname: '',
                street: '',
                city: '',
                postcode: '',
                countryId: '',
            }
        },*/
        initObservable : function () {
            return this;
            this._super().observe([
                'address',
                'isVisible'
            ]);

            this.address =  ko.observable(null);

            if (typeof window.dpdShippingAddress === undefined) {
                return this;
            }


            this.address = window.dpdShippingAddress;
            console.log(window.dpdShippingAddress);
            /*this.isVisible = ko.computed(function () {
                return State.currentSelectedShipmentType() === 'pickup';
            });
            */

            return this;
        },

        address: function () {

        },

        back: function () {
            sidebarModel.hide();
            stepNavigator.navigateTo('shipping');
        }
    });
});