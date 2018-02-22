<?php

namespace DPDBenelux\Shipping\Model\ResourceModel\Tablerate;

class DataHashGenerator
{
	/**
	 * @param array $data
	 * @return string
	 */
	public function getHash(array $data)
	{
		$shippingMethod = $data['shipping_method'];
		$countryId = $data['dest_country_id'];
		$regionId = $data['dest_region_id'];
		$zipCode = $data['dest_zip'];
		$conditionValue = $data['condition_value'];

		return sprintf("%s-%s-%d-%s-%F", $shippingMethod, $countryId, $regionId, $zipCode, $conditionValue);
	}
}
