<?php

namespace Gemboot\Tests\Models;

/**
 * TestUser with search and sort allowlists.
 */
class TestUserListed extends TestUser
{
    protected $searchableFields = ['name', 'created_at', 'selfTyped.name'];

    protected $sortableFields = ['name', 'created_at'];
}
