<?php

namespace Gemboot\Tests\Models;

/**
 * Only a relation field is searchable: a search without search_field has no
 * column to look in.
 */
class TestUserListedRelationsOnly extends TestUser
{
    protected $searchableFields = ['selfTyped.name'];
}
