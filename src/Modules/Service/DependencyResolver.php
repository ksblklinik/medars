<?php

declare(strict_types=1);

namespace App\Modules\Service;

use App\Modules\Exception\ModuleDependencyException;

final class DependencyResolver
{
    /**
     * Menyusun urutan pemuatan modul berdasarkan dependensi (Topological Sort).
     * Mendeteksi dan melempar exception jika terjadi circular dependency.
     *
     * @param array<string, array<int, string>|array<string, string>> $modules
     * @return array<int, string>
     * @throws ModuleDependencyException
     */
    public function resolveOrder(array $modules): array
    {
        $normalized = [];
        foreach ($modules as $slug => $deps) {
            $normalized[$slug] = array_values(is_array($deps) ? (array_is_list($deps) ? $deps : array_keys($deps)) : []);
        }

        $visited = [];
        $visiting = [];
        $order = [];

        foreach (array_keys($normalized) as $module) {
            if (!isset($visited[$module])) {
                $this->dfs($module, $normalized, $visited, $visiting, $order);
            }
        }

        return $order;
    }

    /**
     * @param array<string, array<int, string>> $graph
     * @param array<string, bool> $visited
     * @param array<string, bool> $visiting
     * @param array<int, string> $order
     */
    private function dfs(
        string $node,
        array $graph,
        array &$visited,
        array &$visiting,
        array &$order,
        array $path = []
    ): void {
        $visiting[$node] = true;
        $path[] = $node;

        $neighbors = $graph[$node] ?? [];
        foreach ($neighbors as $neighbor) {
            if (isset($visiting[$neighbor])) {
                $cycle = implode(' -> ', [...$path, $neighbor]);
                throw ModuleDependencyException::circularDependency($cycle);
            }

            if (!isset($visited[$neighbor])) {
                $this->dfs($neighbor, $graph, $visited, $visiting, $order, $path);
            }
        }

        unset($visiting[$node]);
        $visited[$node] = true;
        $order[] = $node;
    }
}
