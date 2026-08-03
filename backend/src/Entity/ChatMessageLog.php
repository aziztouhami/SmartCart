<?php

namespace App\Entity;

use App\Repository\ChatMessageLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One turn (user message or assistant reply) in a chatbot conversation,
 * keyed by the same client-generated session id used for guest events. Also
 * doubles as the rate-limit log: counting recent rows per session is how the
 * controller decides whether to allow another message.
 */
#[ORM\Entity(repositoryClass: ChatMessageLogRepository::class)]
#[ORM\Index(columns: ['session_id'])]
#[ORM\Index(columns: ['created_at'])]
class ChatMessageLog
{
    public const ROLES = ['user', 'assistant'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $sessionId = null;

    #[ORM\Column(length: 20)]
    private ?string $role = null;

    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid chat message role "%s". Valid roles are: %s', $role, implode(', ', self::ROLES)));
        }
        $this->role = $role;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
