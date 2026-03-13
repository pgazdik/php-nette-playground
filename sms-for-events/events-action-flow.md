# Event and Notification Action Summary

The following table summarizes the lifecycle of Events and Notifications, including state transitions and side effects.

| Entity | Action | Initial State | Result State | Outcome / Side Effects |
| :--- | :--- | :--- | :--- | :--- |
| **Event** | `Create` | - | Draft | Automatically creates one or more `NotificationMsg` in **Draft** state. |
| - | - | - | - | - |
| **NotificationMsg** | `Approve` | Draft | Scheduled | First Msg marked eligible for sending. |
| **NotificationMsg** | `Withdraw` | Scheduled | Draft | Message is moved back to Draft and won't be sent. |
| **NotificationMsg** | `Send (Success)` | Scheduled | Sent | Request to SMS GW successful. `NotificationAttempt` created with status **Sent**. |
| **NotificationMsg** | `Send (GW Error)` | Scheduled | Scheduled | Request to SMS GW failed. `NotificationAttempt` created with error info; **Rescheduled**. |
| **NotificationMsg** | `Send (Data Error)` | Scheduled | Failed | Required data (e.g. Event) missing. `NotificationAttempt` created with error info. |
| - | - | - | - | - |
| **NotificationAttempt** | `Check (Delivered)` | Sent / Queued | Delivered | Delivery confirmed by GW. `NotificationMsg` status updated to **Delivered**. Next message in sequence is **Scheduled**. |
| **NotificationAttempt** | `Check (Failed)` | Sent / Queued | Failed | GW reported delivery failure. `NotificationMsg` status set to **Scheduled** and **Rescheduled**. |
| **NotificationAttempt** | `Check (Pending)` | Sent / Queued | Queued | GW reported message is still in transit/queued. |
| **NotificationAttempt** | `Check (Not Found)` | Sent / Queued | NotFound | GW does not recognize the message ID (resource not found). |
| **NotificationAttempt** | `Check (API Error)` | Sent / Queued | CheckError | Error during status check request (e.g. network issue). |


| Entity | Action | Handler Function |
| :--- | :--- | :--- |
| **Event** | Create Event | `EventManager::createEvent` |
| **NotificationMsg** | Approve Notification | `NotificationManager::approveNotification` |
| **NotificationMsg** | Withdraw Notification | `NotificationManager::withdrawNotification` |
| **NotificationMsg** | Send Notification | `NotificationManager::sendNotificationFor` |
| **NotificationAttempt** | Check Notification Attempt | `NotificationManager::checkNotificationStatus` |


## Create Event
Create 1 main and N image `Notification Msg` as **Draft**.



## Approve Notification
(main `Notification Msg` is **Draft**) \
Mark main `Notification Msg` as **Scheduled**.

## Withdraw
(main `Notification Msg` is **Scheduled**) \
Mark main `Notification Msg` as **Draft**.



## Send Notification
(`Notification Msg` is  **Scheduled**)\
Create `NotificationAttempt`, Set `Notification Msg` as **Sent**

**If data (e.g. Event by id) not found** \
Set `NotificationMsg` / `NotificationAttempt` **Failed**

**If HTTP response not Success** \
Set `NotificationAttempt` status to **Failed**\
Rechedule.

**Not valid JSON response** \
Set `NotificationAttempt` status to **Failed**\
Rechedule.

**Send was OK** \
Set `NotificationAttempt` status to **Sent**\



## Check Notification (Attemp)
(`Notification Attempt` has check supporting state (e.g. `Sent`, `Queued`, `NotFound`, `CheckError`))

**If HTTP response not Success** \
Do nothing, implying check again

**Not valid JSON response - not found** \
Set `Notification Attempt` as `NotFound`, and do nothing. Msg was not sent yet, check will happen again.

**Not valid JSON response - other** \
Set `Notification Attempt` as **CheckError**, log+store error msg, but do nothing so check will happen again.

**Valid JSON**

- sending_ok_no_report
- sending_ok
- delivery_ok
- delivery_pending
- delivery_unknown
- delivery_failed

Set `NotificationMsg` / `NotificationAttempt` **Delivered**, schedule next message

- sending_error

Set `Notification Attempt` as **Failed**, log+store error msg, but do nothing so check will happen again. \
Reschedule 2min, 5min, 10min, send mail

- error
    reschedule 2min, 5min, 10min, send mail

- reserved
 
 Set `Notification Attempt` as **CheckError**
 Check again 1min, 2min, 5min, send mail

