<?php
namespace Gemboot\Tests\Models;

class TestUserStrict extends TestUser
{
    // Strict mode: only these relations may be searched.
    protected $searchableRelations = ['selfTyped'];
}
