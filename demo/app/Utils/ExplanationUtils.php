<?php
namespace App\Utils;

use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsgStatus;

class ExplanationUtils
{
    public static function getStatusExplanations(): array
    {
        return [
            (object) [
                'name' => NotificationMsgStatus::Draft->value,
                'color' => NotificationMsgStatus::Draft->toColor()->value,
                'description' => 'Message was created and needs to be approved..',
            ],
            (object) [
                'name' => NotificationMsgStatus::Scheduled->value,
                'color' => NotificationMsgStatus::Scheduled->toColor()->value,
                'description' => 'Message was approved and is scheduled to be sent.',
            ],
            (object) [
                'name' => NotificationAttemptStatus::Sent->value,
                'color' => NotificationAttemptStatus::Sent->toColor()->value,
                'description' => 'Message was sent to the SMS Gateway. GW responded properly.',
            ],
            (object) [
                'name' => NotificationAttemptStatus::NotFound->value,
                'color' => NotificationAttemptStatus::NotFound->toColor()->value,
                'description' => 'Checking message against GW failed because the message ID is not known. This happens when checking status shortly after sending.',
            ],
            (object) [
                'name' => NotificationAttemptStatus::Queued->value,
                'color' => NotificationAttemptStatus::Queued->toColor()->value,
                'description' => 'Checking message against GW was OK, message will be sent later. We will check it later.',
            ],
            (object) [
                'name' => NotificationAttemptStatus::CheckError->value,
                'color' => NotificationAttemptStatus::CheckError->toColor()->value,
                'description' => 'Checking message against GW failed with an error. We will check it later or need to intervene manually.',
            ],
            (object) [
                'name' => NotificationAttemptStatus::Delivered->value,
                'color' => NotificationAttemptStatus::Delivered->toColor()->value,
                'description' => 'GW confirmed message was successfully delivered to the recipient.',
            ],
            (object) [
                'name' => NotificationAttemptStatus::Failed->value,
                'color' => NotificationAttemptStatus::Failed->toColor()->value,
                'description' => 'Some kind of error occured, we will try to resend.',
            ],
        ];
    }
}
