<?php
namespace Gemboot\Tests\Models;

use Gemboot\Models\CoreModel as GembootModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Gemboot\Tests\Database\Factories\GembootTestUserFactory;

class TestUser extends GembootModel {

    use HasFactory;

    protected $table = "gemboot_test_user";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // 'password',
        // 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return GembootTestUserFactory::new();
    }

    // Self relations used to test relation search ("relation.column").
    public function selfTyped(): HasMany
    {
        return $this->hasMany(TestUser::class, 'id', 'id');
    }

    public function selfUntyped()
    {
        return $this->hasMany(TestUser::class, 'id', 'id');
    }

    // A public method that is not a relation and must not be callable from search.
    public function displayName(): string
    {
        return (string) $this->name;
    }
}
