<?php

namespace Gemboot\Services;

use Gemboot\Services\CoreService;
use Illuminate\Database\Eloquent\Model as Eloquent;

class CoreResourceService extends CoreService
{
    public function __construct(Eloquent $model, $with = [], $orderBy = [])
    {
        parent::__construct($model, $with, $orderBy);
    }
}
