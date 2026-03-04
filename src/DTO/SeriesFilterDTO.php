<?php

namespace App\DTO;

class SeriesFilterDTO
{
    private string $sort;
    private string $order;
    private int $limit;
    private int $page;
    private int $includeStartedSeries;
    private int $includeEndedSeries;

    public function __construct()
    {
        $this->sort = 'firstAirDate';
        $this->order = 'DESC';
        $this->limit = 20;
        $this->page = 1;
        $this->includeStartedSeries = 1; // 0: all series, 1: only started series 2: only not started series
        $this->includeEndedSeries = 1; // 0: all series, 1: only ongoing series 2: only ended series
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort;
    }

    public function getOrder(): string
    {
        return $this->order;
    }

    public function setOrder(string $order): void
    {
        $this->order = $order;
    }

    public function getPerPage(): int
    {
        return $this->limit;
    }

    public function setPerPage(int $limit): void
    {
        $this->limit = $limit;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getIncludeStartedSeries(): int
    {
        return $this->includeStartedSeries;
    }

    public function setIncludeStartedSeries(int $includeStartedSeries): void
    {
        $this->includeStartedSeries = $includeStartedSeries;
    }

    public function getIncludeEndedSeries(): int
    {
        return $this->includeEndedSeries;
    }

    public function setIncludeEndedSeries(int $includeEndedSeries): void
    {
        $this->includeEndedSeries = $includeEndedSeries;
    }
}