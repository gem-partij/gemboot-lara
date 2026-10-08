<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\Controllers\TestUserCursorController;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Services\TestUserService;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GembootPaginationModesTest extends TestCase
{
    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function ($query) use (&$count) {
            if (stripos($query->sql, 'count(') !== false) {
                $count++;
            }
        });
        $callback();

        return $count;
    }

    function test_default_mode_is_unchanged()
    {
        TestUser::factory()->count(3)->create();

        $page = (new TestUserService)->listAll();

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $page);
        $this->assertSame(3, $page->total());
    }

    function test_simple_and_cursor_skip_the_count_query()
    {
        TestUser::factory()->count(3)->create();

        $this->assertSame(1, $this->countQueries(fn () => (new TestUserService)->listAll()));
        $this->assertSame(0, $this->countQueries(fn () => (new TestUserService)->setPagination('simple')->listAll()));
        $this->assertSame(0, $this->countQueries(fn () => (new TestUserService)->setPagination('cursor')->listAll()));
    }

    function test_cursor_pages_visit_every_row_once_even_with_duplicate_sort_values()
    {
        // Ten users sharing only two names: without a tie-breaker, pages sorted
        // by name alone could skip or repeat rows.
        foreach (range(1, 10) as $i) {
            TestUser::factory()->create(['name' => $i % 2 ? 'Same A' : 'Same B']);
        }

        $seen = [];
        $cursor = null;
        do {
            $this->app->instance('request', Request::create('/x', 'GET', array_filter(['order' => 'name', 'page_len' => 3, 'cursor' => $cursor])));
            $page = (new TestUserService)->setPagination('cursor')->listAll();
            foreach ($page->items() as $user) {
                $seen[] = $user->id;
            }
            $cursor = $page->nextCursor()?->encode();
        } while ($cursor);

        sort($seen);
        $this->assertSame(range(1, 10), $seen);
    }

    function test_controller_property_and_response_shape()
    {
        $this->app['router']->get('/cursor/users', [TestUserCursorController::class, 'index']);
        TestUser::factory()->count(3)->create();

        $response = $this->getJson('/cursor/users?page_len=2')->assertOk();

        $this->assertCount(2, $response->json('data.data'));
        $this->assertNotNull($response->json('data.next_cursor'));
        $this->assertArrayNotHasKey('total', $response->json('data'));

        $next = $this->getJson('/cursor/users?page_len=2&cursor=' . $response->json('data.next_cursor'))->assertOk();
        $this->assertCount(1, $next->json('data.data'));
    }

    function test_unknown_mode_is_a_developer_error()
    {
        TestUser::factory()->create();

        $this->expectException(\LogicException::class);
        (new TestUserService)->setPagination('infinite')->listAll();
    }
}
