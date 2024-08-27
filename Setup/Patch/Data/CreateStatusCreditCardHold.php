<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Setup\Patch\Data;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Adyen\Payment\Model\OrderStatusConstants;

class CreateStatusCreditCardHold implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private WriterInterface $configWriter;
    private ReinitableConfigInterface $reinitableConfig;
    private StatusFactory $statusFactory;
    private StatusResourceFactory $statusResourceFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig,
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    public function apply()
    {
        /** @var StatusResource $statusResource */
        $statusResource = $this->statusResourceFactory->create();

        // Create the order status
        $status = $this->statusFactory->create();
        $status->setData([
            'status' => OrderStatusConstants::CREDIT_CARD_HOLD_STATUS,
            'label' => OrderStatusConstants::CREDIT_CARD_HOLD_STATUS_LABEL,
        ]);

        try {
            $statusResource->save($status);
        } catch (AlreadyExistsException $exception) {
            return;
        }

        // NOTE: if we don't need a new state we need to assign this order status to an exsisting state like payment review
        $status->assignState(OrderStatusConstants::CREDIT_CARD_HOLD_STATE, false, true);

        // Add custom order state
        $this->configWriter->save(
            'sales/order/state/' . OrderStatusConstants::CREDIT_CARD_HOLD_STATE . '/label',
            OrderStatusConstants::CREDIT_CARD_HOLD_STATE_LABEL
        );

        // Reinitialize the configuration to ensure changes take effect
        $this->reinitableConfig->reinit();
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }
}