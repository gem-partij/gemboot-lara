<?php
namespace Gemboot\Tests\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TestUserHidden extends TestUser
{
    protected $hidden = ['password', 'remember_token'];

    // Relation to a model whose password column is hidden.
    public function selfHidden(): HasMany
    {
        return $this->hasMany(TestUserHidden::class, 'id', 'id');
    }
}
