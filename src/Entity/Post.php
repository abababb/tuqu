<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PostRepository")
 * @ORM\Table(
 *      name="post",
 *      indexes={
 *          @ORM\Index(name="idx_idate_subject", columns={"idate", "subject"})
 *      },
 *      uniqueConstraints={
 *        @ORM\UniqueConstraint(name="postid_unique", 
 *            columns={"postid", "board"})
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
     * @ORM\Column(type="integer")
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
     * @ORM\Column(type="smallint")
     */
    private $examine_status;

    /**
     * @ORM\Column(type="smallint", options={"default"=2})
     */
    private $board = 2;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reply", mappedBy="post_id", orphanRemoval=true)
     */
    private $replies;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
    }

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

    public function getBoard(): ?int
    {
        return $this->board;
    }

    public function setBoard(int $board): self
    {
        $this->board = $board;

        return $this;
    }

    public function addReply(Reply $reply): self
    {
        if (!$this->replies->contains($reply)) {
            $this->replies[] = $reply;
            $reply->setPostId($this);
        }

        return $this;
    }

    public function removeReply(Reply $reply): self
    {
        if ($this->replies->contains($reply)) {
            $this->replies->removeElement($reply);
            // set the owning side to null (unless already changed)
            if ($reply->getPostId() === $this) {
                $reply->setPostId(null);
            }
        }

        return $this;
    }
}
