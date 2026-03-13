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

enum MsgCheckNextStepType: string
{
    case MarkDelivered = 'markDelivered';
    case RescheduleCheck = 'rescheduleCheck';
    case ResendMessage = 'resendMessage';
    case SendEmail = 'sendEmail';

}