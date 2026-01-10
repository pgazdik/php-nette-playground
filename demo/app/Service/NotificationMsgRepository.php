<?php
namespace App\Service;

use App\Model\Entity\Event\MediaType;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\NotificationType;
use App\Utils\DateUtils;
use Nette\Database\Explorer;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;

class NotificationMsgRepository
{
    public function __construct(
        private Explorer $database
    ) {
    }

    public function create(NotificationMsg $msg): void
    {
        $row = $this->database->table('notification_msg')->insert([
            'event_id' => $msg->eventId,
            'msg_index' => $msg->msgIndex,
            'notification_type' => $msg->notificationType->value,
            'media_type' => $msg->mediaType->value,
            'status' => $msg->status->value,
            'text' => $msg->text,
            'send_at' => DateUtils::baToUtc($msg->sendAt),
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
            ->order('send_at DESC')
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
            ->where('status', NotificationMsgStatus::New ->value)
            ->where('media_type', MediaType::Text->value)
            ->count('*');
    }

    /** @return NotificationMsg[] */
    public function getToApprove(int $limit, int $offset): array
    {
        $rows = $this->database->table('notification_msg')
            ->where('status', NotificationMsgStatus::New ->value)
            ->where('media_type', MediaType::Text->value)
            ->order('send_at ASC')
            ->limit($limit, $offset)
            ->fetchAll();

        return $this->rowsToNotificationMsgs($rows);
    }

    public function updateText(int $id, string $text): void
    {
        $this->database->table('notification_msg')
            ->where('id', $id)
            ->update(['text' => $text]);
    }

    public function updateStatus(int $id, NotificationMsgStatus $status): void
    {
        $this->database->table('notification_msg')
            ->where('id', $id)
            ->update(['status' => $status->value]);
    }

    public function getById(int $id): ?NotificationMsg
    {
        $row = $this->database->table('notification_msg')->get($id);
        return $row ? self::toNotificationMsg($row) : null;
    }

    public function approveNotificationsForEvent(int $eventId): void
    {
        $this->database->table('notification_msg')
            ->where('event_id', $eventId)
            ->where('status', NotificationMsgStatus::New ->value)
            ->update(['status' => NotificationMsgStatus::Scheduled->value]);
    }

    //
    // Scheduled
    //

    public function getCountScheduled(): int
    {
        return $this->getCountByStatus(NotificationMsgStatus::Scheduled);
    }

    /** @return NotificationMsg[] */
    public function getScheduled(int $limit, int $offset): array
    {
        $rows = $this->database->table('notification_msg')
            ->where('status', NotificationMsgStatus::Scheduled->value)
            ->order('send_at ASC')
            ->limit($limit, $offset)
            ->fetchAll();

        return self::rowsToNotificationMsgs($rows);
    }

    //
    // Find next message
    //

    public function findNextMessage(NotificationMsg $prevMessage): ?NotificationMsg
    {
        $row = $this->database->table('notification_msg')
            ->where('event_id', $prevMessage->eventId)
            ->where('msg_index', $prevMessage->msgIndex + 1)
            ->fetch();

        return $row ? self::toNotificationMsg($row) : null;
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
            sendAt: DateUtils::utcToBa($row->send_at),
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
