<?php

namespace App\DTO;

class MapDTO
{
    private int $page;
    private int $limit;
    private string $type;

    public function __construct(string $type, int $page, int $limit)
    {
        $this->page = $page;
        $this->limit = $limit;
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
        return $this->limit;
    }

    public function setPerPage(int $limit): void
    {
        $this->limit = $limit;
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