# Configuration

Every setting lives in `config/gemboot.php`, and every value can be set through an environment variable. Usually you only edit `.env`.

Publishing the file is optional. Gemboot merges its defaults, so a published file only needs the keys you want to change:

```sh
php artisan vendor:publish --tag=gemboot
```

## Auth service

| Key | Env variable | Default | Purpose |
|---|---|---|---|
| `auth.base_api` | `GEMBOOT_AUTH_BASE_API` | none (required) | Base URL of the auth service's endpoints (`me`, `has-role`, ...) |
| `auth.cache_ttl` | `GEMBOOT_AUTH_CACHE_TTL` | `0` (off) | Seconds to cache the auth service's answers per token ([details](AUTH.md#caching-auth-answers)) |

`auth.base_url` and `auth.fallback.*` exist in the file but aren't used. They'll be removed in 9.0.

## Calls to the auth service

| Key | Env variable | Default | Purpose |
|---|---|---|---|
| `http.verify` | `GEMBOOT_HTTP_VERIFY` | `true` | TLS certificate check: `true`, `false` (local development only), or the path to a CA bundle |
| `http.timeout` | `GEMBOOT_HTTP_TIMEOUT` | `30` | Seconds to wait for an answer |
| `http.connect_timeout` | `GEMBOOT_HTTP_CONNECT_TIMEOUT` | `10` | Seconds to wait for a connection |

## SSO guard

| Key | Env variable | Default | Purpose |
|---|---|---|---|
| `sso.user_service_url` | `GEMBOOT_USER_SERVICE_URL` | none | The guard calls `{url}/user/me` |
| `sso.get_user_url` | `GEMBOOT_SSO_GET_USER_URL` | none | Full URL instead of the above |
| `sso.fallback.user_service_url` | `GEMBOOT_USER_SERVICE_URL_FALLBACK` | none | Second user service |
| `sso.fallback.get_user_url` | `GEMBOOT_SSO_GET_USER_URL_FALLBACK` | none | Full URL of the second user service |
| `sso.cache_ttl` | `GEMBOOT_SSO_CACHE_TTL` | `300` | Seconds to cache a user per token |

See [The SSO guard](AUTH.md#the-sso-guard).

## Lists

| Key | Env variable | Default | Purpose |
|---|---|---|---|
| `pagination.max_page_len` | `GEMBOOT_MAX_PAGE_LEN` | `1000` | Upper limit for `?page_len`; `null` removes the limit |

## Responses

| Key | Env variable | Default | Purpose |
|---|---|---|---|
| `response.security_headers` | `GEMBOOT_SECURITY_HEADERS` | `Cache-Control: no-store`, `X-Content-Type-Options: nosniff` | Headers on every Gemboot JSON response. The env variable turns them on or off; publish the config to change the list ([details](RESPONSES.md#security-headers)). |
| `response.send_header_error` | `GEMBOOT_SEND_HEADER_ERROR` | `true` | Adds an `x-gemboot-error-message` header to `responseError()` responses |
| `response.compressed` | `GEMBOOT_RESPONSE_COMPRESSED` | `false` | **Deprecated, removed in 9.0.** gzip inside PHP; let your web server compress instead |

## Error alerts

| Key | Env variable | Default | Purpose |
|---|---|---|---|
| `notifications.telegram.token` | `GEMBOOT_TELEGRAM_BOT_TOKEN` | none | Telegram bot token. Alerts are off while empty. |
| `notifications.telegram.chat_id` | `GEMBOOT_TELEGRAM_CHAT_ID` | none | Chat that receives a message for every unexpected 500 |
| `notifications.enable` | `GEMBOOT_NOTIFICATIONS_ENABLE` | `true` | Used by the `Gemboot\Notifications\Telegram` notification class |

## Other services

| Key | Env variable | Default | Purpose |
|---|---|---|---|
| `file_handler.base_url` | `GEMBOOT_FILE_HANDLER_BASE_URL` | none | File service used by [`FileHandler`](FILE_HANDLER.md) |
| `gateway.base_url`, `gateway.base_url_auth` | `GEMBOOT_GW_BASE_URL`, `GEMBOOT_GW_BASE_URL_AUTH` | none | Only for the deprecated `CheckToken` middleware |

## Checking the configuration

`php artisan gemboot:doctor` checks these settings and explains problems ([Commands](COMMANDS.md#gembootdoctor)).

## After `php artisan config:cache`

Gemboot reads every setting through `config()`, so cached configuration works. Remember to run `php artisan config:cache` again after changing `.env` in production.
