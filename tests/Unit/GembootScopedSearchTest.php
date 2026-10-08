<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Services\TestUserService;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A list limited by the app (e.g. "only my records") must stay limited, whatever
 * search parameters the client adds.
 */
class GembootScopedSearchTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        TestUser::factory()->create(['name' => 'Mine', 'email' => 'mine@example.test']);       // id 1
        TestUser::factory()->create(['name' => 'Theirs', 'email' => 'theirs@example.test']);   // id 2
    }

    public static function searchQueries(): array
    {
        // Each one matches only the other user's row.
        return [
            'search, default or mode' => ['search=Theirs&search_field=name'],
            'search, explicit or mode' => ['search=Theirs&search_field=name&search_mode=or'],
            'search_exact' => ['search_exact=Theirs&search_field=name'],
            'relation search' => ['search=Theirs&search_field=selfTyped.name'],
            'several fields' => ['search[]=Theirs&search_field[]=name&search[]=theirs&search_field[]=email'],
            'all columns' => ['search=Theirs'],
            'date' => ['search=' . date('Y') . '&search_field=created_at'],
        ];
    }

    #[DataProvider('searchQueries')]
    function test_search_never_widens_a_scoped_list(string $query)
    {
        // Before 8.6.1, the default "or" mode produced
        // "id = 1 OR name LIKE '%Theirs%'" and returned the other user's row.
        $this->app->instance('request', Request::create('/test/users?' . $query, 'GET'));
        $service = new TestUserService;

        $names = collect($service->listAll(TestUser::where('id', 1))->items())->pluck('name')->all();

        $this->assertNotContains('Theirs', $names);
        $this->assertSame(0, $service->countAll(TestUser::where('id', 2)->where('id', 1)));
    }

    function test_scoped_list_still_searches_within_its_scope()
    {
        $this->app->instance('request', Request::create('/test/users?search=Mine&search_field=name', 'GET'));

        $names = collect((new TestUserService)->listAll(TestUser::where('id', 1))->items())->pluck('name')->all();

        $this->assertSame(['Mine'], $names);
    }

    function test_overridden_get_query_list_all_stays_scoped()
    {
        $this->app->instance('request', Request::create('/test/users?search=Theirs&search_field=name', 'GET'));
        $service = new class extends TestUserService {
            protected function getQueryListAll($model = null, $disable_search = false)
            {
                return parent::getQueryListAll(TestUser::where('id', 1), $disable_search);
            }
        };

        $this->assertSame([], collect($service->listAll()->items())->pluck('name')->all());
    }
}
