<?php
namespace Tests\Service;

use App\Common\Maybe;
use App\Service\SmsGwService;
use Exception;

class SmsGwMockService extends SmsGwService
{
    /** @var callable|null */
    public $handler = null;

    public array $requests = [];

    public function __construct()
    {
        parent::__construct('http://test.mock', 'test-token');
    }

    public function requestToSmsGateway($urlPath, $postData = null): Maybe
    {
        if (!$this->handler)
            throw new Exception('No handler set for SMS gateway mock');

        return ($this->handler)($urlPath, $postData);
    }
}
