<?php

namespace Kodbruket\VsfKco\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    const LOG_TABLE_NAME = 'vsf_kco_events';

    public function upgrade( SchemaSetupInterface $setup, ModuleContextInterface $context ) {
        $installer = $setup;
        $connection = $installer->getConnection();

        $installer->startSetup();

        if(version_compare($context->getVersion(), '1.0.4', '<')) {
            if (!$installer->tableExists(self::LOG_TABLE_NAME)) {
                $table = $connection->newTable(
                    $installer->getTable(self::LOG_TABLE_NAME)
                )
                    ->addColumn(
                        'event_id',
                        Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'nullable' => false,
                            'primary' => true,
                            'unsigned' => true,
                        ],
                        'Event ID'
                    )
                    ->addColumn(
                        'event_name',
                        Table::TYPE_TEXT,
                        255,
                        ['nullable => false'],
                        'Event Name'
                    )
                    ->addColumn(
                        'klarna_order_id',
                        Table::TYPE_TEXT,
                        255,
                        ['nullable' => true],
                        'Klarna Order ID'
                    )
                    ->addColumn(
                        'order_id',
                        Table::TYPE_INTEGER,
                        10,
                        ['unsigned' => true, 'nullable' => true],
                        'Magento Order ID'
                    )
                    ->addColumn(
                        'message',
                        Table::TYPE_TEXT,
                        null,
                        [],
                        'Event Message'
                    )
                    ->addColumn(
                        'raw_data',
                        Table::TYPE_TEXT,
                        null,
                        [],
                        'Raw Data'
                    )
                    ->addColumn(
                        'created_at',
                        Table::TYPE_TIMESTAMP,
                        null,
                        ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                        'Created At'
                    )
                    ->addIndex(
                        $installer->getIdxName(
                            $installer->getTable(self::LOG_TABLE_NAME),
                            ['klarna_order_id'],
                            AdapterInterface::INDEX_TYPE_FULLTEXT
                        ),
                        ['klarna_order_id'],
                        ['type' => AdapterInterface::INDEX_TYPE_FULLTEXT]
                    )
                    ->addIndex(
                        $installer->getIdxName(
                            $installer->getTable(self::LOG_TABLE_NAME),
                            ['order_id'],
                            AdapterInterface::INDEX_TYPE_INDEX
                        ),
                        ['order_id'],
                        ['type' => AdapterInterface::INDEX_TYPE_INDEX]
                    )
                    ->addIndex(
                        $installer->getIdxName(
                            $installer->getTable(self::LOG_TABLE_NAME),
                            ['event_name', 'klarna_order_id'],
                            AdapterInterface::INDEX_TYPE_INDEX
                        ),
                        ['event_name', 'klarna_order_id'],
                        ['type' => AdapterInterface::INDEX_TYPE_INDEX]
                    )
                    ->setComment('VSF KCO Events Table');

                $connection->createTable($table);
            }
        }

        $installer->endSetup();
    }
}
