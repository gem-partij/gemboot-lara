<?php
namespace Gemboot\Tests\Models;

/**
 * Accepts any field on fill(), like a model with $guarded = [].
 */
class TestUserOpen extends TestUser
{
    protected $fillable = [];
    protected $guarded = [];
}
