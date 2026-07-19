<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Joomla\CMS\Application\SiteApplication $app */
/** @var \Joomla\Registry\Registry $params */

$app->getDocument()->getWebAssetManager()
    ->registerAndUseStyle('mod_newsletter_subscription', 'modules/mod_newsletter_subscription/css/style.css');

$emailOn  = (int) $params->get('email', 0) === 1;
$tgOn     = (int) $params->get('tg', 0) === 1;
$tgLink   = trim((string) $params->get('link', ''));
$tgShown  = $tgOn && $tgLink !== '';
$moduleId = 'toggle-' . (int) $module->id;
?>
<div class="newsletter_subscription">
    <input type="checkbox" id="<?php echo $moduleId; ?>">
    <label for="<?php echo $moduleId; ?>" class="btn btn-primary w-100"><?php echo Text::_('MOD_NEWSLETTER_SUBSCRIPTION_TOGGLE'); ?></label>
    <div class="wrapper">
        <label for="<?php echo $moduleId; ?>"><span class="cancel-icon icon-cancel-2" aria-hidden="true"></span></label>
        <div class="icon"><span class="icon-mail" aria-hidden="true"></span></div>
        <div class="content">
            <header><?php echo Text::_('MOD_NEWSLETTER_SUBSCRIPTION_TOGGLE'); ?></header>
            <?php if ($emailOn) : ?>
                <p><?php echo Text::_('MOD_NEWSLETTER_SUBSCRIPTION_INTRO'); ?></p>
            <?php endif; ?>
        </div>
        <?php if ($emailOn) : ?>
            <form action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" method="post">
                <div class="field">
                    <input type="email" class="email" name="email"
                           placeholder="<?php echo Text::_('MOD_NEWSLETTER_SUBSCRIPTION_EMAIL_PLACEHOLDER'); ?>" required>
                </div>
                <div class="field">
                    <button type="submit" name="subscribe" value="1" class="custom-button">
                        <span class="icon-mail" aria-hidden="true"></span>
                        <?php echo Text::_('MOD_NEWSLETTER_SUBSCRIPTION_SUBSCRIBE'); ?>
                    </button>
                    <button type="submit" name="unsubscribe" value="1" class="link">
                        <?php echo Text::_('MOD_NEWSLETTER_SUBSCRIPTION_UNSUBSCRIBE'); ?>
                    </button>
                </div>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        <?php endif; ?>
        <?php if ($tgShown) : ?>
            <div class="mar-bot">
                <?php echo Text::_($emailOn ? 'MOD_NEWSLETTER_SUBSCRIPTION_TG_PROMPT_OR' : 'MOD_NEWSLETTER_SUBSCRIPTION_TG_PROMPT'); ?>
            </div>
            <a class="custom-button" href="<?php echo htmlspecialchars($tgLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                <svg class="tg-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                <?php echo Text::_('MOD_NEWSLETTER_SUBSCRIPTION_SUBSCRIBE'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>
