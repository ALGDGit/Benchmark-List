<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Kept for databases that ran this version before list_position was merged into Version20260531000000.
 */
final class Version20260604120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add item.list_position when upgrading from an older schema (Doctrine Schema API)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            'Migration is only supported on MySQL.'
        );

        if (!$schema->hasTable('item')) {
            return;
        }

        $item = $schema->getTable('item');
        if ($item->hasColumn('list_position')) {
            return;
        }

        $item->addColumn('list_position', Types::STRING, [
            'length' => 10,
            'notnull' => true,
            'default' => 'first',
        ]);
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('item')) {
            return;
        }

        $item = $schema->getTable('item');
        if ($item->hasColumn('list_position')) {
            $item->dropColumn('list_position');
        }
    }
}
