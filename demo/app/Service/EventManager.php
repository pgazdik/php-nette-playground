<?php
namespace App\Service;

use App\Common\Maybe;
use App\Model\Entity\Event\Event;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;
use App\Utils\DateUtils;
use App\Utils\MediaHandler;
use DateTime;

class EventManager
{
    public function __construct(
        private EventRepository $eventRepository,
        private NotificationMsgRepository $notificationMsgRepository,
        private NotificationAttemptRepository $notificationAttemptRepository,
        private MediaHandler $mediaHandler,
    ) {
    }

    public function createEvent(Event $event): Maybe
    {
        // 1. Create Event
        $this->eventRepository->insert($event);

        // 2. Calculate scheduledAt
        // Logic: 7 days before appointment. If appointment is within 7 days, scheduledAt = now.
        // We work with Bratislava time because appointmentDate is in Bratislava time.

        $scheduledAt = (clone $event->appointmentDate)->modify('-7 days');

        $now = DateUtils::nowBaDate();

        if ($scheduledAt < $now) {
            $scheduledAt = $now;
        }

        // 3. Create Text Notification
        $text = sprintf(
            "Hello %s, you have an appointment with %s on %s.",
            $event->patientName,
            $event->doctorName,
            $event->appointmentDate->format('Y-m-d H:i')
        );

        $notificationMsg = self::createTextNotification($event, $text, $scheduledAt);
        $this->notificationMsgRepository->insert($notificationMsg);

        // 4. Create Image Notifications from MediaHandler
        $maybeFiles = $this->listImagesForEvent($event);

        if (!$maybeFiles->isSuccess || empty($maybeFiles->value))
            return Maybe::successWithWarning(null, "Only text notification created, no images found!");

        foreach ($maybeFiles->value as $filePath) 
            $this->createAndInsertImageNotification($event, $filePath, $scheduledAt);

        return Maybe::success(null);
    }

    public function listImagesForEvent(Event $event): Maybe
    {
        return $this->mediaHandler->listFilesDateAware(
            $event->doctorName,
            $event->appointmentDate,
            "/\.(jpg|jpeg|png)$/i"
        );
    }

    // TODO write tests
    public function identifyUnusedImages(Event $event, array $notifications): array
    {
        $mainMsg = $this->notificationMsgRepository->getMainTextMessage($event->id);
        $canAddNotifications = $mainMsg && $mainMsg->status->supportsUpdate();
        if (!$canAddNotifications)
            return [];

        $maybeFiles = $this->listImagesForEvent($event);
        if (!$maybeFiles->isSuccess) {
            // TODO add log
            return [];
        }

        $unusedImages = [];
        $existingPaths = array_filter(array_map(fn($n) => $n->filePath, $notifications));
        foreach ($maybeFiles->value as $filePath)
            if (!in_array($filePath, $existingPaths))
                $unusedImages[] = $filePath;

        return $unusedImages;
    }

    // TODO write tests
    public function updateImageNotifications(Event $event, DateTime $scheduledAt, array $toDelete, array $toAdd): void
    {
        // Process deletions
        foreach ($toDelete as $notificationId) {
            $msg = $this->notificationMsgRepository->getById((int) $notificationId);
            if ($msg && $msg->mediaType === MediaType::Image && $msg->status->supportsUpdate())
                $this->notificationMsgRepository->delete($msg->id);
        }

        // Process additions
        foreach ($toAdd as $filePath)
            if (!$this->notificationMsgRepository->existsByEventIdAndFilePath($event->id, $filePath))
                $this->createAndInsertImageNotification($event, $filePath, $scheduledAt);
    }

    private function createAndInsertImageNotification(Event $event, string $filePath, DateTime $scheduledAt): void
    {
        $imageMsg = self::createImageNotification($event, $filePath, $scheduledAt);
        $this->notificationMsgRepository->insert($imageMsg);
    }


    private static function createTextNotification(Event $event, string $text, DateTime $scheduledAt): NotificationMsg
    {
        return new NotificationMsg(
            eventId: $event->id,
            msgIndex: 1,
            notificationType: NotificationType::Main,
            mediaType: MediaType::Text,
            status: NotificationMsgStatus::Draft,
            text: $text,
            scheduledAt: $scheduledAt
        );
    }

    private static function createImageNotification(Event $event, string $filePath, DateTime $scheduledAt): NotificationMsg
    {
        return new NotificationMsg(
            eventId: $event->id,
            msgIndex: 2,
            notificationType: NotificationType::Main,
            mediaType: MediaType::Image,
            status: NotificationMsgStatus::Draft,
            text: '',
            filePath: $filePath,
            scheduledAt: $scheduledAt,
        );
    }

}
