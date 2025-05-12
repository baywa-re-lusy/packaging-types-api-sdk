<?php

namespace BayWaReLusy\PackagingTypesAPI\SDK;

use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypeEntity\Category;
use Ramsey\Uuid\Uuid;

class PackagingTypeEntity
{
    protected Uuid $id;
    protected string $name;
    protected ?string $shortName = null;
    protected string $transporeonId;
    protected string $category;
    protected bool $active;
    protected ?int $length;
    protected ?int $width;
    protected ?int $height;
    protected ?float $weight;
    protected ?int $maxNbStackable;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): PackagingTypeEntity
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): PackagingTypeEntity
    {
        $this->name = $name;
        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): PackagingTypeEntity
    {
        $this->shortName = $shortName;
        return $this;
    }

    public function getTransporeonId(): string
    {
        return $this->transporeonId;
    }

    public function setTransporeonId(string $transporeonId): PackagingTypeEntity
    {
        $this->transporeonId = $transporeonId;
        return $this;
    }

    public function getCategory(): Category
    {
        return Category::from($this->category);
    }

    public function setCategory(Category $category): PackagingTypeEntity
    {
        $this->category = $category->value;
        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): PackagingTypeEntity
    {
        $this->active = $active;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * @param int|null $length
     * @return PackagingTypeEntity
     */
    public function setLength(?int $length): PackagingTypeEntity
    {
        $this->length = $length;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getWidth(): ?int
    {
        return $this->width;
    }

    /**
     * @param int|null $width
     * @return PackagingTypeEntity
     */
    public function setWidth(?int $width): PackagingTypeEntity
    {
        $this->width = $width;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getHeight(): ?int
    {
        return $this->height;
    }

    /**
     * @param int|null $height
     * @return PackagingTypeEntity
     */
    public function setHeight(?int $height): PackagingTypeEntity
    {
        $this->height = $height;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getWeight(): ?float
    {
        return $this->weight;
    }

    /**
     * @param float|null $weight
     * @return PackagingTypeEntity
     */
    public function setWeight(?float $weight): PackagingTypeEntity
    {
        $this->weight = $weight;
        return $this;
    }

    public function getMaxNbStackable(): ?int
    {
        return $this->maxNbStackable;
    }

    public function setMaxNbStackable(?int $maxNbStackable): PackagingTypeEntity
    {
        $this->maxNbStackable = $maxNbStackable;
        return $this;
    }
}
