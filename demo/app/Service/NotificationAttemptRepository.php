<?php
namespace App\Service;

use App\Model\Entity\Event\NotificationAttempt;
use App\Model\Entity\Event\NotificationAttemptStatus;
use App\Utils\DateUtils;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

use DateTime;

class NotificationAttemptRepository
{
    public function __construct(
        private Explorer $database
    ) {
    }

    //
    // Create
    //

    public function create(NotificationAttempt $attempt): void
    {
        $row = $this->database->table('notification_attempt')->insert([
            'notification_msg_id' => $attempt->notificationMsgId,
            'attempt_no' => $attempt->attemptNo,
            'send_at' => DateUtils::baToUtc($attempt->sendAt),
            'status' => $attempt->status->value,
            'sending_error' => $attempt->sendingError,
            'gw_id' => $attempt->gwId,
            'gw_send_status' => $attempt->gwSendStatus,
            'gw_check_status' => $attempt->gwCheckStatus,
            'gw_error_code' => $attempt->gwErrorCode,
            'gw_send_date' => $attempt->gwSendDate,
            'gw_delivery_date' => $attempt->gwDeliveryDate,
        ]);

        $attempt->id = $row->id;
    }

    public function update(
        NotificationAttempt $attempt,
        NotificationAttemptStatus $newStatus,
        string $gwCheckStatus,
        ?int $gwErrorCode,
        ?DateTime $gwSendDate,
        ?DateTime $gwDeliveryDate
    ): void {

        $this->database->table('notification_attempt')
            ->where('id', $attempt->id)
            ->update([
                'status' => $newStatus->value,
                'gw_check_status' => $gwCheckStatus,
                'gw_error_code' => $gwErrorCode,
                'gw_send_date' => $gwSendDate,
                'gw_delivery_date' => $gwDeliveryDate ? DateUtils::baToUtc($gwDeliveryDate) : $attempt->gwDeliveryDate,
            ]);

        // Update the attempt object to reflect the changes
        $attempt->status = $newStatus;
        $attempt->gwCheckStatus = $gwCheckStatus;
        $attempt->gwErrorCode = $gwErrorCode;
        $attempt->gwSendDate = $gwSendDate ?: $attempt->gwSendDate;
        $attempt->gwDeliveryDate = $gwDeliveryDate ?: $attempt->gwDeliveryDate;
    }

    //
    // Sending
    //

    /** @return NotificationAttempt[] */
    public function listToSend(): array
    {
        $rows = $this->database->table('notification_attempt')
            ->where('send_at <= NOW()')
            ->where('status = ?', NotificationAttemptStatus::Scheduled->value)
            ->fetchAll();

        return self::toNotificationAttempts($rows, true);
    }

    //
    // Checking
    //

    /** @return NotificationAttempt[] */
    public function listToCheck(): array
    {
        $rows = $this->database->table('notification_attempt')
            ->where('status = ?', NotificationAttemptStatus::Sent->value)
            ->fetchAll();

        return self::toNotificationAttempts($rows, true);
    }

    /** @return NotificationAttempt[] */
    public function listByMsgId(int $msgId): array
    {
        $rows = $this->database->table('notification_attempt')
            ->where('notification_msg_id', $msgId)
            ->order('attempt_no ASC')
            ->fetchAll();

        return self::toNotificationAttempts($rows, true);
    }

    /** @return NotificationAttempt[] */
    private static function toNotificationAttempts(iterable $rows, bool $withMsg): array
    {
        $attempts = [];
        foreach ($rows as $row) {
            $attempts[] = self::toNotificationAttempt($row, $withMsg);
        }
        return $attempts;
    }

    /** @return NotificationAttempt */
    private static function toNotificationAttempt(ActiveRow $row, bool $withMsg): NotificationAttempt
    {
        $result = new NotificationAttempt(
            id: $row->id,
            notificationMsgId: $row->notification_msg_id,
            attemptNo: $row->attempt_no,
            sendAt: DateUtils::utcToBa($row->send_at),
            status: NotificationAttemptStatus::from($row->status),
            sendingError: $row->sending_error,
            gwId: $row->gw_id,
            gwSendStatus: $row->gw_send_status,
            gwCheckStatus: $row->gw_check_status,
            gwErrorCode: $row->gw_error_code ? (int) $row->gw_error_code : null,
            gwSendDate: $row->gw_send_date,
            gwDeliveryDate: $row->gw_delivery_date,
            createdAt: DateUtils::utcToBa($row->created_at),
            updatedAt: DateUtils::utcToBa($row->updated_at)
        );

        if ($withMsg)
            $result->msg = NotificationMsgRepository::toNotificationMsg($row->notification_msg);

        return $result;
    }

    public function noteMessageSent(NotificationAttempt $attempt, int $gwId, string $gwStatus): void
    {
        $this->database->table('notification_attempt')
            ->where('id', $attempt->id)
            ->update([
                'status' => NotificationAttemptStatus::Sent->value,
                'gw_id' => $gwId,
                'gw_send_status' => $gwStatus,
            ]);

        // Update the attempt object to reflect the changes
        $attempt->status = NotificationAttemptStatus::Sent;
        $attempt->gwId = $gwId;
        $attempt->gwSendStatus = $gwStatus;
    }

    public function noteMessageSendErrorAndReschedule(NotificationAttempt $attempt, string $error): void
    {
        $this->database->table('notification_attempt')
            ->where('id', $attempt->id)
            ->update([
                'status' => NotificationAttemptStatus::Failed->value,
                'sending_error' => $error,
            ]);

        // Update the attempt object to reflect the changes
        $attempt->status = NotificationAttemptStatus::Failed;
        $attempt->sendingError = $error;
    }
}
