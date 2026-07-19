# Joomla Subscription and Notifications Package

A set of two Joomla! extensions — a **Newsletter Subscription Module** and a **New Article Notifications Plugin** — that manage news subscriptions and automatically announce new articles.

**Version 2.0.0 targets Joomla 6.x** (namespaced extensions, DI service providers, `SubscriberInterface` plugin events). For the legacy Joomla 3 version see the [1.0.0 tag / original upstream](https://github.com/AntoninaZz/pkg_subscription_and_notifications).

## Features

* **Newsletter Subscription Module** (`mod_newsletter_subscription`)
  * Visitors subscribe/unsubscribe to news via email; optional link to a Telegram channel.
  * Subscribers are stored in a database table (`#__newsletter_subscribers`) — the insecure `subscribers.txt` from v1 is migrated automatically on update and removed.
  * Sends a customizable welcome email (via the Joomla mailer).
  * CSRF-protected form, POST/redirect/GET flow, translated UI (en-GB, uk-UA).

* **New Article Notifications Plugin** (`plg_content_new_article_notifications`)
  * Announces new **published** articles from selected categories via:
    * **Email** — BCC batches to all subscribers (configurable batch size);
    * **Telegram** — bot API, with cover image and an inline "Details" button;
    * **Mattermost** — incoming webhook, Markdown message with title link, intro text and cover image attachment.
  * All send failures are logged (System → Logs), never break article saving.

## Installation

1. Run `./build.sh` (or download the release zip) to get `dist/pkg_subscription_and_notifications-2.0.0.zip`.
2. Install it via **System → Install → Extensions**.
3. Publish the **Newsletter Subscription** module and configure the **Content - New Article Notifications** plugin (enabled automatically on install).

## Configuration

* **Module:** enable email and/or Telegram subscription, welcome email subject/message, Telegram channel link.
* **Plugin:**
  * Email: newsletter name, max BCC recipients per email.
  * Telegram: bot token, channel (`@channel`). The bot must be an administrator of the channel.
  * Mattermost: incoming webhook URL (Mattermost → Integrations → Incoming Webhooks → Add Incoming Webhook).
  * Categories whose new articles trigger notifications.

Notifications are sent only for articles **created in the published state** in the selected categories.

## Development

* Sources live under `packages/`; `build.sh` produces the installable package in `dist/`.
* PHPUnit tests: `packages/plg_content_new_article_notifications/tests/` (run inside a Joomla container, `JOOMLA_ROOT` env points to the Joomla root).

## License

GPL-3.0 — see [LICENSE](LICENSE).
