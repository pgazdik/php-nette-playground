<?php

namespace App\Service\MsgCheck;

enum MsgCheckNextStepType: string
{
    case MarkDelivered = 'markDelivered';
    case RescheduleCheck = 'rescheduleCheck';
    case ResendMessage = 'resendMessage';
    case SendEmail = 'sendEmail';

}