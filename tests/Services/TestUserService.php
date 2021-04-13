<?php
namespace Gemboot\Tests\Services;

use Gemboot\Services\CoreService as GembootService;
use Gemboot\Tests\Models\TestUser;

class TestUserService extends GembootService {

    public function __construct(TestUser $model = null, $with = [], $orderBy = [])
    {
        if (empty($model)) {
            $model = new TestUser();
        }
        parent::__construct($model, $with, $orderBy);
    }

}
