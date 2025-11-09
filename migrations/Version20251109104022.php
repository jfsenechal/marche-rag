<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251109104022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on vector';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'CREATE INDEX ON sentence USING ivfflat (embeddings vector_cosine_ops)
WITH (lists = 100);'
        );
    }

    public function down(Schema $schema): void
    {

    }
}
