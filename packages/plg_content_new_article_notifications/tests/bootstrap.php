<?php

/**
 * PHPUnit bootstrap: boots the Joomla framework inside the test container
 * and loads the plugin class.
 */

\defined('_JEXEC') || \define('_JEXEC', 1);

$joomlaRoot = getenv('JOOMLA_ROOT') ?: '/var/www/joomla';

if (!\defined('JPATH_BASE')) {
    \define('JPATH_BASE', $joomlaRoot);
}

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

require_once __DIR__ . '/../src/Extension/NewArticleNotifications.php';
