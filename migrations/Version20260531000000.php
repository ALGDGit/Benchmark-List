<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create category, item, item_category and homepage_slot tables (Doctrine Schema API)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            'Migration is only supported on MySQL.'
        );

        $this->createCategoryTable($schema);
        $this->createItemTable($schema);
        $this->createItemCategoryTable($schema);
        $this->createHomepageSlotTable($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('homepage_slot')) {
            $schema->dropTable('homepage_slot');
        }
        if ($schema->hasTable('item_category')) {
            $schema->dropTable('item_category');
        }
        if ($schema->hasTable('item')) {
            $schema->dropTable('item');
        }
        if ($schema->hasTable('category')) {
            $schema->dropTable('category');
        }
    }

    private function createCategoryTable(Schema $schema): void
    {
        if ($schema->hasTable('category')) {
            return;
        }

        $table = $schema->createTable('category');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 120, 'notnull' => true]);
        $table->addColumn('slug', Types::STRING, ['length' => 140, 'notnull' => true]);
        $table->addUniqueIndex(['slug'], 'uniq_category_slug');
        $table->setPrimaryKey(['id']);
        $this->applyMysqlTableOptions($table);
    }

    private function createItemTable(Schema $schema): void
    {
        if ($schema->hasTable('item')) {
            return;
        }

        $table = $schema->createTable('item');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 180, 'notnull' => true]);
        $table->addColumn('list_position', Types::STRING, [
            'length' => 10,
            'notnull' => true,
            'default' => 'first',
        ]);
        $table->addIndex(['name'], 'idx_item_name');
        $table->setPrimaryKey(['id']);
        $this->applyMysqlTableOptions($table);
    }

    private function createItemCategoryTable(Schema $schema): void
    {
        if ($schema->hasTable('item_category')) {
            return;
        }

        $table = $schema->createTable('item_category');
        $table->addColumn('item_id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('category_id', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['item_id', 'category_id']);
        $table->addIndex(['category_id'], 'IDX_ITEM_CATEGORY_CATEGORY');
        $table->addForeignKeyConstraint(
            'item',
            ['item_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'FK_ITEM_CATEGORY_ITEM'
        );
        $table->addForeignKeyConstraint(
            'category',
            ['category_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'FK_ITEM_CATEGORY_CATEGORY'
        );
        $this->applyMysqlTableOptions($table);
    }

    private function createHomepageSlotTable(Schema $schema): void
    {
        if ($schema->hasTable('homepage_slot')) {
            return;
        }

        $table = $schema->createTable('homepage_slot');
        $table->addColumn('slot_number', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('category_id', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['slot_number']);
        $table->addForeignKeyConstraint(
            'category',
            ['category_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'FK_HOMEPAGE_SLOT_CATEGORY'
        );
        $this->applyMysqlTableOptions($table);
    }

    private function applyMysqlTableOptions(Table $table): void
    {
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collate', 'utf8mb4_unicode_ci');
        $table->addOption('engine', 'InnoDB');
    }
}
