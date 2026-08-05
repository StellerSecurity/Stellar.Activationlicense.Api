# Stellar Activation License API

This Laravel API stores and activates subscription license codes for Stellar products.

## Supported license types

| Value | Type |
| --- | --- |
| `0` | Antivirus |
| `1` | VPN |
| `2` | Protect |

## License statuses

| Value | Status | Meaning |
| --- | --- | --- |
| `0` | Inactive | The license exists but cannot be used. |
| `1` | Active | The license can be verified or activated. |
| `2` | Activated | The license has already been redeemed. |

## Create activation licenses

`POST /api/v1/activationlicensecontroller/create`

This endpoint is protected with HTTP Basic Authentication. Configure the credentials through `API_USERNAME` and `API_PASSWORD`.

Only expose this endpoint over HTTPS in production. The response contains redeemable license codes and is returned with `Cache-Control: no-store`.

### Request fields

| Field | Required | Description |
| --- | --- | --- |
| `type` | Yes | License type: `0`, `1`, or `2`. |
| `subscriptions_days` | Yes | Number of subscription days. Must be at least `1`. |
| `status` | No | Initial status: `0` or `1`. Defaults to `1`. |
| `quantity` | No | Number of licenses to create. Defaults to `1`; maximum defaults to `100`. |
| `code` | No | A custom code for a single license. It is trimmed and converted to uppercase. |
| `prefix` | No | Prefix used for generated codes. Defaults to `STELLAR`. |

A custom `code` cannot be combined with `prefix`, and `quantity` must be `1` when a custom code is supplied.

### Create one generated license

```bash
curl --request POST \
  --user "${API_USERNAME}:${API_PASSWORD}" \
  --header "Content-Type: application/json" \
  --data '{
    "type": 1,
    "subscriptions_days": 30
  }' \
  http://127.0.0.1:8000/api/v1/activationlicensecontroller/create
```

Example response:

```json
{
  "response_code": 201,
  "response_message": "Activation license created.",
  "count": 1,
  "licenses": [
    {
      "id": 1,
      "code": "STELLAR-7K4M-W9QX-3DPR-T6HN",
      "status": 1,
      "type": 1,
      "subscriptions_days": 30,
      "created_at": "2026-08-05T17:00:00.000000Z",
      "updated_at": "2026-08-05T17:00:00.000000Z"
    }
  ]
}
```

### Create a batch

```bash
curl --request POST \
  --user "${API_USERNAME}:${API_PASSWORD}" \
  --header "Content-Type: application/json" \
  --data '{
    "type": 0,
    "subscriptions_days": 365,
    "quantity": 10,
    "prefix": "AV"
  }' \
  http://127.0.0.1:8000/api/v1/activationlicensecontroller/create
```

### Create a license with a custom code

```bash
curl --request POST \
  --user "${API_USERNAME}:${API_PASSWORD}" \
  --header "Content-Type: application/json" \
  --data '{
    "type": 2,
    "subscriptions_days": 90,
    "code": "RESELLER-2026-0001"
  }' \
  http://127.0.0.1:8000/api/v1/activationlicensecontroller/create
```

The API returns `409 Conflict` when the custom code already exists for the same license type and `422 Unprocessable Content` when validation fails.

## Existing endpoints

- `POST /api/v1/activationlicensecontroller/verify`
- `POST /api/v1/activationlicensecontroller/activate`
- `GET /api/v1/activationlicensecontroller/activate`

## Install and run locally

Requirements: PHP 8.1 or newer, Composer, and MySQL or SQLite.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database and secure API credentials in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stellar_activation_license
DB_USERNAME=root
DB_PASSWORD=

API_USERNAME=replace-with-a-secure-username
API_PASSWORD=replace-with-a-long-random-password
ACTIVATION_LICENSE_CODE_PREFIX=STELLAR
ACTIVATION_LICENSE_MAX_BATCH_SIZE=100
```

Run the migrations and start the development server:

```bash
php artisan migrate
php artisan serve
```

The local API is then available at `http://127.0.0.1:8000`.

## Run tests

The test suite uses an in-memory SQLite database.

```bash
composer install
php artisan test
```

## Production optimization

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan config:cache
php artisan view:cache
php artisan migrate --force
```
