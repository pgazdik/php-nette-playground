<?php

namespace App\Service\MsgCheck;

use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationMsgStatus;
use Nette\Database\Explorer;
use Tracy\Debugger;

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