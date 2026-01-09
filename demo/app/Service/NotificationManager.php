<?php

namespace App\Service;

use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationMsg;
use Nette\Database\Explorer;
use Tracy\Debugger;

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

    public function checkStatusOfSentNotifications(): void
    {


    }

    public function sendEligibleNotifications(): void
    {
        while (true) {
            $msgAttempts = $this->notificationAttemptRepository->listToSend();
            if (count($msgAttempts) === 0) {
                return; // No more messages to send
            }
            foreach ($msgAttempts as $attempt) {
                $this->sendNotification($attempt);
            }
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
            $this->notificationAttemptRepository->noteMessageSendErrorAndReschedule($attempt, $result->error);
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
}
