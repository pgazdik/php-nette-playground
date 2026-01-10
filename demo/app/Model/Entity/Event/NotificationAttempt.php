<?php
namespace App\Model\Entity\Event;

use App\Utils\DateUtils;
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
            sendAt: DateUtils::baToUtc($msg->sendAt),
            status: NotificationAttemptStatus::Scheduled,
            msg: $msg,
        );
    }

    public static function createNextAttempt(NotificationAttempt $previousAttempt): NotificationAttempt
    {
        // If we try to send a notification manually before it would be sent automatically, and it fails, 
        // we do not want to delay the next attempt but keep the original time
        $needsDelay = $previousAttempt->sendAt <= new DateTime();
        $newSendAt = $needsDelay ? self::computeDelay($previousAttempt) : $previousAttempt->sendAt;

        return new NotificationAttempt(
            notificationMsgId: $previousAttempt->notificationMsgId,
            attemptNo: $previousAttempt->attemptNo + 1,
            sendAt: DateUtils::baToUtc($newSendAt),
            status: NotificationAttemptStatus::Scheduled,
            msg: $previousAttempt->msg,
        );
    }

    public static function computeDelay(NotificationAttempt $previousAttempt): DateTime
    {
        $delayInMinutes = min(60, pow(2, $previousAttempt->attemptNo - 1));
        return (clone $previousAttempt->sendAt)->modify("+{$delayInMinutes} minutes");
    }

}