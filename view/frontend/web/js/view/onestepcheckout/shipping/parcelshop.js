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
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/model/sidebar',
    'Magento_Checkout/js/view/shipping-information/address-renderer/default',
    'mage/translate'
], function ($, ko, Component, quote, shippingService, checkoutData, stepNavigator, sidebarMode, addressRenderer) {
    'use strict';

    return Component.extend({
        defaults: {
            isVisible: false,
            template: 'DPDBenelux_Shipping/ShippingInfo',
        },

        addRow: function() {
            var method = quote.shippingMethod();

            if (!(method === null) && method.carrier_code === 'dpdpickup' && method.method_code === 'dpdpickup') {
                $('#dpd_parcelshop_container').remove();
                if ($('#dpd_parcelshop_container').length === 0) {
                    if (!($('.checkout-shipping-method-maps').length === 0)) {
                        var colspan = $('.checkout-shipping-method-maps').parent().children().length;

                        var row = $(
                            '<div id="dpd_parcelshop_container">' +
                                '<div id="map_container">' +
                                    '<ul id="parcel_shop_list"></ul>' +
                                    '<div id="map_canvas" class="gmaps"></div>' +
                                    '<div class="search-shipping-address" style="display: none;">' +
                                        '<div class="container">' +
                                            '<svg aria-hidden="true" data-prefix="fas" data-icon="search" class="image" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">' +
                                                '<path fill="currentColor" d="M505 442.7L405.3 343c-4.5-4.5-10.6-7-17-7H372c27.6-35.3 44-79.7 44-128C416 93.1 322.9 0 208 0S0 93.1 0 208s93.1 208 208 208c48.3 0 92.7-16.4 128-44v16.3c0 6.4 2.5 12.5 7 17l99.7 99.7c9.4 9.4 24.6 9.4 33.9 0l28.3-28.3c9.4-9.4 9.4-24.6.1-34zM208 336c-70.7 0-128-57.2-128-128 0-70.7 57.2-128 128-128 70.7 0 128 57.2 128 128 0 70.7-57.2 128-128 128z"></path>' +
                                            '</svg>' +
                                            '<input class="text" placeholder="' + jQuery.mage.__('Search Parcelshop') + '" />' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                            '</div>'
                        );

                        row.insertAfter($('.checkout-shipping-method-maps').parent());
                        $('#map_container').on('click', '.search-shipping-address', this.openInput);
                        $('#map_container').on('keypress', '.search-shipping-address .text', this.searchParcelShops.bind(this));

                        $('#map_container').on('click', '.parcelshoplink', this.selectParcelShop.bind(this));
                        $('#map_container').on('click', '.invalidateParcel', this.invalidateParcel.bind(this));

                        this.getParcels();
                    }
                }
            }
            
            return (!(method === null)) ? (method.method_code + '_' + method.carrier_code) : null;
        },


        getParcels: function(query) {
            var callback = function(response) {
                if (response.success) {
                    this.failedGettingsParcels = false;
                    this.parcelShops = response.parcelshops;

                    $('#parcel_shop_list').css('height', window.checkoutConfig.dpd_googlemaps_height);

                    $('#map_canvas').css('height', window.checkoutConfig.dpd_googlemaps_height);
                    $('#map_canvas').css('max-width', window.checkoutConfig.dpd_googlemaps_width);

                    var map = new google.maps.Map($('#map_canvas').get(0), {
                        mapTypeId: google.maps.MapTypeId.ROAsetParcelshopImageDMAP,
                        mapTypeControl: false,
                        streetViewControl: false
                    });

                    var infowindow = new google.maps.InfoWindow();
                    var markerBounds = new google.maps.LatLngBounds();
                    var markerImage = new google.maps.MarkerImage(response.gmapsIcon, new google.maps.Size(57, 62), new google.maps.Point(0, 0), new google.maps.Point(0, 31));
                    var shadow = new google.maps.MarkerImage(response.gmapsIconShadow, new google.maps.Size(85, 55), new google.maps.Point(0, 0), new google.maps.Point(0, 55));

                    $('#parcel_shop_list').empty();

                    $.each(response.parcelshops, function(index, shop) {
                        var content = shop.gmapsMarkerContent;

                        var marker = new google.maps.Marker({
                            map: map,
                            position: new google.maps.LatLng(shop.gmapsCenterlat, shop.gmapsCenterlng),
                            icon: markerImage,
                            shadow: shadow
                        });

                        markerBounds.extend(new google.maps.LatLng(shop.gmapsCenterlat, shop.gmapsCenterlng));

                        var callback = function(marker) {
                            infowindow.setContent(content);
                            infowindow.open(map, marker);
                        };

                        google.maps.event.addListener(marker, 'click', callback.bind(this, marker));

                        map.fitBounds(markerBounds);

                        var item = $(
                            '<li>' +
                                '<span class="company">' + shop.company + '</span>' +
                                '<span class="houseno">' + shop.houseno + '</span>' +
                                '<span class="zipcode">' + shop.zipcode + '</span> ' +
                                '<span class="city">' + shop.city + '</span>' +
                                '<a class="parcelshoplink" id="' + shop.parcelShopId + '" href="#">' + jQuery.mage.__('Select Parcelshop') + '</a>' +
                            '</li>');

                        $('#parcel_shop_list').append(item);
                    });

                    $('#parcel_shop_list').show();
                    $('#map_container .search-shipping-address').show();
                }
                else {
                    this.failedGettingsParcels = true;

                    if (!(query)) {
                        $('#map_canvas').html(response.error_message);
                    }
                    else {
                        var error = $('<div class="error_message">' + response.error_message + '</div>');

                        $('#map_container').append(error);
                        $('#map_container').on('click', '.error_message', function() {
                            $(this).remove();
                        });
                    }
                }
            };

            if (query) {
                var data = {
                    query: query
                }
            }
            else {
                var shippingAddress = quote.shippingAddress();

                var data = {
                    postcode: shippingAddress.postcode,
                    countryId: shippingAddress.countryId,
                    street: shippingAddress.street
                };
            }

            var options = {
                method: 'POST',
                showLoader: true,
                url: window.checkoutConfig.dpd_parcelshop_url,
                data: data
            };

            $.ajax(options).done(callback.bind(this));
        },



        initObservable: function() {
            this._super().observe([
                'pickupAddresses',
                'postalCode',
                'city',
                'countryCode',
                'street',
                'hasAddress',
                'selectedOption'
            ]);

            this.selectedMethod = ko.computed(this.addRow, this);

            return this;
        },


        invalidateParcel: function(event) {
            event.preventDefault();

            $('.dpd-shipping-information').hide();

            var query = $('.search-shipping-address .text').val();

            if (query && !(this.failedGettingsParcels)) {
                this.getParcels(query);
            }
            else {
                this.getParcels();
            }
        },


        openInput: function(event) {
            if (!(event.target instanceof HTMLInputElement)) {
                if ($(this).css('width') === '20px') {
                    $(this).css('width', 'auto');
                }
                else {
                    $(this).css('width', '20px');
                }
            }
        },


        searchParcelShops: function(event) {
            if (event.key === "Enter") {
                event.preventDefault();

                this.getParcels($(event.target).val());
            }
        },


        selectParcelShop: function(event) {
            event.preventDefault();

            var shopId = event.target.id;

            if (!(shopId)) {
                shopId = event.target.parentNode.id;
            }

            var parcelShop = this.parcelShops[shopId];

            $('.dpd-shipping-information').show();

            $('#dpd_company').html(parcelShop.company);
            $('#dpd_street').html(parcelShop.houseno);
            $('#dpd_zipcode_and_city').html(parcelShop.zipcode + ' ' + parcelShop.city);
            $('#dpd_country').html(parcelShop.country);

            $.cookie('dpd-selected-parcelshop-id', parcelShop.parcelShopId);
            $.cookie('dpd-selected-parcelshop-company', parcelShop.company);
            $.cookie('dpd-selected-parcelshop-street', parcelShop.houseno);
            $.cookie('dpd-selected-parcelshop-zipcode', parcelShop.zipcode);
            $.cookie('dpd-selected-parcelshop-city', parcelShop.city);
            $.cookie('dpd-selected-parcelshop-country', parcelShop.country);

            window.dpdShippingAddress = parcelShop;

            var callback = function(response) {
                $('#parcel_shop_list').hide();
                $('#map_container .search-shipping-address').hide();
                $('#map_canvas').empty();
                $('#map_canvas').html(response);
            };

            var options = {
                method: 'POST',
                showLoader: true,
                url: window.checkoutConfig.dpd_parcelshop_save_url,
                data: parcelShop
            };

            $.ajax(options).done(callback.bind(this));
        }
    });
});