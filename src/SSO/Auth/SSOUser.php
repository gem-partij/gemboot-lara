<?php

namespace Gemboot\SSO\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class SSOUser implements Authenticatable, Arrayable, JsonSerializable
{
    protected $attributes;

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public function getAuthIdentifierName()
    {
        return 'id';
    }
    public function getAuthIdentifier()
    {
        return $this->attributes['id'];
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
    public function setRememberToken($value) {}
    public function getRememberTokenName()
    {
        return null;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __toString()
    {
        return json_encode($this->attributes);
    }

    public function toArray()
    {
        return $this->attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
