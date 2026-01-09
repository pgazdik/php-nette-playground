<?php
namespace App\Service;

use App\Model\Entity\Event\Event;
use App\Utils\DateUtils;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class EventRepository
{
    private static string $ALL_COLUMNS_EXCEPT_ATTACHMENT_CONTENT = 'id, patient_name, phone_number, doctor_name, doctor_address, appointment_date, attachment_name, attachment_type, created_at, updated_at';

    public function __construct(
        private Explorer $database
    ) {
    }

    public function getByIdWithImage(int $eventId): ?Event
    {
        $row = $this->database->table('event')->get($eventId);
        return $row ? $this->toEvent($row) : null;
    }

    public function getByIdNoImage(int $eventId): ?Event
    {
        $row = $this->database->table('event')
            ->select($this::$ALL_COLUMNS_EXCEPT_ATTACHMENT_CONTENT)
            ->get($eventId);
        return $row ? $this->toEvent($row) : null;
    }

    public function create(Event $event): int
    {
        $row = $this->database->table('event')->insert([
            'patient_name' => $event->patientName,
            'phone_number' => $event->phoneNumber,
            'doctor_name' => $event->doctorName,
            'doctor_address' => $event->doctorAddress,
            'appointment_date' => DateUtils::baToUtc($event->appointmentDate),
            'attachment_content' => $event->attachmentContent,
            'attachment_name' => $event->attachmentName,
            'attachment_type' => $event->attachmentType
        ]);

        return $row->id;
    }

    /**
     * @return Event[]
     */
    public function getAll(): array
    {
        $rows = $this->database->table('event')
            ->select($this::$ALL_COLUMNS_EXCEPT_ATTACHMENT_CONTENT)
            ->order('appointment_date ASC')
            ->fetchAll();

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->toEvent($row);
        }
        return $events;
    }

    private static function toEvent(ActiveRow $row): Event
    {
        return new Event(
            patientName: $row->patient_name,
            phoneNumber: $row->phone_number,
            doctorName: $row->doctor_name,
            doctorAddress: $row->doctor_address,
            appointmentDate: DateUtils::utcToBa($row->appointment_date),
            attachmentContent: $row->attachment_content ?? null,
            attachmentName: $row->attachment_name,
            attachmentType: $row->attachment_type,
            id: $row->id,
            createdAt: DateUtils::utcToBa($row->created_at),
            updatedAt: DateUtils::utcToBa($row->updated_at),
        );
    }
}
