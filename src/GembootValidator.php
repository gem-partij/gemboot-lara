<?php

namespace Gemboot;

use Illuminate\Support\Facades\Validator;
use Gemboot\Exceptions\BadRequestException;
use Gemboot\GembootResponse;

class GembootValidator
{
    protected $validator;
    protected $fails;
    protected $errors;

    public function validator()
    {
        return $this->validator;
    }

    public function fails()
    {
        return $this->validator->fails();
    }

    public function errors()
    {
        return $this->validator->errors();
    }

    public function make(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        $this->validator = Validator::make($data, $rules, $messages, $customAttributes);
        return $this->validator;
    }

    public function makeAndThrow(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        $this->make($data, $rules, $messages, $customAttributes);
        if ($this->fails()) {
            throw new BadRequestException(json_encode($this->errors()));
        }
        return true;
    }

    public function makeAndResponseJson(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        $this->make($data, $rules, $messages, $customAttributes);
        if ($this->fails()) {
            return (new GembootResponse)->responseBadRequest(['error' => $this->errors()]);
        }
        return true;
    }
}
