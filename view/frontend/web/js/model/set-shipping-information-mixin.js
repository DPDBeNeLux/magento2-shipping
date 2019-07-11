define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'mage/translate'
], function ($, wrapper, quote, globalMessageList, $t) {
    'use strict';

    return function (shippingInformationAction) {

        return wrapper.wrap(shippingInformationAction, function (originalAction) {
            var selectedShippingMethod = quote.shippingMethod();
            if (selectedShippingMethod.carrier_code === 'dpdpickup' && selectedShippingMethod.method_code === 'dpdpickup' && window.oscRoute === undefined) {
                var parcelShopId = $('.parcelshopId').val();
                if (!parcelShopId) {
                    globalMessageList.addErrorMessage({ message: $t('You must select a parcelshop')});
                    jQuery(window).scrollTop(0);
                    return { done: function (){ } };
                }
            }
            return originalAction();
        });
    };
});
