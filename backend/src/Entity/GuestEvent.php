<?php

namespace App\Entity;

use App\Repository\GuestEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A view/cart event from a visitor browsing without an account, keyed by a
 * client-generated session id instead of a User. This is the raw signal the
 * recommendation batch job mines for "viewed/added together" co-occurrence.
 */
#[ORM\Entity(repositoryClass: GuestEventRepository::class)]
#[ORM\Index(columns: ['session_id'])]
#[ORM\Index(columns: ['created_at'])]
class GuestEvent
{
    public const TYPES = ['view', 'cart'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $sessionId = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid guest event type "%s". Valid types are: %s',
                $type,
                implode(', ', self::TYPES)
            ));
        }
        $this->type = $type;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }
}
