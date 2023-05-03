<?php

namespace App\Entity;

use App\Repository\MeldungRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MeldungRepository::class)]
class Meldung
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Rechnungsnummer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $StuckArtikelnummer = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $Datum = null;

    #[ORM\Column]
    private ?bool $Gutschrift = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Kulanzerstattung = null;

    #[ORM\Column(nullable: true)]
    private ?bool $NeueSendung = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $KulanzerstattungEuro = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRechnungsnummer(): ?string
    {
        return $this->Rechnungsnummer;
    }

    public function setRechnungsnummer(?string $Rechnungsnummer): self
    {
        $this->Rechnungsnummer = $Rechnungsnummer;

        return $this;
    }

    public function getStuckArtikelnummer(): ?string
    {
        return $this->StuckArtikelnummer;
    }

    public function setStuckArtikelnummer(?string $StuckArtikelnummer): self
    {
        $this->StuckArtikelnummer = $StuckArtikelnummer;

        return $this;
    }

    public function getDatum(): ?\DateTimeInterface
    {
        return $this->Datum;
    }

    public function setDatum(\DateTimeInterface $Datum): self
    {
        $this->Datum = $Datum;

        return $this;
    }

    public function isGutschrift(): ?bool
    {
        return $this->Gutschrift;
    }

    public function setGutschrift(bool $Gutschrift): self
    {
        $this->Gutschrift = $Gutschrift;

        return $this;
    }

    public function getKulanzerstattung(): ?string
    {
        return $this->Kulanzerstattung;
    }

    public function setKulanzerstattung(?string $Kulanzerstattung): self
    {
        $this->Kulanzerstattung = $Kulanzerstattung;

        return $this;
    }

    public function isNeueSendung(): ?bool
    {
        return $this->NeueSendung;
    }

    public function setNeueSendung(?bool $NeueSendung): self
    {
        $this->NeueSendung = $NeueSendung;

        return $this;
    }

    public function getKulanzerstattungEuro(): ?string
    {
        return $this->KulanzerstattungEuro;
    }

    public function setKulanzerstattungEuro(?string $KulanzerstattungEuro): self
    {
        $this->KulanzerstattungEuro = $KulanzerstattungEuro;

        return $this;
    }
}
