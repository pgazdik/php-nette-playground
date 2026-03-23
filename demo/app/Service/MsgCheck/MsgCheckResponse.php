<?php

namespace App\Service\MsgCheck;

use DateTime;

class MsgCheckResponse
{
public function __construct(
        public string  $status,
        public ?string $errorCode,
        public ?DateTime $sendingDate,
        public ?DateTime $deliveryDate,
    ) {
    }
}