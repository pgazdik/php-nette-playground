<?php
namespace App\Service;

use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;
use App\Utils\AppUtils;
use App\Utils\DateUtils;
use Nette\Database\Explorer;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;

class NotificationMsgRepository
{
    public function __construct(
        private Explorer $database
    ) {
    }

    public function insert(NotificationMsg $msg): void
    {
        $row = $this->database->table('notification_msg')->insert([
            'event_id' => $msg->eventId,
            'msg_index' => $msg->msgIndex,
            'notification_type' => $msg->notificationType->value,
            'media_type' => $msg->mediaType->value,
            'status' => $msg->status->value,
            'text' => $msg->text,
            'file_path' => $msg->filePath,
            'scheduled_at' => DateUtils::baToUtc($msg->scheduledAt),
        ]);

        $msg->id = $row->id;
    }

    public function getCount(): int
    {
        return $this->database->table('notification_msg')->count('*');
    }

    /** @return NotificationMsg[] */
    public function getAll(int $limit, int $offset): array
    {
        $rows = $this->database->table('notification_msg')
            ->order('scheduled_at DESC')
            ->limit($limit, $offset)
            ->fetchAll();

        $msgs = [];
        foreach ($rows as $row) {
            $msgs[] = $this->toNotificationMsg($row);
        }
        return $msgs;
    }

    //
    // Validation / Approval
    //

    public function getCountToApprove(): int
    {
        return $this->database->table('notification_msg')
            ->where('status', NotificationMsgStatus::Draft ->value)
            ->where('media_type', MediaType::Text->value)
            ->count('*');
    }

    /** @return NotificationMsg[] */
    public function getToApprove(int $limit, int $offset): array
    {
        $rows = $this->database->table('notification_msg')
            ->where('status', NotificationMsgStatus::Draft ->value)
            ->where('media_type', MediaType::Text->value)
            ->order('scheduled_at ASC')
            ->limit($limit, $offset)
            ->fetchAll();

        return $this->rowsToNotificationMsgs($rows);
    }

    public function updateText(int $id, string $text): void
    {
        $this->database->table('notification_msg')
            ->where('id', $id)
            ->update(['text' => $text]);
        Debugger::log("Updated Notification($id). New text: $text.");
    }

    public function rescheduleAt(int $id, \DateTime $scheduledAt): void
    {
        $this->database->table('notification_msg')
            ->where('id', $id)
            ->update([
                'scheduled_at' => DateUtils::baToUtc($scheduledAt),
                'status' => NotificationMsgStatus::Scheduled->value
            ]);
        Debugger::log("Rescheduled Notification($id) for: " . $scheduledAt->format('Y-m-d H:i:s'));
    }

    public function updateStatus(int $id, NotificationMsgStatus $status): void
    {
        $this->database->table('notification_msg')
            ->where('id', $id)
            ->update(['status' => $status->value]);
    }

    public function delete(int $id): void
    {
        $attempts = $this->database->table('notification_attempt')
            ->where('notification_msg_id', $id)
            ->delete();

        $this->database->table('notification_msg')
            ->where('id', $id)
            ->delete();

        Debugger::log("Deleted Notification($id). Attempts deleted: $attempts");
    }

    public function getById(int $id): ?NotificationMsg
    {
        $row = $this->database->table('notification_msg')->get($id);
        return $row ? self::toNotificationMsg($row) : null;
    }

    public function approveNotification(int $id): void
    {
        // Approve only the first available message to ensure sequential processing
        $this->database->table('notification_msg')
            ->where('id', $id)
            ->where('status', NotificationMsgStatus::Draft ->value)
            ->update(['status' => NotificationMsgStatus::Scheduled->value]);

        Debugger::log("Approved Notification($id)");
    }

    public function withdrawNotification(int $id): void
    {
        $this->database->table('notification_msg')
            ->where('id', $id)
            ->where('status', NotificationMsgStatus::Scheduled->value)
            ->update(['status' => NotificationMsgStatus::Draft->value]);
        Debugger::log("Withdrawn Notification($id)");
    }

    //
    // Scheduled
    //

    public function getCountScheduled(): int
    {
        return $this->getCountByStatus(NotificationMsgStatus::Scheduled);
    }

    public function getCountScheduledOrSent(): int
    {
        return $this->database->table('notification_msg')
            ->where('status IN ?', AppUtils::toEnumValues([NotificationMsgStatus::Scheduled, NotificationMsgStatus::Sent]))
            ->count('*');
    }

    public function getCountFailed(): int
    {
        return $this->getCountByStatus(NotificationMsgStatus::Failed);
    }

    /** @return NotificationMsg[] */
    public function getScheduled(int $limit, int $offset): array
    {
        $rows = $this->database->table('notification_msg')
            ->where('status', NotificationMsgStatus::Scheduled->value)
            ->order('scheduled_at ASC')
            ->limit($limit, $offset)
            ->fetchAll();

        return self::rowsToNotificationMsgs($rows);
    }

    /** @return NotificationMsg[] */
    public function getScheduledOrSent(int $limit, int $offset): array
    {
        $rows = $this->database->table('notification_msg')
            ->where('status IN ?', AppUtils::toEnumValues([NotificationMsgStatus::Scheduled, NotificationMsgStatus::Sent]))
            ->order('scheduled_at ASC')
            ->limit($limit, $offset)
            ->fetchAll();

        return self::rowsToNotificationMsgs($rows);
    }

    /** @return NotificationMsg[] */
    public function getFailed(int $limit, int $offset): array
    {
        $rows = $this->database->table('notification_msg')
            ->where('status', NotificationMsgStatus::Failed->value)
            ->order('updated_at DESC')
            ->limit($limit, $offset)
            ->fetchAll();

        return self::rowsToNotificationMsgs($rows);
    }

    /** @return NotificationMsg[] */
    public function listEligibleForSending(): array
    {
        $rows = $this->database->table('notification_msg')
            ->where('status', NotificationMsgStatus::Scheduled->value)
            ->where('scheduled_at <= NOW()')
            ->order('scheduled_at ASC')
            ->fetchAll();

        return self::rowsToNotificationMsgs($rows);
    }

    //
    // Find next messages
    //

    /** @return NotificationMsg[] */
    public function findNextMessages(NotificationMsg $prevMessage): array
    {
        $rows = $this->database->table('notification_msg')
            ->where('event_id', $prevMessage->eventId)
            ->where('msg_index', $prevMessage->msgIndex + 1)
            ->fetchAll();

        return self::rowsToNotificationMsgs($rows);
    }

    /** @return NotificationMsg[] */
    public function getMainTextMessage(int $eventId): ?NotificationMsg
    {
        $row = $this->database->table('notification_msg')
            ->where('event_id', $eventId)
            ->where('notification_type', NotificationType::Main->value)
            ->where('media_type', MediaType::Text->value)
            ->fetch();

        return $row ? self::toNotificationMsg($row) : null;
    }

    public function existsByEventIdAndFilePath(int $eventId, string $filePath): bool
    {
        return $this->database->table('notification_msg')
            ->where('event_id', $eventId)
            ->where('file_path', $filePath)
            ->count('*') > 0;
    }

    public function getAllByEventId(int $eventId): array
    {
        $rows = $this->database->table('notification_msg')
            ->where('event_id', $eventId)
            ->order('msg_index ASC')
            ->fetchAll();

        return self::rowsToNotificationMsgs($rows);
    }


    //
    // Helpers
    //

    private static function rowsToNotificationMsgs(array $rows): array
    {
        $msgs = [];
        foreach ($rows as $row) {
            $msgs[] = self::toNotificationMsg($row);
        }
        return $msgs;
    }

    public static function toNotificationMsg(ActiveRow|IRow $row): NotificationMsg
    {
        return new NotificationMsg(
            eventId: $row->event_id,
            msgIndex: $row->msg_index,
            mediaType: MediaType::from($row->media_type),
            notificationType: NotificationType::from($row->notification_type),
            status: NotificationMsgStatus::from($row->status),
            text: $row->text,
            filePath: $row->file_path,
            scheduledAt: DateUtils::utcToBa($row->scheduled_at),
            approvedAt: $row->approved_at ? DateUtils::utcToBa($row->approved_at) : null,
            id: $row->id,
            createdAt: DateUtils::utcToBa($row->created_at),
            updatedAt: DateUtils::utcToBa($row->updated_at),
        );
    }

    private function getCountByStatus(NotificationMsgStatus $status): int
    {
        return $this->database->table('notification_msg')
            ->where('status', $status->value)
            ->count('*');
    }

}
