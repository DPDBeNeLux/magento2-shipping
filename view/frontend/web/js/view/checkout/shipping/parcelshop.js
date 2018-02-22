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
define([
	'jquery',
	'ko',
	'uiComponent',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/model/shipping-service',
	'Magento_Checkout/js/checkout-data',
	'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/model/sidebar',
    'Magento_Checkout/js/view/shipping-information/address-renderer/default'
], function ($, ko, Component, quote, shippingService, checkoutData, stepNavigator, sidebarMode) {
	'use strict';

	return Component.extend({
		 defaults: {
		 	//template: 'DPDBenelux_Shipping/checkout/shipping/parcelshops'
             template: 'DPDBenelux_Shipping/ShippingInfo',
			 isVisible: false
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

			var parcelShops = null;

			this.selectedMethod = ko.computed(function() {
				var method = quote.shippingMethod();
				var selectedMethod = method !== null ? method.carrier_code + '_' + method.method_code : null;

				if(selectedMethod === 'dpdpickup_dpdpickup') {
					if ($('#dpd_parcelshop_container').length === 0) {


						var columnCount = $('#label_method_dpdpickup_dpdpickup').parent().children().length;

						$('<tr><td id="dpd_parcelshop_container" colspan="' + columnCount + '"></td><tr>').insertAfter($('#label_method_dpdpickup_dpdpickup').parent());
						$('#dpd_parcelshop_container').html('<div id="parcelshop" class="parcelshopwrapper"><a href="#" id="get_parcels_link">Click here to select your Parcelshop</a><div id="map_container"><div id="map_canvas" class="gmaps"></div></div></div>');
					}
				} else {
					$('#dpd_parcelshop_container').remove();
                    jQuery('.dpd-shipping-information').hide();
				}
				return selectedMethod;
			}, this);

			$(document).ready(function() {

				$(document).on('click', '.parcelshoplink', function(e) {
					e.preventDefault();
					var shopId = e.target.id;
					if(!shopId) {
						shopId = e.target.parentNode.id;
					}

					var parcelShop = parcelShops[shopId];
					var shippingAddressData = checkoutData.getShippingAddressFromData();

					window.dpdShippingAddress = parcelShop;
					
					var newShippingAddress = {
									firstName:"DPD Parcelshop: ", 
									lastName: parcelShop.company, 
									street: {0:parcelShop.houseno, 1:""},
									postcode: parcelShop.zipcode,
									city: parcelShop.city,
									country_id: parcelShop.country,
									company: "",
									region: "",
									region_id: null,
									telephone: ""
									};

					var shippingAddressData = checkoutData.getShippingAddressFromData();

                    var parcelShopCountry = '';
                    parcelShopCountry = parcelShop.country;

					jQuery('.dpd-shipping-information').show();
                    jQuery('#dpd_company').html('DPD Parcleshop: ' + parcelShop.company);
                    jQuery('#dpd_street').html(parcelShop.houseno);
                    jQuery('#dpd_zipcode_and_city').html(parcelShop.zipcode + ' ' + parcelShop.city);
                    jQuery('#dpd_country').html(parcelShopCountry);

                    jQuery.cookie('dpd-selected-parcelshop-company', 'DPD Parcleshop: ' + parcelShop.company);
                    jQuery.cookie('dpd-selected-parcelshop-street', parcelShop.houseno);
                    jQuery.cookie('dpd-selected-parcelshop-zipcode', parcelShop.zipcode + ' ' + parcelShop.city);
                    jQuery.cookie('dpd-selected-parcelshop-country', parcelShopCountry);

					jQuery.ajax({
						method: 'POST',
						showLoader: true, // enable loader
						url : window.checkoutConfig.dpd_parcelshop_save_url,
						data : parcelShops[shopId]
					}).done(function (response) {
						$('#map_canvas').empty();
						$('#map_canvas').html(response);
					});
				});

				$(document).on('click', '.invalidateParcel', function(e) { getParcels(e); });
				$(document).on('click', '#get_parcels_link', function(e) { getParcels(e); });


				function getParcels(e)
				{
					e.preventDefault();

					var shippingAddress = quote.shippingAddress();

					$('#get_parcels_link').hide();

					jQuery.ajax({
						method: 'POST',
						showLoader: true, // enable loader
						url : window.checkoutConfig.dpd_parcelshop_url,
						data : {
							postcode: shippingAddress.postcode,
							countryId: shippingAddress.countryId,
							street:	shippingAddress.street
						}
					}).done(function (response) {
						var map_canvas = $('#map_canvas');

						if(response.success) {
							map_canvas.height(window.checkoutConfig.dpd_googlemaps_height);
							map_canvas.width(window.checkoutConfig.dpd_googlemaps_width);

							var map = new google.maps.Map(map_canvas.get(0), {
								mapTypeId: google.maps.MapTypeId.ROAsetParcelshopImageDMAP
							});

							var markerBounds = new google.maps.LatLngBounds();

							var marker_image = new google.maps.MarkerImage(response.gmapsIcon, new google.maps.Size(57, 62), new google.maps.Point(0, 0), new google.maps.Point(0, 31));
							var shadow = new google.maps.MarkerImage(response.gmapsIconShadow, new google.maps.Size(85, 55), new google.maps.Point(0, 0), new google.maps.Point(0, 55));
							var infowindow = new google.maps.InfoWindow();
							window.markers = new Array();

							parcelShops = response.parcelshops;

							$.each(response.parcelshops, function (index, shop) {

								var content = shop.gmapsMarkerContent;

								var marker = new google.maps.Marker({
									map: map,
									position: new google.maps.LatLng(shop.gmapsCenterlat, shop.gmapsCenterlng),
									icon: marker_image,
									shadow: shadow
								});

								markerBounds.extend(new google.maps.LatLng(shop.gmapsCenterlat, shop.gmapsCenterlng));

								window.markers.push(marker);
								google.maps.event.addListener(marker, 'click', (function (marker) {
									return function () {
										infowindow.setContent(content);
										infowindow.open(map, marker);
									}
								})(marker));

								map.fitBounds(markerBounds);
							});
						} else {

							$('#get_parcels_link').show();
							// Set the error message
							map_canvas.html(response.error_message);
						}
					});

				};
			});


			return this;
		},

        initializeDpdShopInfo: function() {
		 	if(this.selectedMethod() == 'dpdpickup_dpdpickup') {
                var dpdCompany = jQuery.cookie('dpd-selected-parcelshop-company');

                if(typeof dpdCompany !== null) {
                    jQuery('.dpd-shipping-information').show();
                    jQuery('#dpd_company').html(dpdCompany);
                    jQuery('#dpd_street').html(jQuery.cookie('dpd-selected-parcelshop-street'));
                    jQuery('#dpd_zipcode_and_city').html(jQuery.cookie('dpd-selected-parcelshop-zipcode'));
                    jQuery('#dpd_country').html(jQuery.cookie('dpd-selected-parcelshop-country'));
                }
			}
        },


	});


});
