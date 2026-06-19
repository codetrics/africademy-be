<?php

declare(strict_types=1);

namespace App\Service\ReturnType;

use Knp\Component\Pager\Pagination\PaginationInterface;

class PaginationReturnType
{
    private int $currentPage;

    private int $itemsPerPage;

    private int $totalItems;

    private int $totalPages;

    public function __construct(PaginationInterface $pagination)
    {
        $this->currentPage = $pagination->getCurrentPageNumber();
        $this->itemsPerPage = $pagination->getItemNumberPerPage();
        $this->totalItems = $pagination->getTotalItemCount();
        $this->totalPages = $this->itemsPerPage > 0
            ? (int) ceil($this->totalItems / $this->itemsPerPage)
            : 0;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }
}
