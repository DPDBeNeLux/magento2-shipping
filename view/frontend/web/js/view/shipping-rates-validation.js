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
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-rates-validation-rules',
        '../model/shipping-rates-validator',
        '../model/shipping-rates-validation-rules'
    ],
    function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        dpdbeneluxProviderShippingRatesValidator,
        dpdbeneluxProviderShippingRatesValidationRules
    ) {
        "use strict";
        defaultShippingRatesValidator.registerValidator('dpdpredict', dpdbeneluxProviderShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dpdpredict', dpdbeneluxProviderShippingRatesValidationRules);

        defaultShippingRatesValidator.registerValidator('dpdguarantee18', dpdbeneluxProviderShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dpdguarantee18', dpdbeneluxProviderShippingRatesValidationRules);

        defaultShippingRatesValidator.registerValidator('dpdexpress12', dpdbeneluxProviderShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dpdexpress12', dpdbeneluxProviderShippingRatesValidationRules);

        defaultShippingRatesValidator.registerValidator('dpdexpress10', dpdbeneluxProviderShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dpdexpress10', dpdbeneluxProviderShippingRatesValidationRules);

        defaultShippingRatesValidator.registerValidator('dpdclassic', dpdbeneluxProviderShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dpdclassic', dpdbeneluxProviderShippingRatesValidationRules);

        defaultShippingRatesValidator.registerValidator('dpdsaturday', dpdbeneluxProviderShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dpdsaturday', dpdbeneluxProviderShippingRatesValidationRules);

        defaultShippingRatesValidator.registerValidator('dpdclassicsaturday', dpdbeneluxProviderShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dpdclassicsaturday', dpdbeneluxProviderShippingRatesValidationRules);

        defaultShippingRatesValidator.registerValidator('dpdpickup', dpdbeneluxProviderShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dpdpickup', dpdbeneluxProviderShippingRatesValidationRules);
        return Component;
    }
);