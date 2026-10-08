<?php

namespace Gemboot\Commands;

use Gemboot\Middleware\HasPermissionTo;
use Gemboot\Middleware\HasRole;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;

/**
 * Lists every role and permission the app's routes check, so the auth team can
 * see what a service needs, and typos stand out before they become 403s.
 */
class Permissions extends Command
{
    protected $signature = 'gemboot:permissions
                            {--json : Print the result as JSON}';

    protected $description = 'List the roles and permissions checked by your routes';

    public function handle(): int
    {
        $found = ['role' => [], 'permission' => []];

        foreach (app('router')->getRoutes() as $route) {
            foreach ($this->checksOf($route) as [$type, $name]) {
                $found[$type][$name][] = $this->describe($route);
            }
        }

        foreach ($found as &$names) {
            ksort($names);
            foreach ($names as &$routes) {
                $routes = array_values(array_unique($routes));
            }
        }
        unset($names, $routes);

        $typos = array_merge(
            $this->possibleTypos(array_keys($found['role'])),
            $this->possibleTypos(array_keys($found['permission']))
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'roles' => $found['role'],
                'permissions' => $found['permission'],
                'possible_typos' => $typos,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->printSection('Roles', $found['role']);
        $this->printSection('Permissions', $found['permission']);

        if ($typos) {
            $this->newLine();
            $this->line('<fg=yellow>Possible typos (names that differ by one or two characters):</>');
            foreach ($typos as [$a, $b]) {
                $this->line("  ! {$a}  <->  {$b}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Role and permission names checked on a route, after expanding middleware
     * groups and aliases as Laravel does when handling a request.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function checksOf(Route $route): array
    {
        $checks = [];
        $middleware = app('router')->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware());

        foreach ($middleware as $item) {
            if (!is_string($item) || !str_contains($item, ':')) {
                continue;
            }

            [$class, $parameters] = explode(':', $item, 2);
            $type = match (ltrim($class, '\\')) {
                HasRole::class => 'role',
                HasPermissionTo::class => 'permission',
                default => null,
            };
            if ($type === null) {
                continue;
            }

            // The middleware receives the first parameter; "|" separates names.
            $first = explode(',', $parameters)[0];
            foreach (array_filter(explode('|', $first), fn ($n) => $n !== '') as $name) {
                $checks[] = [$type, $name];
            }
        }

        return $checks;
    }

    private function describe(Route $route): string
    {
        $methods = array_diff($route->methods(), ['HEAD']);

        return implode('|', $methods) . ' /' . ltrim($route->uri(), '/');
    }

    private function printSection(string $title, array $names): void
    {
        $this->line("<options=bold>{$title}</> (" . count($names) . ')');

        if (!$names) {
            $this->line('  none');
            $this->newLine();

            return;
        }

        foreach ($names as $name => $routes) {
            $this->line("  <info>{$name}</info>");
            foreach ($routes as $route) {
                $this->line("      {$route}");
            }
        }
        $this->newLine();
    }

    /**
     * Pairs of names that differ by one or two characters, e.g. user.read and user.raed.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function possibleTypos(array $names): array
    {
        $pairs = [];
        $count = count($names);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (levenshtein($names[$i], $names[$j]) <= 2) {
                    $pairs[] = [$names[$i], $names[$j]];
                }
            }
        }

        return $pairs;
    }
}
