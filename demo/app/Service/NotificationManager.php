<?php

namespace App\Service;

use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationMsgStatus;
use Nette\Database\Explorer;
use Tracy\Debugger;

use DateTime;

class NotificationManager
{
    public static final string $MMS_PATH = "mms";

    public function __construct(
        private Explorer $database,
        private EventRepository $eventRepository,
        private NotificationMsgRepository $notificationMsgRepository,
        private NotificationAttemptRepository $notificationAttemptRepository,
        private SmsGwService $smsGwService,
        private LockService $lockService,
        private NotificationCheckResponseHandler $notificationCheckResponseHandler,
    ) {
    }

    //
    // Approve / Withdraw notification
    //

    public function approveNotification(int $msgId): void
    {
        $msg = $this->notificationMsgRepository->getById($msgId);
        if (!$msg) {
            throw new \Exception("Notification not found");
        }

        $this->notificationMsgRepository->approveNotificationsForEvent($msg->eventId);
    }

    public function withdrawNotification(int $msgId): void
    {
        $this->notificationMsgRepository->withdrawNotification($msgId);
    }

    //
    // Sending Notifications
    //

    public function sendEligibleNotifications(): void
    {
        $msgs = $this->notificationMsgRepository->listEligibleForSending();
        if (count($msgs) === 0)
            return; // No more messages to send

        Debugger::log("Sending notifications for " . count($msgs) . " messages");

        foreach ($msgs as $msg) {
            $this->sendNotificationFor($msg);
        }
    }

    public function forceSend(int $msgId): ?string
    {
        $msg = $this->notificationMsgRepository->getById($msgId);
        if (!$msg)
            return "Cannot send message, Message($msgId) not found!";

        return $this->sendNotificationFor($msg);
    }

    private function sendNotificationFor(NotificationMsg $msg): ?string
    {
        if (!$this->acquireEventLock($msg))
            return "Cannot send message, another process is sending for this event right now!";

        try {
            // Double check status inside lock
            $msg = $this->notificationMsgRepository->getById($msg->id);
            if ($msg->status !== NotificationMsgStatus::Scheduled)
                return "Cannot send message, it is not scheduled.";

            $attempt = $this->l_insertNextAttempt($msg);

            // Mark message as Sent so it's not picked up again immediately
            $this->notificationMsgRepository->updateStatus($msg->id, NotificationMsgStatus::Sent);

            $this->l_makeNotificationAttempt($msg, $attempt);

            return null;

        } finally {
            $this->releaseEventLock($msg);
        }
    }

    private function l_insertNextAttempt(NotificationMsg $msg): NotificationAttempt
    {
        $attemptNo = $this->notificationAttemptRepository->getNextAttemptNo($msg->id);
        $attempt = new NotificationAttempt(
            notificationMsgId: $msg->id,
            attemptNo: $attemptNo,
            scheduledAt: new DateTime(), // Required by DB but ignored for logic
            status: NotificationAttemptStatus::Sent, // Initial status
            msg: $msg
        );

        $this->notificationAttemptRepository->insert($attempt);

        return $attempt;
    }

    private function l_makeNotificationAttempt(NotificationMsg $msg, NotificationAttempt $attempt): void
    {
        Debugger::log("Sending notification, attempt no.{$attempt->attemptNo} id: {$attempt->id}", "info");

        [$event, $text, $attachements] = $this->prepareMessageInputs($msg);

        if (!$event) {
            // Unexpected
            Debugger::log("Cannot send notification, event with id {$msg->eventId} not found. NotificationMsg id: {$msg->id}");
            $this->notificationAttemptRepository->noteMessageSendError($attempt, "Event not found");
            $this->notificationMsgRepository->updateStatus($msg->id, NotificationMsgStatus::Failed);
            return;
        }

        $postData = json_encode([
            "to" => [$event->phoneNumber],
            "text" => $text,
            "encoding" => "unicode",
            "validity" => "max",
            // "send_after" => "08:00",
            // "send_before" => "21:00",
            "test" => false,
            "attachments" => $attachements
        ]);

        $result = $this->smsGwService->requestToSmsGateway(self::$MMS_PATH, $postData);

        if (!$result->isSuccess) {
            // sending error
            $this->notificationAttemptRepository->noteMessageSendError($attempt, $result->error);
            $this->rescheduleMessage($msg, $attempt->attemptNo);

            return;
        }

        $response = $result->value;

        if (!is_array($response)) {
            $this->notificationAttemptRepository->noteMessageSendError($attempt, "Unexpected response: " . json_encode($response));
            $this->rescheduleMessage($msg, $attempt->attemptNo);

            return;
        }

        $response = $response[0];

        $this->notificationAttemptRepository->noteMessageSent($attempt, $response->id, $response->status);
    }

    private function prepareMessageInputs(NotificationMsg $msg): array
    {
        if ($msg->mediaType === MediaType::Text) {
            $event = $this->eventRepository->getByIdNoImage($msg->eventId);

            return [$event, $msg->text, []];

        } else { // Image
            $event = $this->eventRepository->getByIdWithImage($msg->eventId);

            if (!$event)
                return [null, null, []];

            $attachements[] = [
                "content_type" => $event->attachmentType,
                "content" => base64_encode($event->attachmentContent),
            ];

            // Text cannot be null
            return [$event, "", $attachements];
        }
    }

    //
    // Checking Notifications
    //

    public function checkStatusOfSentNotifications(): void
    {
        $attempts = $this->notificationAttemptRepository->listToCheck();
        Debugger::log("Checking status of " . count($attempts) . " attempts");
        if (count($attempts) === 0) {
            return;
        }
        foreach ($attempts as $attempt) {
            if (!$this->acquireEventLock($attempt->msg))
                continue;

            try {
                $this->checkNotificationStatus($attempt);
            } finally {
                $this->releaseEventLock($attempt->msg);
            }
        }
    }

    public function forceCheckStatus(int $attemptId): ?string
    {
        $attempt = $this->notificationAttemptRepository->getById($attemptId);
        if (!$attempt)
            return "Cannot check message, corresponding Attempt #$attemptId not found!";

        if (!$attempt->status->supportsCheck())
            return "Cannot check message, not supported for status : " . $attempt->status->value;

        if (!$this->acquireEventLock($attempt->msg))
            return "Cannot check message, another process is sending right now!";

        try {
            $this->checkNotificationStatus($attempt);
            return null;

        } finally {
            $this->releaseEventLock($attempt->msg);
        }
    }

    private function checkNotificationStatus(NotificationAttempt $attempt)
    {
        Debugger::log("Checking notification, attempt id: {$attempt->id}", "info");

        $gwId = $attempt->gwId;

        $result = $this->smsGwService->requestToSmsGateway("sent?id_from={$gwId}&id_to={$gwId}", null);
        if (!$result->isSuccess) {
            $this->logAndStoreCheckError($attempt, "Sending check failed: {$result->error}");
            // Do NOT reschedule here? Check failure usually means try checking later. 
            return;
        }

        $response = $result->value;

        // if the message is not yet recognized by the SMS GW, the response JSON is {"message":"Resource(s) not found"}
        if (!is_array($response)) {
            if (property_exists($response, 'message') && str_contains($response->message, 'not found')) {
                $this->notificationAttemptRepository->noteMessageNotFound($attempt);

            } else {
                $this->logAndStoreCheckError($attempt, "Unexpected response: " . json_encode($response));
            }
            return;
        }

        if (sizeof($response) !== 1) {
            $this->logAndStoreCheckError($attempt, "Wrong number of responses: " . json_encode($response));
            return;
        }

        $response = $response[0];

        // sending_ok_no_report
        // sending_ok
        // delivery_ok
        // delivery_pending
        // delivery_unknown
        // delivery_failed
        // sending_error
        // reserved
        // error
        $gwStatus = $response->status;

        $gwDeliveryDate = $response->delivery_date;

        $delivered = $gwDeliveryDate !== null || $gwStatus === 'sending_ok';
        $failed = str_contains($gwStatus, 'error');

        $newStatus = $delivered ? NotificationAttemptStatus::Delivered :
            ($failed ? NotificationAttemptStatus::Failed : NotificationAttemptStatus::Queued);

        $this->notificationAttemptRepository->update(
            $attempt,
            $newStatus,
            $gwStatus,
            $response->error_code,
            $response->sending_date ? new DateTime($response->sending_date) : null,
            $gwDeliveryDate ? new DateTime($gwDeliveryDate) : null
        );

        if ($delivered) {
            $this->notificationMsgRepository->updateStatus($attempt->notificationMsgId, NotificationMsgStatus::Delivered);
            $this->scheduleNextMessage($attempt->msg);
        } else if ($failed) {
            Debugger::log("Notification attempt # {$attempt->id} failed and will be rescheduled. Response: " . json_encode($response));
            $this->rescheduleMessage($attempt->msg, $attempt->attemptNo);
        }
    }

    private function logAndStoreCheckError($attempt, $errorMsg): void
    {
        Debugger::log("Attempt #{$attempt->id} ->" . $errorMsg);
        $this->notificationAttemptRepository->noteMessageCheckError($attempt, $errorMsg);
    }

    private function rescheduleMessage(NotificationMsg $msg, int $lastAttemptNo): void
    {
        $delayInMinutes = min(60, pow(2, $lastAttemptNo - 1));

        $newScheduledAt = (new DateTime())->modify("+{$delayInMinutes} minutes");

        $this->notificationMsgRepository->rescheduleAt($msg->id, $newScheduledAt);
    }

    private function scheduleNextMessage(NotificationMsg $currentMsg): void
    {
        $nextNotificationMsg = $this->notificationMsgRepository->findNextMessage($currentMsg);
        if (!$nextNotificationMsg) {
            Debugger::log("No more notifications left for event: {$currentMsg->eventId}");
            return;
        }

        // We assume next message is New. We set it to Scheduled so it gets picked up.
        if ($nextNotificationMsg->status === NotificationMsgStatus::Draft) {
            $this->notificationMsgRepository->updateStatus($nextNotificationMsg->id, NotificationMsgStatus::Scheduled);
            // Optionally ensure scheduledAt is now? It should be already set to 'now' (or 7 days prior logic) by creation.
            // If it was scheduled in the past, it will be picked up immediately.
        }
    }

    private function acquireEventLock(NotificationMsg $msg, int $timeout = 0): bool
    {
        return $this->lockService->acquireLock("event_{$msg->eventId}", $timeout);
    }

    private function releaseEventLock(NotificationMsg $msg): void
    {
        $this->lockService->releaseLock("event_{$msg->eventId}");
    }

}