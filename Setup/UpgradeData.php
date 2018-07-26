<?php

namespace DPDBenelux\Shipping\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Quote\Setup\QuoteSetupFactory;
use Magento\Sales\Setup\SalesSetupFactory;

/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var SalesSetupFactory
     */
    protected $salesSetupFactory;

    /**
     * @var QuoteSetupFactory
     */
    protected $quoteSetupFactory;

    /**
     * @param SalesSetupFactory $salesSetupFactory
     * @param QuoteSetupFactory $quoteSetupFactory
     */
    public function __construct(SalesSetupFactory $salesSetupFactory, QuoteSetupFactory $quoteSetupFactory)
    {
        $this->salesSetupFactory = $salesSetupFactory;
        $this->quoteSetupFactory = $quoteSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /**
         * Prepare database for install
         */
        $setup->startSetup();


        if (version_compare($context->getVersion(), '1.0.8') < 0) {
            $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);
            /**
             * Add dpd attributes to the:
             *  - sales/flat_order table
             */
            $salesInstaller->addAttribute(
                'order',
                'dpd_parcelshop_id',
                ['type' => 'varchar', 'visible' => false, 'default' => '']
            );
            $salesInstaller->addAttribute(
                'order',
                'dpd_company',
                ['type' => 'varchar', 'visible' => false, 'default' => '']
            );
            $salesInstaller->addAttribute(
                'order',
                'dpd_street',
                ['type' => 'varchar', 'visible' => false, 'default' => '']
            );
            $salesInstaller->addAttribute(
                'order',
                'dpd_zipcode',
                ['type' => 'varchar', 'visible' => false, 'default' => '']
            );
            $salesInstaller->addAttribute(
                'order',
                'dpd_city',
                ['type' => 'varchar', 'visible' => false, 'default' => '']
            );
            $salesInstaller->addAttribute(
                'order',
                'dpd_country',
                ['type' => 'varchar', 'visible' => false, 'default' => '']
            );
        }

        /**
         * Prepare database after install
         */
        $setup->endSetup();
    }
}
