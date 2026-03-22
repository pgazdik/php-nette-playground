<?php

namespace App\Service;

use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Service\MsgCheck\MsgCheckNextStepType;
use App\Service\MsgCheck\MsgCheckResponse;
use App\Service\MsgCheck\MsgCheckResponseHandler;
use App\Utils\MediaHandler;
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
        private MsgCheckResponseHandler $msgCheckResponseHandler,
        private MediaHandler $mediaHandler,
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
            status: NotificationAttemptStatus::Sent, // Initial status
            msg: $msg
        );

        $this->notificationAttemptRepository->insert($attempt);

        return $attempt;
    }

    private function l_makeNotificationAttempt(NotificationMsg $msg, NotificationAttempt $attempt): void
    {
        Debugger::log("Sending notification, attempt no.{$attempt->attemptNo} id: {$attempt->id}", "info");

        [$event, $text, $attachements, $error] = $this->prepareMessageInputs($msg);

        if ($error) {
            // Unexpected
            Debugger::log("Cannot send notification for event with id {$msg->eventId}, NotificationMsg id: {$msg->id}. Reason: ". $error);
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
        $event = $this->eventRepository->getById($msg->eventId);
        if (!$event)
            return [null, null, null, "Event not found!"];

        if ($msg->mediaType === MediaType::Text) {
            return [$event, $msg->text, [], null];

        } else { // Image
            if (!$msg->filePath)
                return [null, null, [], "Image path not set!"];

            $fullPath = $this->mediaHandler->resolvePath($msg->filePath);
            if (!file_exists($fullPath))
                return [null, null, [], "Image not found!"];

            $attachements[] = [
                "content_type" => mime_content_type($fullPath),
                "content" => base64_encode(file_get_contents($fullPath)),
            ];

            // Text cannot be null
            return [$event, "", $attachements, null];
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

        $gwResponse = $response[0];

        $msgResponse = new MsgCheckResponse(
            $gwResponse->status,
            $gwResponse->error_code,
            $gwResponse->sending_date ? new DateTime($gwResponse->sending_date) : null,
            $gwResponse->delivery_date ? new DateTime($gwResponse->delivery_date) : null
        );

        $nextStep = $this->msgCheckResponseHandler->determineNextStep($attempt, $msgResponse);

        $this->notificationAttemptRepository->update(
            $attempt,
            $nextStep->newStatus,
            $nextStep->newAttemptCheckAt(),
            $msgResponse->status,
            $nextStep->newGwCheckStatusHistory,
            $msgResponse->errorCode,
            $msgResponse->sendingDate,
            $msgResponse->deliveryDate
        );

        if ($nextStep->type === MsgCheckNextStepType::MarkDelivered) {
            $this->notificationMsgRepository->updateStatus($attempt->notificationMsgId, NotificationMsgStatus::Delivered);
            $this->scheduleNextMessages($attempt->msg);
        } else if ($nextStep->type === MsgCheckNextStepType::ResendMessage) {
            Debugger::log("Notification attempt # {$attempt->id} failed and will be rescheduled. Response: " . json_encode($response));
            $this->rescheduleMessage($attempt->msg, $nextStep->resendDelayInMin);
        } else if ($nextStep->type === MsgCheckNextStepType::SendEmail) {
            $this->notificationMsgRepository->updateStatus($attempt->notificationMsgId, NotificationMsgStatus::Failed);
        }
    }

    private function logAndStoreCheckError($attempt, $errorMsg): void
    {
        Debugger::log("Attempt #{$attempt->id} ->" . $errorMsg);
        $this->notificationAttemptRepository->noteMessageCheckError($attempt, $errorMsg);
    }

    private function rescheduleMessage(NotificationMsg $msg, int $delayInMinutes): void
    {
        $newScheduledAt = new DateTime("+{$delayInMinutes} minutes");

        $this->notificationMsgRepository->rescheduleAt($msg->id, $newScheduledAt);
    }

    private function scheduleNextMessages(NotificationMsg $currentMsg): void
    {
        $nextNotificationMsgs = $this->notificationMsgRepository->findNextMessages($currentMsg);
        if (count($nextNotificationMsgs) === 0) {
            Debugger::log("No more notifications left for event: {$currentMsg->eventId}");
            return;
        }

        foreach ($nextNotificationMsgs as $nextNotificationMsg) {
            // We assume next message is Draft. We set it to Scheduled so it gets picked up.
            if ($nextNotificationMsg->status === NotificationMsgStatus::Draft) {
                $this->notificationMsgRepository->updateStatus($nextNotificationMsg->id, NotificationMsgStatus::Scheduled);
            }
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