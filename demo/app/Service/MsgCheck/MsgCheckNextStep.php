<?php

namespace App\Service\MsgCheck;

use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use DateTime;

class MsgCheckNextStep
{
    public function __construct(
        public NotificationAttempt $attempt,
    ) {
    }
    
    public MsgCheckNextStepType $type;
    public NotificationAttemptStatus $newStatus;
    public string $newGwCheckStatusHistory;
    public ?int $recheckDelayInMin = null;
    public ?int $resendDelayInMin = null;

    public function newAttemptCheckAt(): DateTime {
        return $this->recheckDelayInMin ? new DateTime("+{$this->recheckDelayInMin} minutes") : $this->attempt->checkAt;
    }
}
