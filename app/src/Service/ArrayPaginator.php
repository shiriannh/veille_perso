<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class ArrayPaginator
{
    /**
     * @template T
     *
     * @param array<int, T> $items
     *
     * @return array{0: array<int, T>, 1: array<string, mixed>}
     */
    public function paginate(Request $request, array $items, string $defaultPerPage = '25'): array
    {
        $allowed = ['5', '10', '25', 'all'];
        $perPage = in_array($request->query->get('perPage'), $allowed, true)
            ? (string) $request->query->get('perPage')
            : $defaultPerPage;
        $total = count($items);

        if ($perPage === 'all') {
            return [$items, [
                'page' => 1,
                'perPage' => 'all',
                'total' => $total,
                'pages' => 1,
                'allowed' => $allowed,
            ]];
        }

        $limit = max(1, (int) $perPage);
        $pages = max(1, (int) ceil($total / $limit));
        $pageValue = (string) $request->query->get('page', '1');
        $page = ctype_digit($pageValue) ? max(1, min($pages, (int) $pageValue)) : 1;
        $offset = ($page - 1) * $limit;

        return [array_slice($items, $offset, $limit), [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pages' => $pages,
            'allowed' => $allowed,
        ]];
    }
}
