<?php

namespace PerfectOblivion\ActionServiceResponder\Tests\Foundation\Services;

use PerfectOblivion\ActionServiceResponder\Services\Service;
use PerfectOblivion\ActionServiceResponder\Services\Traits\SelfCallingService;

class TestNoAutorunService extends Service
{
    use SelfCallingService;

    /** @var bool */
    public $autorunIfEnabled = false;

    /**
     * Handle the call to the service.
     *
     * @param  array  $parameters
     *
     * @return mixed
     */
    public function run(array $parameters = [])
    {
        return $parameters;
    }
}
