# SMS Gateway Demo

## Prerequisites

- Git installed
- Docker installed
- Docker is running

## Installation

Clone this repository, e.g.:
```bash
git clone https://github.com/gazdik/php-nette-playground.git
```

Navigate to the root:
```bash
cd php-nette-playground
```

Build php-fpm image:
```bash
docker compose build php-fpm
```

Start the containers (in attached mode, i.e. stopping via Ctrl+C):
```bash
docker compose up
```
(No desire to go through start/stop/up/down differences now).

Install dependencies:
```bash
docker compose exec -w /application/demo php-fpm composer install
```

If it works, the output should look like this:
```
Verifying lock file contents can be installed on current platform.
Package operations: 47 installs, 0 updates, 0 removals
  - Downloading symfony/thanks (v1.4.0)
  - Downloading latte/latte (v3.0.20)
  - Downloading nette/utils (v4.0.5)
...
 45/46 [===========================>]  97%
 46/46 [============================] 100%
Generating autoload files
24 packages you are using are looking for funding.
Use the `composer fund` command to find out more!
```

Verify Installation:
Check that there is a new folder `./demo/vendor`, with a content like this:
```
bin/
cmposer/
dibi/
...
trac/
autoload.php
```


## Usage

We are assuming your project is running, i.e. you called `docker compose up` and never stopped it.

Open: http://localhost:41000/sms

Should see (with no data):
![screenshot](sms-screen.png)


### Configure the Gateway

First, enter `SMS Gateway URL` and `API Token` in the form ON THE LEFT and click `Save to Session`.

It's IMPORTANT to enter `https://` with the IP address.


### Send some SMS

Enter a number in the format `+4219xyz`\
Enter SMS text.\
Click `Create` to save the message to the database (nothing is sent yet).

Send the message with the green `Send` button.

Wait (hopefully only) a few seconds:\
Check the status with the blue `Check` button. \
If it works, it updates the `Check St`, `Send Date` and other columns on the right side.

WARNING: First few seconds after pressing `Send`, it's still not sent to the phone by the Gateway, so you'might see an error as a result of checking (for now, will be supported later).

That's it.


----

## Testing the Gateway via CLI

(Just in case....)

### Send Single SMS

Doc: https://www.smseagle.eu/docs/apiv2/#tag/Send-messages/operation/Messages::sms_single_post

```bash
curl -H "Content-Type: application/json" \
     -H "access-token: $TOKEN" \
     -X POST \
     --insecure \
     -d '{ 
            "to":"+421xyz", 
            "text":"Hello there", 
            "encoding":"unicode", 
            "flash":false, 
            "validity":"max", 
            "test":false
        }' \
     https://$SMS_GATEWAY_URL/index.php/api/v2/messages/sms_single
```

```json
[{
    "status": "queued",
    "message": "Hello there",
    "number": "+421xyz",
    "id": 654
}]
```

### Read Sent SMS

Doc: https://www.smseagle.eu/docs/apiv2/#tag/Read-messages/operation/Messages::messages_get

ID is the id from the response when sending (e.g. `654`)

```bash
curl -H "Content-Type: application/json" \
     -H "access-token: $TOKEN" \
     -X GET \
     --insecure \
     "https://$SMS_GATEWAY_URL/index.php/api/v2/messages/sent?id_from=$ID&id_to=$ID"
```

```json
[{
    "id": 654,
    "number": "+421xyz",
    "text": "Hello there",
    "text_binary": "00....",
    "attachments": null,
    "udh": null,
    "smsc": "+421def",
    "class": 255,
    "encoding": "unicode",
    "folder_id": 3,
    "validity": "max",
    "sender_name": "remote",
    "modem_no": 1,
    "status": "delivery_ok",
    "error_code": null,
    "update_date": "2025-06-22T18:05:43+02:00",
    "insert_date": "2025-06-22T18:04:16+02:00",
    "sending_date": "2025-06-22T18:04:21+02:00",
    "delivery_date": "2025-06-22T18:05:42+02:00"
}]
```

Error codes: https://www.smseagle.eu/learn/cms-errors-explained/

Statuses:
- sending_ok_no_report
- sending_ok
- delivery_ok
- delivery_pending
- delivery_unknown
- delivery_failed
- sending_error
- reserved
- error

