<?php

/**
 * Package install script.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

// phpcs:ignore
class pkg_subscription_and_notificationsInstallerScript
{
    /**
     * Enable the notifications plugin after install so it works out of the box
     * (it does nothing until configured).
     */
    public function postflight(string $type, $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('new_article_notifications'));
        $db->setQuery($query)->execute();

        return true;
    }
}
