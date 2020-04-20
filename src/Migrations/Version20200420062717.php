<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200420062717 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE reply (id INT AUTO_INCREMENT NOT NULL, post_id_id INT NOT NULL, raw_content LONGTEXT DEFAULT NULL, content LONGTEXT DEFAULT NULL, raw_authorname VARCHAR(255) DEFAULT NULL, reply_no INT NOT NULL, author VARCHAR(255) DEFAULT NULL, author_code VARCHAR(255) DEFAULT NULL, reply_time DATETIME DEFAULT NULL, parent_id INT DEFAULT NULL, INDEX IDX_FDA8C6E0E85F12B8 (post_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reply ADD CONSTRAINT FK_FDA8C6E0E85F12B8 FOREIGN KEY (post_id_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE post DROP replies');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE reply');
        $this->addSql('ALTER TABLE post ADD replies INT NOT NULL');
    }
}
