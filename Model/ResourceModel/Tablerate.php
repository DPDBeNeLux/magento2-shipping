<?php
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
namespace DPDBenelux\Shipping\Model\ResourceModel;


use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\RateQuery;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\RateQueryFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\Import;

class Tablerate  extends \Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate/*\Magento\Framework\Model\ResourceModel\Db\AbstractDb*/
{
    /**
     * @var Import
     */
    protected $importProcessor;
	
    /**
     * @var RateQueryFactory
     *
    private $rateQueryFactory;
	*/
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $coreConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\OfflineShipping\Model\Carrier\Tablerate $carrierTablerate,
        \Magento\Framework\Filesystem $filesystem,
        Import $import,
        RateQueryFactory $rateQueryFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $logger, $coreConfig, $storeManager, $carrierTablerate, $filesystem, $import, $rateQueryFactory, $connectionName);
        $this->coreConfig = $coreConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->carrierTablerate = $carrierTablerate;
        $this->filesystem = $filesystem;
        $this->importProcessor = $import;
        $this->rateQueryFactory = $rateQueryFactory;
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
     * Define main table and id field name
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('dpd_shipping_tablerate', 'pk');
    }

	    /**
     * Upload table rate file and import data from it
     *
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
        /**
         * @var \Magento\Framework\App\Config\Value $object
         */
        if (empty($_FILES['groups']['tmp_name']['tablerate']['fields']['import']['value'])) {
            return $this;
        }
        $filePath = $_FILES['groups']['tmp_name']['tablerate']['fields']['import']['value'];

        $websiteId = $this->storeManager->getWebsite($object->getScopeId())->getId();
        $conditionName = $this->getConditionName($object);

        $file = $this->getCsvFile($filePath);
        try {
            // delete old data by website and condition name
            $condition = [
                'website_id = ?' => $websiteId,
                'condition_name = ?' => $conditionName,
            ];
            $this->deleteByCondition($condition);

            $columns = $this->importProcessor->getColumns();
            $conditionFullName = $this->_getConditionFullName($conditionName);
            foreach ($this->importProcessor->getData($file, $websiteId, $conditionName, $conditionFullName) as $bunch) {
                $this->importData($columns, $bunch);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something went wrong while importing table rates.')
            );
        } finally {
            $file->close();
        }

        if ($this->importProcessor->hasErrors()) {
            $error = __(
                'We couldn\'t import this file because of these errors: %1',
                implode(" \n", $this->importProcessor->getErrors())
            );
            throw new \Magento\Framework\Exception\LocalizedException($error);
        }
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
     * Return table rate array or false by rate request
     *
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return array|bool
     *
    public function getRate(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from($this->getMainTable());
        /** @var RateQuery $rateQuery *//*
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
	*/
}