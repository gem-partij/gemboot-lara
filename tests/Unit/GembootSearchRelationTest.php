<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Exceptions\BadRequestException;
use Gemboot\Tests\TestCase;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Models\TestUserStrict;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\DataProvider;

class GembootSearchRelationTest extends TestCase
{

    public static function dangerousRelationNames(): array
    {
        // Methods that whereHas() would call on the model if they were accepted
        // as relation names. truncate() emptied the table before this fix.
        return [
            'truncate' => ['truncate'],
            'save' => ['save'],
            'delete' => ['delete'],
            'forceDelete' => ['forceDelete'],
            'push' => ['push'],
            'touch' => ['touch'],
            'replicate' => ['replicate'],
            'non-relation method' => ['displayName'],
            'undefined method' => ['doesNotExist'],
            'query builder method' => ['whereNull'],
        ];
    }

    #[DataProvider('dangerousRelationNames')]
    function test_index_rejects_non_relation_names_and_keeps_data(string $name)
    {
        TestUser::factory()->count(3)->create();

        $this->getJson("/test/users?search=a&search_field={$name}.id")
            ->assertStatus(400);

        $this->assertSame(3, TestUser::count());
    }

    function test_index_rejects_non_relation_names_in_array_and_exact_search()
    {
        TestUser::factory()->count(3)->create();

        $this->getJson('/test/users?search[]=a&search_field[]=truncate.id')->assertStatus(400);
        $this->getJson('/test/users?search_exact=a&search_field=truncate.id')->assertStatus(400);
        $this->getJson('/test/users?search=a&search_field=truncate.id&search_mode=and')->assertStatus(400);

        $this->assertSame(3, TestUser::count());
    }

    function test_typed_and_untyped_relations_still_work()
    {
        TestUser::factory()->create(['name' => 'Findable Person']);
        TestUser::factory()->create(['name' => 'Someone Else']);

        foreach (['selfTyped', 'selfUntyped'] as $relation) {
            $response = $this->getJson("/test/users?search=Findable&search_field={$relation}.name&search_mode=and");

            $response->assertStatus(200);
            $this->assertCount(1, $response->json('data.data'));
            $this->assertSame('Findable Person', $response->json('data.data.0.name'));
        }
    }

    function test_dynamic_relations_still_work()
    {
        TestUser::resolveRelationUsing('selfDynamic', function (TestUser $user): HasMany {
            return $user->hasMany(TestUser::class, 'id', 'id');
        });
        TestUser::factory()->create(['name' => 'Findable Person']);

        $result = TestUser::query()->search('Findable', 'selfDynamic.name', 'and')->get();

        $this->assertCount(1, $result);
    }

    function test_strict_allowlist_only_accepts_listed_relations()
    {
        TestUserStrict::factory()->create(['name' => 'Findable Person']);

        $this->assertCount(1, TestUserStrict::query()->search('Findable', 'selfTyped.name', 'and')->get());

        $this->expectException(BadRequestException::class);
        TestUserStrict::query()->search('Findable', 'selfUntyped.name', 'and')->get();
    }
}
