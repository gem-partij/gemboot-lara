<?php

namespace Gemboot\Auth;

use Gemboot\GembootPermission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The user of the gemboot guard: the auth service's answer as attributes
 * ($user->name), plus roles and permissions when the verifier knows them.
 *
 * Roles and permissions are null when unknown. Then hasRole() and
 * hasPermissionTo() ask the auth service for the current request's token.
 */
class GembootUser implements Authenticatable, Arrayable, JsonSerializable
{
    /**
     * @param array<string, mixed> $attributes
     * @param array<int, string>|null $roles null = unknown, ask the auth service
     * @param array<int, string>|null $permissions null = unknown, ask the auth service
     */
    public function __construct(
        protected array $attributes,
        protected ?array $roles = null,
        protected ?array $permissions = null,
    ) {
    }

    /**
     * A user from the auth service's `me` answer, the same data token-validated
     * puts into user_login. Roles and permissions stay unknown.
     */
    public static function fromAuthService(array $answer): static
    {
        return new static($answer);
    }

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    /**
     * The "id" attribute, or "user.id" when the answer wraps the user.
     */
    public function getAuthIdentifier()
    {
        return $this->attributes['id'] ?? data_get($this->attributes, 'user.id');
    }

    public function getAuthPasswordName()
    {
        return null;
    }

    public function getAuthPassword()
    {
        return null;
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName()
    {
        return null;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /** @return array<int, string>|null null when unknown */
    public function roles(): ?array
    {
        return $this->roles;
    }

    /** @return array<int, string>|null null when unknown */
    public function permissions(): ?array
    {
        return $this->permissions;
    }

    /**
     * Whether the user has any of the roles. "admin|editor" and
     * ['admin', 'editor'] both mean either one.
     */
    public function hasRole(string|array $roles): bool
    {
        if ($this->roles === null) {
            return (new GembootPermission)->hasRole($roles);
        }

        return array_intersect($this->names($roles), $this->roles) !== [];
    }

    /**
     * Same rules as GembootPermission::hasPermissionTo(): a string needs every
     * name in it ("report.read|report.export" needs both), an array any one.
     */
    public function hasPermissionTo(string|array $permissions): bool
    {
        if ($this->permissions === null) {
            return (new GembootPermission)->hasPermissionTo($permissions);
        }

        $names = $this->names($permissions);
        $granted = array_intersect($names, $this->permissions);

        if (is_array($permissions)) {
            return $granted !== [];
        }

        return $names !== [] && count($granted) === count($names);
    }

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    public function toArray()
    {
        return $this->attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function names(string|array $names): array
    {
        $list = is_array($names) ? $names : explode('|', $names);

        return array_values(array_filter(array_map('strval', $list), fn ($n) => $n !== ''));
    }
}
