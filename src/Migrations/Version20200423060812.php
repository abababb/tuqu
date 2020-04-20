<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200423060812 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE reply DROP FOREIGN KEY FK_FDA8C6E0E85F12B8');
        $this->addSql('DROP INDEX IDX_FDA8C6E0E85F12B8 ON reply');
        $this->addSql('ALTER TABLE reply CHANGE post_id_id post_id INT NOT NULL');
        $this->addSql('ALTER TABLE reply ADD CONSTRAINT FK_FDA8C6E04B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('CREATE INDEX IDX_FDA8C6E04B89032C ON reply (post_id)');
        $this->addSql('CREATE UNIQUE INDEX postid_replyno_unique ON reply (post_id, reply_no)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE reply DROP FOREIGN KEY FK_FDA8C6E04B89032C');
        $this->addSql('DROP INDEX IDX_FDA8C6E04B89032C ON reply');
        $this->addSql('DROP INDEX postid_replyno_unique ON reply');
        $this->addSql('ALTER TABLE reply CHANGE post_id post_id_id INT NOT NULL');
        $this->addSql('ALTER TABLE reply ADD CONSTRAINT FK_FDA8C6E0E85F12B8 FOREIGN KEY (post_id_id) REFERENCES post (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_FDA8C6E0E85F12B8 ON reply (post_id_id)');
    }
}
