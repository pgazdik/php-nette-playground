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
    ) {
    }

    //
    // Sending Notifications
    //

    public function sendEligibleNotifications(): void
    {
        $msgAttempts = $this->notificationAttemptRepository->listToSend();
        if (count($msgAttempts) === 0) {
            return; // No more messages to send
        }

        Debugger::log("Sending notifications for " . count($msgAttempts) . " attempts");

        foreach ($msgAttempts as $attempt) {
            if (!$this->acquireEventLock($attempt->msg))
                continue;

            try {
                $this->sendNotification($attempt);
            } finally {
                $this->releaseEventLock($attempt->msg);
            }
        }
    }

    public function forceSend(int $attemptId): ?string
    {
        $attempt = $this->notificationAttemptRepository->getById($attemptId);
        if (!$attempt)
            return "Cannot send message, corresponding Attempt($attemptId) not found!";

        if ($attempt->status !== NotificationAttemptStatus::Scheduled)
            return "Cannot send message, seems it was sent already?";

        if (!$this->acquireEventLock($attempt->msg))
            return "Cannot send message, another process is sending right now!";

        try {
            $this->sendNotification($attempt);
            return null;
        } finally {
            $this->releaseEventLock($attempt->msg);
        }
    }

    private function sendNotification(NotificationAttempt $attempt): void
    {
        Debugger::log("Sending notification, attempt id: {$attempt->id}", "info");

        $msg = $attempt->msg;
        assert($msg !== null, "Message should not be null for attempt {$attempt->id}");

        [$event, $text, $attachements] = $this->prepareMessageInputs($msg);

        if (!$event) {
            Debugger::log("Cannot send notification, event with id {$msg->eventId} not found. NotificationMsg id: {$msg->id}");
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
            $this->scheduleNextAttempt($attempt);

            return;
        }

        $response = $result->value;

        if (!is_array($response)) {
            $this->notificationAttemptRepository->noteMessageSendError($attempt, "Unexpected response: " . json_encode($response));
            $this->scheduleNextAttempt($attempt);

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
        $msgAttempts = $this->notificationAttemptRepository->listToCheck();
        Debugger::log("Checking status of " . count($msgAttempts) . " attempts");
        if (count($msgAttempts) === 0) {
            return; // No more messages to send
        }
        foreach ($msgAttempts as $attempt) {
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

        $gwDeliveryDate = $response->delivery_date;
        $status = $response->status;

        $delivered = $gwDeliveryDate !== null;
        $failed = str_contains($status, 'error');

        $newStatus = $delivered ? NotificationAttemptStatus::Delivered :
            ($failed ? NotificationAttemptStatus::Failed : NotificationAttemptStatus::Queued);

        $this->notificationAttemptRepository->update(
            $attempt,
            $newStatus,
            $status,
            $response->error_code,
            $response->sending_date ? new DateTime($response->sending_date) : null,
            $gwDeliveryDate ? new DateTime($gwDeliveryDate) : null
        );

        if ($delivered) {
            $this->notificationMsgRepository->updateStatus($attempt->notificationMsgId, NotificationMsgStatus::Delivered);
            $this->scheduleAttemptForNextMessage($attempt);
        } else if ($failed) {
            Debugger::log("Notification attempt # {$attempt->id} failed and will be rescheduled. Response: " . json_encode($response));
            $this->scheduleNextAttempt($attempt);
        }
    }

    private function logAndStoreCheckError($attempt, $msg): void
    {
        Debugger::log("Attempt #{$attempt->id} ->" . $msg);
        $this->notificationAttemptRepository->noteMessageCheckError($attempt, $msg);
    }

    private function scheduleNextAttempt(NotificationAttempt $attempt): void
    {
        $nextAttempt = NotificationAttempt::createNextAttempt($attempt);
        $this->notificationAttemptRepository->insert($nextAttempt);
    }

    private function scheduleAttemptForNextMessage(NotificationAttempt $attempt): void
    {
        $msg = $attempt->msg;
        $nextNotificationMsg = $this->notificationMsgRepository->findNextMessage($msg);
        if (!$nextNotificationMsg) {
            Debugger::log("No more notifications left for event: {$msg->eventId}");
            return;
        }

        $this->notificationAttemptRepository->insert(NotificationAttempt::createFirstAttempt($nextNotificationMsg));
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
