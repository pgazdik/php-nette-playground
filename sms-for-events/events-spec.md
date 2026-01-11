# Events

I have a simple demo app with Nette and MySQL. The main folder is 'demo/', tests are under `tests/`, sql migration scripts for nextras migrations are in `app/migrations/structure` with names like `2025-12-31-18-00-00_create_my_table.sql`.


I have one feature for sending SMS messages. It contains an entity called `SmsMsg`, an `SmsPresenter` with a template `default.latte`. The web page has two panels, the left panel is for configuring the SMS gateway, the right panel has a form for creating a new SMS message and shows existing messages in a table underneath. SMS messages are created and when displayed in a list a "Send" button is available. When clicked, the SMS is sent to the SMS gateway and the status is updated.

Now, I want to create a new separate feature for Events. It will also have two panels, the panel on the right is again for configuring the SMS gateway. But the right panel is for creating Events. An Event has the following attributes: 

* patientName - text
* phoneNumber - text
* doctorName - text
* doctorAddress - text
* appointmentDate - timestamp
* attachment - image / blob

Please create an Event entity, a migration script for the Event table and a presenter for creating and listing events.


class EventManager {
    public function __construct(
        private EventService $events,
        private NotificationService $notifications
    ) {}

    public function createFullEvent(array $data) {
        $event = $this->events->create($data);
        $this->notifications->createNotification($event);
        return $event;
    }
}

Tests:
> docker compose exec -w //application/demo php-fpm ./vendor/bin/phpunit tests --filter EventServiceTest --testdox

Access ENVs

```php
$this->logInfo("SMS_GW_URL: " . getenv('SMS_GW_URL'));
$this->logInfo("SMS_GW_TOKEN: " . getenv('SMS_GW_TOKEN'));
```

I am working on a PHP app using Nette framework.

This is a PHP app using Nette framework. Let's focus on the Event part, there is code to create Events, now I want to extend it with notifications. I have already created Entities in the app/Model/Event. First step, extend the migration script that already exists and create tables for the new entities.