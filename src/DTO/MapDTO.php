<?php

namespace App\DTO;

class MapDTO
{
    private int $page;
    private int $perPage;
    private string $type;

    public function __construct(string $type, int $page, int $perPage)
    {
        $this->page = $page;
        $this->perPage = $perPage;
        $this->type = $type;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function setPerPage(int $perPage): void
    {
        $this->perPage = $perPage;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }
}