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
namespace DPDBenelux\Shipping\Model\ResourceModel;

use DPDBenelux\Shipping\Model\ResourceModel\Tablerate\RateQuery;
use DPDBenelux\Shipping\Model\ResourceModel\Tablerate\RateQueryFactory;
use Magento\Framework\Filesystem\DirectoryList;

use DPDBenelux\Shipping\Model\ResourceModel\Tablerate\Import;

class Tablerate extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    /**
     * Import table rates website ID
     *
     * @var int
     */
    protected $_importWebsiteId = 0;

    /**
     * Errors in import process
     *
     * @var array
     */
    protected $_importErrors = [];

    /**
     * Count of imported table rates
     *
     * @var int
     */
    protected $_importedRows = 0;

    /**
     * Array of unique table rate keys to protect from duplicates
     *
     * @var array
     */
    protected $_importUniqueHash = [];

    /**
     * Array of countries keyed by iso2 code
     *
     * @var array
     */
    protected $importIso2Countries;

    /**
     * Array of countries keyed by iso3 code
     *
     * @var array
     */
    protected $importIso3Countries;

    /**
     * Associative array of countries and regions
     * [country_id][region_code] = region_id
     *
     * @var array
     */
    protected $importRegions;

    /**
     * Import Table Rate condition name
     *
     * @var string
     */
    protected $importConditionName;

    /**
     * @var string
     */
    protected $shippingMethod;

    /**
     * Array of condition full names
     *
     * @var array
     */
    protected $_conditionFullNames = [];

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     * @since 100.1.0
     */
    protected $coreConfig;

    /**
     * @var \Psr\Log\LoggerInterface
     * @since 100.1.0
     */
    protected $logger;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     * @since 100.1.0
     */
    protected $storeManager;

    /**
     * @var \Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate
     * @since 100.1.0
     */
    protected $carrierTablerate;

    /**
     * @var \Magento\Directory\Model\ResourceModel\Country\CollectionFactory
     */
    protected $countryCollectionFactory;
    /**
     * @var \Magento\Directory\Model\ResourceModel\Region\CollectionFactory
     */
    protected $regionCollectionFactory;

    /**
     * Filesystem instance
     *
     * @var \Magento\Framework\Filesystem
     * @since 100.1.0
     */
    protected $filesystem;

    /**
     * @var Import
     */
    private $import;

    /**
     * @var RateQueryFactory
     */
    private $rateQueryFactory;

    /**
     * Tablerate constructor.
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $coreConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\OfflineShipping\Model\Carrier\Tablerate $carrierTablerate
     * @param Filesystem $filesystem
     * @param RateQueryFactory $rateQueryFactory
     * @param Import $import
     * @param null $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $coreConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\OfflineShipping\Model\Carrier\Tablerate $carrierTablerate,
        \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollectionFactory,
        \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionCollectionFactory,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory,
        \Magento\Framework\Filesystem $filesystem,
        Import $import,
        RateQueryFactory $rateQueryFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->coreConfig = $coreConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->carrierTablerate = $carrierTablerate;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->regionCollectionFactory = $regionCollectionFactory;
        $this->readFactory = $readFactory;
        $this->filesystem = $filesystem;
        $this->import = $import;
        $this->rateQueryFactory = $rateQueryFactory;
    }


    /**
     * Define main table and id field name
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('dpd_shipping_tablerate', 'pk');
    }

    /**
     * Return table rate array or false by rate request
     *
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return array|bool
     */
    public function getRate(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from($this->getMainTable());

        /** @var RateQuery $rateQuery */
        $rateQuery = $this->rateQueryFactory->create(['request' => $request]);

        $rateQuery->prepareSelect($select);
        $bindings = $rateQuery->getBindings();

        $result = $connection->fetchRow($select, $bindings);
        // Normalize destination zip code
        if ($result && $result['dest_zip'] == '*') {
            $result['dest_zip'] = '';
        }

        return $result;
    }

    /**
     * @param array $condition
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function deleteByCondition(array $condition)
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();
        $connection->delete($this->getMainTable(), $condition);
        $connection->commit();
        return $this;
    }

    /**
     * @param array $fields
     * @param array $values
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    private function importData(array $fields, array $values)
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            if (count($fields) && count($values)) {
                $this->getConnection()->insertArray($this->getMainTable(), $fields, $values);
                $this->_importedRows += count($values);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $connection->rollback();
            throw new \Magento\Framework\Exception\LocalizedException(__('Unable to import data'), $e);
        } catch (\Exception $e) {
            $connection->rollback();
            $this->logger->critical($e);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something went wrong while importing table rates.')
            );
        }
        $connection->commit();
    }

    /**
     * @param \Magento\Framework\DataObject $object
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate
     * @todo: this method should be refactored as soon as updated design will be provided
     * @see https://wiki.corp.x.com/display/MCOMS/Magento+Filesystem+Decisions
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function uploadAndImport(\Magento\Framework\DataObject $object)
    {
        foreach ($_FILES['groups']['tmp_name'] as $key => $value) {
            
            // Only process uploaded DPD files
            if (strpos($key, 'dpd') !== 0) {
                continue;
            }

            $csvFile = $value['fields']['import']['value'];

            if ($csvFile == '') {
                continue;
            }

            $website = $this->storeManager->getWebsite($object->getScopeId());

            $this->importWebsiteId = (int)$website->getId();
            $this->importUniqueHash = [];
            $this->importErrors = [];
            $this->importedRows = 0;

            $tmpDirectory = ini_get('upload_tmp_dir') ? $this->readFactory->create(ini_get('upload_tmp_dir'))
                : $this->filesystem->getDirectoryRead(DirectoryList::SYS_TMP);
            $path = $tmpDirectory->getRelativePath($csvFile);
            $stream = $tmpDirectory->openFile($path);

            // check and skip headers
            $headers = $stream->readCsv();
            if ($headers === false || count($headers) < 5) {
                $stream->close();
                throw new \Magento\Framework\Exception\LocalizedException(__('Please correct Table Rates File Format.'));
            }

            $this->shippingMethod = $key;
            $this->importConditionName = $this->getConditionName($object, $object->getGroupId());
            $adapter = $this->getConnection();
            $adapter->beginTransaction();
            try {
                $rowNumber = 1;
                $importData = [];
                $this->_loadDirectoryCountries();
                $this->_loadDirectoryRegions();

                // delete old data by website and condition name
                $condition = [
                    'website_id = ?' => $this->importWebsiteId,
                    'condition_name = ?' => $this->importConditionName,
                    'shipping_method = ?' => $this->shippingMethod
                ];
                $adapter->delete($this->getMainTable(), $condition);
                while (false !== ($csvLine = $stream->readCsv())) {
                    $rowNumber++;
                    if (empty($csvLine)) {
                        continue;
                    }
                    $row = $this->_getImportRow($csvLine, $rowNumber);
                    if ($row !== false) {
                        $importData[] = $row;
                    }
                    if (count($importData) == 5000) {
                        $this->_saveImportData($importData);
                        $importData = [];
                    }
                }
                $this->_saveImportData($importData);
                $stream->close();
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $adapter->rollback();
                $stream->close();
                throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
            } catch (\Exception $e) {
                $adapter->rollback();
                $stream->close();
                $this->logger->critical($e);
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Something went wrong while importing table rates.')
                );
            }

            $adapter->commit();
            if ($this->importErrors) {
                $error = __(
                    'We couldn\'t import this file because of these errors: %1',
                    implode(" \n", $this->importErrors)
                );
                throw new \Magento\Framework\Exception\LocalizedException($error);
            }
        }

        return $this;
    }

    /**
     * Validate row for import and return table rate array or false
     * Error will be add to importErrors array
     *
     * @param array $row
     * @param int $rowNumber
     * @return array|false
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getImportRow($row, $rowNumber = 0)
    {
        // validate row
        if (count($row) < 4) {
            $this->importErrors[] =
                __('Please correct Matrix Rates format in Row #%1. Invalid Number of Rows', $rowNumber);
            return false;
        }

        // strip whitespace from the beginning and end of each row
        foreach ($row as $k => $v) {
            $row[$k] = trim($v);
        }

        // validate country
        if (isset($this->importIso2Countries[$row[0]])) {
            $countryId = $this->importIso2Countries[$row[0]];
        } elseif (isset($this->importIso3Countries[$row[0]])) {
            $countryId = $this->importIso3Countries[$row[0]];
        } elseif ($row[0] == '*' || $row[0] == '') {
            $countryId = '0';
        } else {
            $this->importErrors[] = __('Please correct Country "%1" in Row #%2.', $row[0], $rowNumber);
            return false;
        }
        // validate region
        if ($countryId != '0' && isset($this->importRegions[$countryId][$row[1]])) {
            $regionId = $this->importRegions[$countryId][$row[1]];
        } elseif ($row[1] == '*' || $row[1] == '') {
            $regionId = 0;
        } else {
            $this->importErrors[] = __('Please correct Region/State "%1" in Row #%2.', $row[1], $rowNumber);
            return false;
        }

        // detect zip code
        if ($row[2] == '*' || $row[2] == '') {
            $zipCode = '*';
        } else {
            $zipCode = $row[2];
        }

        $value = $row[3];
        $price = $this->_parseDecimalValue($row[4]);
        $cost = 0;

        // protect from duplicate
        $hash = sprintf(
            "%s-%s-%s-%s-%F-%F-%s",
            $countryId,
            $regionId,
            $zipCode,
            $value,
            $price,
            $cost,
            $this->shippingMethod
        );

        if (isset($this->importUniqueHash[$hash])) {
            $this->importErrors[] = __(
                'Duplicate Row #%1 (Country "%2", Region/State "%3", Zip from "%4", Value "%5", and Shipping Method "%8")',
                $rowNumber,
                $countryId,
                $regionId,
                $zipCode,
                $value,
                $price,
                $cost,
                $this->shippingMethod
            );
            return false;
        }
        $this->importUniqueHash[$hash] = true;
        return [
            $this->importWebsiteId,      // website_id
            $countryId,                  // dest_country_id
            $regionId,                   // dest_region_id,
            $zipCode,                    // dest_zip
            $this->importConditionName,  // condition_name,
            $value,                      // condition_value From
            $price,                      // price
            $cost,                       // cost
            $this->shippingMethod              // shipping method
        ];
    }

    /**
     * Load directory countries
     *
     * @return \WebShopApps\MatrixRate\Model\ResourceModel\Carrier\Matrixrate
     */
    protected function _loadDirectoryCountries()
    {
        if ($this->importIso2Countries !== null && $this->importIso3Countries !== null) {
            return $this;
        }
        $this->importIso2Countries = [];
        $this->importIso3Countries = [];
        /** @var $collection \Magento\Directory\Model\ResourceModel\Country\Collection */
        $collection = $this->countryCollectionFactory->create();
        foreach ($collection->getData() as $row) {
            $this->importIso2Countries[$row['iso2_code']] = $row['country_id'];
            $this->importIso3Countries[$row['iso3_code']] = $row['country_id'];
        }
        return $this;
    }
    /**
     * Load directory regions
     *
     * @return \WebShopApps\MatrixRate\Model\ResourceModel\Carrier\Matrixrate
     */
    protected function _loadDirectoryRegions()
    {
        if ($this->importRegions !== null) {
            return $this;
        }
        $this->importRegions = [];
        /** @var $collection \Magento\Directory\Model\ResourceModel\Region\Collection */
        $collection = $this->regionCollectionFactory->create();
        foreach ($collection->getData() as $row) {
            $this->importRegions[$row['country_id']][$row['code']] = (int)$row['region_id'];
        }
        return $this;
    }

    /**
     * @param \Magento\Framework\DataObject $object
     * @return mixed|string
     * @since 100.1.0
     */
    public function getConditionName(\Magento\Framework\DataObject $object, $shippingMethod)
    {
        if ($object->getData('groups/'.$shippingMethod.'/fields/condition_name/inherit') == '1') {
            $conditionName = (string)$this->coreConfig->getValue('carriers/'.$shippingMethod.'/condition_name', 'default');
        } else {
            $conditionName = $object->getData('groups/'.$shippingMethod.'/fields/condition_name/value');
        }
        return $conditionName;
    }

    /**
     * @param string $filePath
     * @return \Magento\Framework\Filesystem\File\ReadInterface
     */
    private function getCsvFile($filePath)
    {
        $tmpDirectory = $this->filesystem->getDirectoryRead(DirectoryList::SYS_TMP);
        $path = $tmpDirectory->getRelativePath($filePath);
        return $tmpDirectory->openFile($path);
    }

    /**
     * Return import condition full name by condition name code
     *
     * @param string $conditionName
     * @return string
     */
    protected function _getConditionFullName($conditionName)
    {
        if (!isset($this->_conditionFullNames[$conditionName])) {
            $name = $this->carrierTablerate->getCode('condition_name_short', $conditionName);
            $this->_conditionFullNames[$conditionName] = $name;
        }

        return $this->_conditionFullNames[$conditionName];
    }

    /**
     * Parse and validate positive decimal value
     * Return false if value is not decimal or is not positive
     *
     * @param string $value
     * @return bool|float
     */
    protected function _parseDecimalValue($value)
    {
        if (!is_numeric($value)) {
            return false;
        }
        $value = (double)sprintf('%.4F', $value);
        if ($value < 0.0000) {
            return false;
        }
        return $value;
    }

    /**
     * Save import data batch
     *
     * @param array $data
     * @return \Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate
     */
    protected function _saveImportData(array $data)
    {
        if (!empty($data)) {
            $columns = [
                'website_id',
                'dest_country_id',
                'dest_region_id',
                'dest_zip',
                'condition_name',
                'condition_value',
                'price',
                'cost',
                'shipping_method',
            ];

            $this->getConnection()->insertArray($this->getMainTable(), $columns, $data);
            $this->_importedRows += count($data);
        }

        return $this;
    }
}
