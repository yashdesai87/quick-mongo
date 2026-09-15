<?php

/**
 * Pagination helper class
 */
class Paginator
{
    private $totalItems;

    private $itemsPerPage;

    private $currentPage;

    private $totalPages;

    private $offset;

    /**
     * Constructor
     */
    public function __construct($totalItems, $itemsPerPage = 50, $currentPage = 1)
    {
        $this->totalItems = max(0, (int) $totalItems);
        $this->itemsPerPage = max(1, (int) $itemsPerPage);
        $this->currentPage = max(1, (int) $currentPage);

        // Calculate total pages
        $this->totalPages = (int) ceil($this->totalItems / $this->itemsPerPage);

        // Ensure current page is within bounds
        if ($this->currentPage > $this->totalPages && $this->totalPages > 0) {
            $this->currentPage = $this->totalPages;
        }

        // Calculate offset for database queries
        $this->offset = ($this->currentPage - 1) * $this->itemsPerPage;
    }

    /**
     * Get limit for database query
     */
    public function getLimit()
    {
        return $this->itemsPerPage;
    }

    /**
     * Get total pages
     */
    public function getTotalPages()
    {
        return $this->totalPages;
    }

    /**
     * Get current page
     */
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * Check if has previous page
     */
    public function hasPrevious()
    {
        return $this->currentPage > 1;
    }

    /**
     * Check if has next page
     */
    public function hasNext()
    {
        return $this->currentPage < $this->totalPages;
    }

    /**
     * Get previous page number
     */
    public function getPreviousPage()
    {
        return max(1, $this->currentPage - 1);
    }

    /**
     * Get next page number
     */
    public function getNextPage()
    {
        return min($this->totalPages, $this->currentPage + 1);
    }

    /**
     * Get range of pages to display
     */
    public function getPageRange($maxVisible = 7)
    {
        $pages = [];

        if ($this->totalPages <= $maxVisible) {
            // Show all pages if total is less than max visible
            for ($i = 1; $i <= $this->totalPages; $i++) {
                $pages[] = $i;
            }
        } else {
            // Calculate range around current page
            $halfVisible = floor($maxVisible / 2);
            $start = max(1, $this->currentPage - $halfVisible);
            $end = min($this->totalPages, $start + $maxVisible - 1);

            // Adjust start if we're near the end
            if ($end - $start < $maxVisible - 1) {
                $start = max(1, $end - $maxVisible + 1);
            }

            for ($i = $start; $i <= $end; $i++) {
                $pages[] = $i;
            }
        }

        return $pages;
    }

    /**
     * Get items range display text
     */
    public function getItemsRangeText()
    {
        if ($this->totalItems == 0) {
            return 'No items';
        }

        $start = $this->offset + 1;
        $end = min($this->offset + $this->itemsPerPage, $this->totalItems);

        return "Showing $start to $end of {$this->totalItems} items";
    }

    /**
     * Render pagination HTML
     */
    public function render($baseUrl, $queryParams = [])
    {
        if ($this->totalPages <= 1) {
            return ''; // No pagination needed
        }

        $html = '<nav class="pagination-wrapper">';
        $html .= '<ul class="pagination">';

        // Previous button
        if ($this->hasPrevious()) {
            $prevParams = array_merge($queryParams, ['page' => $this->getPreviousPage()]);
            $prevUrl = $baseUrl.'?'.http_build_query($prevParams);
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="'.htmlspecialchars($prevUrl).'">Previous</a>';
            $html .= '</li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link">Previous</span>';
            $html .= '</li>';
        }

        // Page numbers
        $pages = $this->getPageRange();

        // Add first page with ellipsis if needed
        if (! empty($pages) && $pages[0] > 1) {
            $firstParams = array_merge($queryParams, ['page' => 1]);
            $firstUrl = $baseUrl.'?'.http_build_query($firstParams);
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="'.htmlspecialchars($firstUrl).'">1</a>';
            $html .= '</li>';

            if ($pages[0] > 2) {
                $html .= '<li class="page-item disabled">';
                $html .= '<span class="page-link">...</span>';
                $html .= '</li>';
            }
        }

        // Render page numbers
        foreach ($pages as $page) {
            $pageParams = array_merge($queryParams, ['page' => $page]);
            $pageUrl = $baseUrl.'?'.http_build_query($pageParams);

            if ($page == $this->currentPage) {
                $html .= '<li class="page-item active">';
                $html .= '<span class="page-link">'.$page.'</span>';
                $html .= '</li>';
            } else {
                $html .= '<li class="page-item">';
                $html .= '<a class="page-link" href="'.htmlspecialchars($pageUrl).'">'.$page.'</a>';
                $html .= '</li>';
            }
        }

        // Add last page with ellipsis if needed
        if (! empty($pages) && end($pages) < $this->totalPages) {
            if (end($pages) < $this->totalPages - 1) {
                $html .= '<li class="page-item disabled">';
                $html .= '<span class="page-link">...</span>';
                $html .= '</li>';
            }

            $lastParams = array_merge($queryParams, ['page' => $this->totalPages]);
            $lastUrl = $baseUrl.'?'.http_build_query($lastParams);
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="'.htmlspecialchars($lastUrl).'">'.$this->totalPages.'</a>';
            $html .= '</li>';
        }

        // Next button
        if ($this->hasNext()) {
            $nextParams = array_merge($queryParams, ['page' => $this->getNextPage()]);
            $nextUrl = $baseUrl.'?'.http_build_query($nextParams);
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="'.htmlspecialchars($nextUrl).'">Next</a>';
            $html .= '</li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link">Next</span>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '<div class="pagination-info">'.$this->getItemsRangeText().'</div>';
        $html .= '</nav>';

        return $html;
    }
}
