<?php
namespace App\Service;

use DateTimeZone;

use App\Model\Entity\Event;
use App\Utils\DateUtils;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class EventService
{
    public function __construct(
        private Explorer $database
    ) {
    }

    public function create(Event $event): void
    {
        $this->database->table('event')->insert([
            'patient_name' => $event->patientName,
            'phone_number' => $event->phoneNumber,
            'doctor_name' => $event->doctorName,
            'doctor_address' => $event->doctorAddress,
            'appointment_date' => DateUtils::baToUtc($event->appointmentDate),
            'attachment_content' => $event->attachmentContent,
            'attachment_name' => $event->attachmentName,
            'attachment_type' => $event->attachmentType
        ]);
    }

    /**
     * @return Event[]
     */
    public function getAll(): array
    {
        $rows = $this->database->table('event')
            ->select('id, patient_name, phone_number, doctor_name, doctor_address, appointment_date, attachment_name, attachment_type, created_at')
            ->order('appointment_date ASC')
            ->fetchAll();

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->hydrate($row);
        }
        return $events;
    }

    private function hydrate(ActiveRow $row): Event
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
            createdAt: DateUtils::utcToBa($row->created_at)
        );
    }
}
