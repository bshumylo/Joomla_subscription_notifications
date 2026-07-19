<?php

namespace Zdebska\Module\NewsletterSubscription\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class NewsletterSubscriptionHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    public function subscribe(string $email, Registry $params, CMSApplicationInterface $app): void
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $app->enqueueMessage(Text::_('MOD_NEWSLETTER_SUBSCRIPTION_MSG_INVALID_EMAIL'), 'error');

            return;
        }

        if ($this->isSubscribed($email)) {
            $app->enqueueMessage(Text::_('MOD_NEWSLETTER_SUBSCRIPTION_MSG_ALREADY'), 'info');

            return;
        }

        $db = $this->getDatabase();

        try {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__newsletter_subscribers'))
                ->columns([$db->quoteName('email')])
                ->values(':email')
                ->bind(':email', $email);
            $db->setQuery($query)->execute();
        } catch (\RuntimeException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'mod_newsletter_subscription');
            $app->enqueueMessage(Text::_('MOD_NEWSLETTER_SUBSCRIPTION_MSG_ERROR'), 'error');

            return;
        }

        $this->sendGreeting($email, $params);

        $app->enqueueMessage(Text::_('MOD_NEWSLETTER_SUBSCRIPTION_MSG_SUBSCRIBED'), 'success');
    }

    public function unsubscribe(string $email, CMSApplicationInterface $app): void
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $app->enqueueMessage(Text::_('MOD_NEWSLETTER_SUBSCRIPTION_MSG_INVALID_EMAIL'), 'error');

            return;
        }

        if (!$this->isSubscribed($email)) {
            $app->enqueueMessage(Text::_('MOD_NEWSLETTER_SUBSCRIPTION_MSG_NOT_SUBSCRIBED'), 'info');

            return;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__newsletter_subscribers'))
            ->where($db->quoteName('email') . ' = :email')
            ->bind(':email', $email);
        $db->setQuery($query)->execute();

        $app->enqueueMessage(Text::_('MOD_NEWSLETTER_SUBSCRIPTION_MSG_UNSUBSCRIBED'), 'success');
    }

    private function isSubscribed(string $email): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__newsletter_subscribers'))
            ->where($db->quoteName('email') . ' = :email')
            ->bind(':email', $email, ParameterType::STRING);

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    private function sendGreeting(string $email, Registry $params): void
    {
        $subject = $params->get('subject') ?: Text::_('MOD_NEWSLETTER_SUBSCRIPTION_GREETING_SUBJECT_DEFAULT');
        $body    = $params->get('message') ?: Text::_('MOD_NEWSLETTER_SUBSCRIPTION_GREETING_MESSAGE_DEFAULT');

        try {
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $mailer->addRecipient($email);
            $mailer->setSubject($subject);
            $mailer->isHtml(true);
            $mailer->setBody($body);
            $mailer->Send();
        } catch (\Throwable $e) {
            Log::add($e->getMessage(), Log::WARNING, 'mod_newsletter_subscription');
        }
    }
}
