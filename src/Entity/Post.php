<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PostRepository")
 * @ORM\Table(
 *      name="post",
 *      indexes={
 *          @ORM\Index(name="idx_idate_subject", columns={"idate", "subject"})
 *      }
 * )
 */
class Post
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", unique=true)
     */
    private $postid;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $subject;

    /**
     * @ORM\Column(type="datetime")
     */
    private $idate;

    /**
     * @ORM\Column(type="datetime")
     */
    private $ndate;

    /**
     * @ORM\Column(type="string", length=40)
     */
    private $author;

    /**
     * @ORM\Column(type="integer")
     */
    private $replies;

    /**
     * @ORM\Column(type="smallint")
     */
    private $examine_status;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getIdate(): ?\DateTimeInterface
    {
        return $this->idate;
    }

    public function setIdate(\DateTimeInterface $idate): self
    {
        $this->idate = $idate;

        return $this;
    }

    public function getNdate(): ?\DateTimeInterface
    {
        return $this->ndate;
    }

    public function setNdate(\DateTimeInterface $ndate): self
    {
        $this->ndate = $ndate;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getReplies(): ?int
    {
        return $this->replies;
    }

    public function setReplies(int $replies): self
    {
        $this->replies = $replies;

        return $this;
    }

    public function getExamineStatus(): ?int
    {
        return $this->examine_status;
    }

    public function setExamineStatus(int $examine_status): self
    {
        $this->examine_status = $examine_status;

        return $this;
    }

    public function getPostid(): ?int
    {
        return $this->postid;
    }

    public function setPostid(int $postid): self
    {
        $this->postid = $postid;

        return $this;
    }
}
