<?php

namespace App\Entity;

interface ReferenceEntityInterface
{
    public function getId(): ?int;

    public function getName(): string;

    public function setName(string $name): self;

    public function getSlug(): string;

    public function setSlug(string $slug): self;

    public function getDescription(): ?string;

    public function setDescription(?string $description): self;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): self;
}
