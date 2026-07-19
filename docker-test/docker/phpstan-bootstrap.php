<?php
// PHPStan autoload shim: framework.php dies without _JEXEC, so define it first.
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/joomla');
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';
