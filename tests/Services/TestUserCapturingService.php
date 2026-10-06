<?php
namespace Gemboot\Tests\Services;

/**
 * Records the data store() and update() receive from the controller.
 */
class TestUserCapturingService extends TestUserService
{
    public static array $received = [];

    public function store($requestData, $merge_data_with = [])
    {
        static::$received[] = $requestData;

        return parent::store($requestData, $merge_data_with);
    }
}
