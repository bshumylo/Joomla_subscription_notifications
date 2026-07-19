<?php

/**
 * Install script: creates the subscribers table and migrates the legacy
 * subscribers.txt file (v1.x) into the database.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

// phpcs:ignore
class mod_newsletter_subscriptionInstallerScript
{
    public function postflight(string $type, $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__newsletter_subscribers') . ' ('
            . $db->quoteName('id') . ' int unsigned NOT NULL AUTO_INCREMENT, '
            . $db->quoteName('email') . ' varchar(190) NOT NULL, '
            . $db->quoteName('created') . ' datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'PRIMARY KEY (' . $db->quoteName('id') . '), '
            . 'UNIQUE KEY ' . $db->quoteName('idx_email') . ' (' . $db->quoteName('email') . ')'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        )->execute();

        $this->migrateLegacyFile($db);

        return true;
    }

    private function migrateLegacyFile(DatabaseInterface $db): void
    {
        $legacy = JPATH_SITE . '/modules/mod_newsletter_subscription/subscribers.txt';

        if (!is_file($legacy)) {
            return;
        }

        $imported = 0;

        foreach (file($legacy, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $email = trim($line);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__newsletter_subscribers'))
                    ->columns([$db->quoteName('email')])
                    ->values(':email')
                    ->bind(':email', $email);
                $db->setQuery($query)->execute();
                $imported++;
            } catch (\RuntimeException $e) {
                // Duplicate — already migrated.
            }
        }

        @unlink($legacy);

        Log::add(
            sprintf('mod_newsletter_subscription: migrated %d subscriber(s) from subscribers.txt', $imported),
            Log::INFO,
            'mod_newsletter_subscription'
        );
    }
}
