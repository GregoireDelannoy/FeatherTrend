<?php

namespace App\Entity;

use App\Repository\PicturesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PicturesRepository::class)]
class Pictures
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $datetime = null;

    #[ORM\Column(length: 8191)]
    private ?string $path = null;

    #[ORM\ManyToOne(inversedBy: 'pictures')]
    private ?Species $specie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getDatetime(): ?\DateTime
    {
        return $this->datetime;
    }

    public function setDatetime(?\DateTime $datetime): static
    {
        $this->datetime = $datetime;

        return $this;
    }

    public function getPath(): ?string
    {
        return dirname(__DIR__).'/../assets/pictures/'.$this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getSpecie(): ?Species
    {
        return $this->specie;
    }

    public function setSpecie(?Species $specie): static
    {
        $this->specie = $specie;

        return $this;
    }
}
