<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReplyRepository")
 * @ORM\Table(
 *      name="reply",
 *      indexes={
 *      },
 *      uniqueConstraints={
 *        @ORM\UniqueConstraint(name="postid_replyno_unique", 
 *            columns={"post_id", "reply_no"})
 *      }
 * )
 */
class Reply
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $raw_content;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $content;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $raw_authorname;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Post", inversedBy="reply_entities")
     * @ORM\JoinColumn(nullable=false)
     */
    private $post;

    /**
     * @ORM\Column(type="integer")
     */
    private $reply_no;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $author;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $author_code;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $reply_time;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $parent_id;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRawContent(): ?string
    {
        return $this->raw_content;
    }

    public function setRawContent(?string $raw_content): self
    {
        $this->raw_content = $raw_content;

        return $this;
    }

    public function getRawAuthorname(): ?string
    {
        return $this->raw_authorname;
    }

    public function setRawAuthorname(?string $raw_authorname): self
    {
        $this->raw_authorname = $raw_authorname;

        return $this;
    }

    public function getReplyNo(): ?int
    {
        return $this->reply_no;
    }

    public function setReplyNo(int $reply_no): self
    {
        $this->reply_no = $reply_no;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthorCode(): ?string
    {
        return $this->author_code;
    }

    public function setAuthorCode(?string $author_code): self
    {
        $this->author_code = $author_code;

        return $this;
    }

    public function getReplyTime(): ?\DateTimeInterface
    {
        return $this->reply_time;
    }

    public function setReplyTime(?\DateTimeInterface $reply_time): self
    {
        $this->reply_time = $reply_time;

        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parent_id;
    }

    public function setParentId(?int $parent_id): self
    {
        $this->parent_id = $parent_id;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->post = $post;

        return $this;
    }
}
