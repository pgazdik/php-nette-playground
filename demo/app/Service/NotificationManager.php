<?php

namespace App\Service;

use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Model\Entity\Event\NotificationMsg;
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
    ) {
    }

    //
    // Sending Notifications
    //

    public function sendEligibleNotifications(): void
    {
        // TODO add locking
        $msgAttempts = $this->notificationAttemptRepository->listToSend();
        if (count($msgAttempts) === 0) {
            return; // No more messages to send
        }
        foreach ($msgAttempts as $attempt) {
            $this->sendNotification($attempt);
        }
    }

    public function forceSend(int $attemptId): ?string
    {
        $attempt = $this->notificationAttemptRepository->getById($attemptId);
        if (!$attempt)
            return "Cannot send message, corresponding Attempt($attemptId) not found!";

        if ($attempt->status !== NotificationAttemptStatus::Scheduled)
            return "Cannot send message,  seems it was sent already?";

        $this->sendNotification($attempt);
        return null;
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
            $this->notificationAttemptRepository->noteMessageSendErrorAndReschedule($attempt, $result->error);
            $this->scheduleNextAttempt($attempt);

            return;
        }

        $response = $result->value[0];

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

            return [$event, null, $attachements];
        }
    }

    //
    // Checking Notifications
    //

    public function checkStatusOfSentNotifications(): void
    {
        $msgAttempts = $this->notificationAttemptRepository->listToCheck();
        if (count($msgAttempts) === 0) {
            return; // No more messages to send
        }
        foreach ($msgAttempts as $attempt) {
            $this->checkNotification($attempt);
        }
    }

    private function checkNotification(NotificationAttempt $attempt)
    {
        Debugger::log("Checking notification, attempt id: {$attempt->id}", "info");

        $gwId = $attempt->gwId;

        $result = $this->smsGwService->requestToSmsGateway('sent?id_from=' . $gwId . '&id_to=' . $gwId, null);
        if (!$result->isSuccess) {
            // TODO think later how to handle these errors
            Debugger::log('MMS check failed! Error: ' . $result->error, Debugger::ERROR);
            return;
        }

        $response = $result->value;

        // if the message is not yet recognized by the SMS GW, the response JSON is {"message":"Resource(s) not found"}
        if (!is_array($response)) {
            if (property_exists($response, 'message') && str_contains($response->message, 'not found')) {
                // TODO think later how to handle these errors
                Debugger::log('MMS not found yet, try again later!;', Debugger::INFO);

            } else {
                // TODO think later how to handle these errors
                $msg = "Unexpected response: " . json_encode($response);
                Debugger::log($msg, Debugger::ERROR);
            }
            return;
        }

        if (sizeof($response) !== 1) {
            // TODO think later how to handle these errors
            Debugger::log("Wrong number of responses: " . json_encode($response), Debugger::ERROR);
            return;
        }

        $response = $response[0];

        $gwDeliveryDate = $response->delivery_date;
        $delivered = $gwDeliveryDate !== null;
        $newStatus = $delivered ? NotificationAttemptStatus::Delivered : NotificationAttemptStatus::Failed;

        $this->notificationAttemptRepository->update(
            $attempt,
            $newStatus,
            $response->status,
            $response->error_code,
            $response->sending_date ? new DateTime($response->sending_date) : null,
            $gwDeliveryDate ? new DateTime($gwDeliveryDate) : null
        );

        if ($delivered) {
            $this->scheduleAttemptForNextMessage($attempt);
        } else {
            // $this->scheduleNextAttempt($attempt);
        }

    }

    private function scheduleNextAttempt(NotificationAttempt $attempt): void
    {
        $nextAttempt = NotificationAttempt::createNextAttempt($attempt);
        $this->notificationAttemptRepository->create($nextAttempt);
    }

    private function scheduleAttemptForNextMessage(NotificationAttempt $attempt): void
    {
        $msg = $attempt->msg;
        $nextNotificationMsg = $this->notificationMsgRepository->findNextMessage($msg);
        if (!$nextNotificationMsg) {
            Debugger::log("All notifications were sent for event: {$msg->eventId}");
            return;
        }

        $this->notificationAttemptRepository->create(NotificationAttempt::createFirstAttempt($nextNotificationMsg));
    }
}
