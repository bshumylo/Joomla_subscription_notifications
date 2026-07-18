# Joomla Subscription and Notifications Package

## Overview

The **Joomla Subscription and Notifications Package** is a set of two extensions—a **Newsletter Subscription Module** and a **New Article Notifications Plugin**—that work together to manage email and Telegram subscriptions and automate notifications about new articles.

## Features

* **Newsletter Subscription Module**

  * Allows users to subscribe to news via email and/or Telegram.
  * Displays a "Subscribe" button that opens a pop-up form.
  * Manages email subscriptions via a text file stored in the module directory.
  * Sends a welcome email to new subscribers.
* **New Article Notifications Plugin**

  * Automatically sends notifications via email and/or Telegram when a new article is published.
  * Uses the same subscription list as the module.
  * Telegram notifications include article title, summary, cover image (if available), and a link.
  * Email notifications are sent via BCC to all subscribers.

## Installation

1. Install the package via Joomla Extension Manager.
2. Enable both the **Newsletter Subscription Module** and the **New Article Notifications Plugin**.
3. Configure the module and plugin settings as needed.

## Configuration

* **Module Settings:**

  * Enable email/Telegram subscription options.
  * Customize the welcome email subject and message.
  * Provide a Telegram channel link.
* **Plugin Settings:**

  * Enable email/Telegram notifications.
  * Set a newsletter name for email subjects.
  * Define the maximum number of BCC recipients per email.
  * Provide the Telegram bot token and channel link.
  * Select article categories to trigger notifications.



&#x20;License AGPL-3.0 - [LICENCE](https://github.com/bshumylo/Joomla\_subscription\_notifications/blob/main/LICENSE)

