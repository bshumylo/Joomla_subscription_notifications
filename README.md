# Subscribe &amp; Notify — Newsletter Subscription and Article Notifications for Joomla

A package of two Joomla! extensions — a **newsletter subscription module** and a **new article notifications plugin** — that let visitors subscribe to your news and automatically announce every newly published article by **email, Telegram and Mattermost**.

**Version 2.x targets Joomla 6.x** (namespaced extensions, DI service providers, `SubscriberInterface` plugin events). For the legacy Joomla 3 version see the [original upstream project](https://github.com/AntoninaZz/pkg_subscription_and_notifications).

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

1. Download `pkg_subscription_and_notifications-x.y.z.zip` from the [latest release](../../releases/latest), or build it yourself with `./build.sh`.
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
* Local Docker test harness: `docker-test/` (`RUN_TESTS.bat` on Windows).
* Releases are automated: pushing a `vX.Y.Z` tag builds the package and attaches the zip to a GitHub Release. The tag must match `<version>` in `pkg_subscription_and_notifications.xml`. Build artifacts are never committed.

## Credits

This project is a fork of [pkg_subscription_and_notifications](https://github.com/AntoninaZz/pkg_subscription_and_notifications) by **Antonina Zdebska**, originally released in 2022 under the GPL-3.0.

Version 2.x is an extensive rework by **bshumylo**: port to Joomla 6, namespaced extensions and DI service providers, database-backed subscriber storage replacing the plaintext file, CSRF protection, Mattermost support, translations and an automated test and release pipeline. The original design and functionality remain the work of the original author.

## License

GPL-3.0-or-later — see [LICENSE](LICENSE).

Copyright (C) 2022-2025 Antonina Zdebska
Copyright (C) 2026 bshumylo

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
