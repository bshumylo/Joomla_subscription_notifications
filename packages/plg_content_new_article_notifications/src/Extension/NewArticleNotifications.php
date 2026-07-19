<?php

namespace Zdebska\Plugin\Content\NewArticleNotifications\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

/**
 * Notifies subscribers about new published articles via email,
 * Telegram and Mattermost.
 */
final class NewArticleNotifications extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    private const LOG_CATEGORY = 'plg_content_new_article_notifications';

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentAfterSave' => 'onContentAfterSave',
        ];
    }

    public function onContentAfterSave(AfterSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_content.article' || !$event->getIsNew()) {
            return;
        }

        $article = $event->getItem();

        // Only announce articles that are created in the published state.
        if ((int) $article->state !== 1) {
            return;
        }

        $categories = array_map('intval', (array) $this->params->get('cat_id', []));

        if (!\in_array((int) $article->catid, $categories, true)) {
            return;
        }

        $url   = $this->getArticleUrl($article);
        $image = $this->getImageUrl($article);

        if ($this->params->get('send_tg')) {
            $this->sendTelegram($article, $url, $image);
        }

        if ($this->params->get('send_mm')) {
            $this->sendMattermost($article, $url, $image);
        }

        if ($this->params->get('send_emails')) {
            $this->sendEmails($article, $url, $image);
        }
    }

    private function getArticleUrl(object $article): string
    {
        $url = Uri::root() . 'index.php?option=com_content&view=article&id=' . (int) $article->id;

        if ((int) $article->catid > 1) {
            $url .= '&catid=' . (int) $article->catid;
        }

        return $url;
    }

    /**
     * Absolute URL of the intro image, or empty string.
     * Joomla 4+ stores images as "path#joomlaImage://..." — the fragment must be stripped.
     */
    private function getImageUrl(object $article): string
    {
        $images = json_decode((string) ($article->images ?? ''));
        $intro  = trim((string) ($images->image_intro ?? ''));

        if ($intro === '') {
            return '';
        }

        $intro = explode('#', $intro, 2)[0];

        if (preg_match('#^https?://#i', $intro)) {
            return $intro;
        }

        return Uri::root() . ltrim($intro, '/');
    }

    private function getPlainIntro(object $article, int $maxLength = 490): string
    {
        $text = trim(strip_tags((string) $article->introtext));

        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength) . '...';
        } elseif (trim((string) $article->fulltext) !== '') {
            $text .= '...';
        }

        return $text;
    }

    private function sendTelegram(object $article, string $url, string $image): void
    {
        $token   = trim((string) $this->params->get('token', ''));
        $channel = trim((string) $this->params->get('channel', ''));

        if ($token === '' || $channel === '') {
            return;
        }

        $text = '<strong>' . htmlspecialchars($article->title, ENT_NOQUOTES, 'UTF-8') . "</strong>\n"
            . htmlspecialchars($this->getPlainIntro($article), ENT_NOQUOTES, 'UTF-8');

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => Text::_('PLG_CONTENT_NEW_ARTICLE_NOTIFICATIONS_DETAILS'), 'url' => $url],
            ]],
        ];

        if ($image === '') {
            $endpoint = 'https://api.telegram.org/bot' . $token . '/sendMessage';
            $payload  = [
                'chat_id'      => $channel,
                'text'         => $text,
                'parse_mode'   => 'HTML',
                'reply_markup' => $keyboard,
            ];
        } else {
            $endpoint = 'https://api.telegram.org/bot' . $token . '/sendPhoto';
            $payload  = [
                'chat_id'      => $channel,
                'caption'      => $text,
                'parse_mode'   => 'HTML',
                'photo'        => $image,
                'reply_markup' => $keyboard,
            ];
        }

        $this->postJson($endpoint, $payload, 'Telegram');
    }

    private function sendMattermost(object $article, string $url, string $image): void
    {
        $webhook = trim((string) $this->params->get('mm_webhook', ''));

        if ($webhook === '') {
            return;
        }

        $text = '#### [' . $article->title . '](' . $url . ")\n"
            . $this->getPlainIntro($article) . "\n"
            . '[' . Text::_('PLG_CONTENT_NEW_ARTICLE_NOTIFICATIONS_DETAILS') . '](' . $url . ')';

        $payload = ['text' => $text];

        if ($image !== '') {
            $payload['attachments'] = [
                [
                    'fallback'  => (string) $article->title,
                    'image_url' => $image,
                ],
            ];
        }

        $this->postJson($webhook, $payload, 'Mattermost');
    }

    private function sendEmails(object $article, string $url, string $image): void
    {
        try {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('email'))
                ->from($db->quoteName('#__newsletter_subscribers'));
            $subscribers = $db->setQuery($query)->loadColumn();
        } catch (\RuntimeException $e) {
            Log::add('Cannot load subscribers: ' . $e->getMessage(), Log::ERROR, self::LOG_CATEGORY);

            return;
        }

        if (!$subscribers) {
            return;
        }

        $newsletter = $this->params->get('newsletter') ?: Text::_('PLG_CONTENT_NEW_ARTICLE_NOTIFICATIONS_NEWSLETTER_DEFAULT');
        $bccMax     = max(1, (int) $this->params->get('bccmax', 50));

        $title = htmlspecialchars((string) $article->title, ENT_QUOTES, 'UTF-8');
        $cover = $image === '' ? '' : "<div><img src='" . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . "' alt=''/></div>";

        $footer  = '';
        $channel = trim((string) $this->params->get('channel', ''));

        if ($this->params->get('send_tg') && $channel !== '') {
            $channelName = ltrim($channel, '@');
            $footer      = "<footer style='margin-top: 10px;'>"
                . Text::sprintf(
                    'PLG_CONTENT_NEW_ARTICLE_NOTIFICATIONS_TG_FOOTER',
                    "<a href='https://t.me/" . htmlspecialchars($channelName, ENT_QUOTES, 'UTF-8') . "' target='_blank'>"
                    . htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') . '</a>'
                )
                . '</footer>';
        }

        $body = '<h2>' . $title . '</h2>'
            . $cover
            . $article->introtext
            . "<div><a href='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "' target='_blank' style='font-size: 16px;'>"
            . Text::_('PLG_CONTENT_NEW_ARTICLE_NOTIFICATIONS_READ_MORE') . '</a></div>'
            . $footer;

        $subject      = $newsletter . ': ' . $article->title;
        $mailerFactory = Factory::getContainer()->get(MailerFactoryInterface::class);
        $siteMail      = $this->getApplication()->get('mailfrom');

        foreach (array_chunk($subscribers, $bccMax) as $batch) {
            try {
                $mailer = $mailerFactory->createMailer();
                $mailer->addRecipient($siteMail);
                $mailer->addBcc($batch);
                $mailer->setSubject($subject);
                $mailer->isHtml(true);
                $mailer->setBody($body);
                $mailer->Send();
            } catch (\Throwable $e) {
                Log::add('Email batch failed: ' . $e->getMessage(), Log::WARNING, self::LOG_CATEGORY);
            }
        }
    }

    private function postJson(string $endpoint, array $payload, string $service): void
    {
        try {
            $response = HttpFactory::getHttp()->post(
                $endpoint,
                json_encode($payload),
                ['Content-Type' => 'application/json'],
                10
            );

            if ($response->code < 200 || $response->code >= 300) {
                Log::add(
                    sprintf('%s notification failed (HTTP %d): %s', $service, $response->code, (string) $response->body),
                    Log::WARNING,
                    self::LOG_CATEGORY
                );
            }
        } catch (\Throwable $e) {
            Log::add(sprintf('%s notification failed: %s', $service, $e->getMessage()), Log::WARNING, self::LOG_CATEGORY);
        }
    }
}
