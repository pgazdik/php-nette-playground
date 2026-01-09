<?php
namespace App\Model\Entity\Event;

use DateTime;

class NotificationAttempt
{
    public function __construct(
        public int $notificationMsgId,

        public int $attemptNo, // 1, 2, 3...
        public DateTime $sendAt,
        public NotificationAttemptStatus $status,

        public ?string $sendingError = null, // if error happens while trying to send and we can't even reach the GW

        public ?int $gwId = null,
        public ?string $gwSendStatus = null,
        public ?string $gwCheckStatus = null,
        public ?int $gwErrorCode = null,
        public ?DateTime $gwSendDate = null,
        public ?DateTime $gwDeliveryDate = null,

        public ?int $id = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,

        // NOT MAPPED TO DB
        public ?NotificationMsg $msg = null,
   ) {
    }

    public static function createFirstAttempt(NotificationMsg $msg): NotificationAttempt
    {
        return new NotificationAttempt(
            notificationMsgId: $msg->id,
            attemptNo: 1,
            sendAt: $msg->sendAt,
            status: NotificationAttemptStatus::Scheduled,
            msg: $msg,
        );
    }

    public static function createNextAttempt(NotificationAttempt $previousAttempt, int $delayInMinutes): NotificationAttempt
    {
        return new NotificationAttempt(
            notificationMsgId: $previousAttempt->notificationMsgId,
            attemptNo: $previousAttempt->attemptNo + 1,
            sendAt: (clone $previousAttempt->sendAt)->modify("+{$delayInMinutes} minutes"),
            status: NotificationAttemptStatus::Scheduled,
            msg: $previousAttempt->msg,
        );
    }

}