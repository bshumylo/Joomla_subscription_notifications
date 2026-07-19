<?php

require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;
use Zdebska\Plugin\Content\NewArticleNotifications\Extension\NewArticleNotifications;

class NewArticleNotificationsTest extends TestCase
{
    private function plugin(): NewArticleNotifications
    {
        return (new ReflectionClass(NewArticleNotifications::class))->newInstanceWithoutConstructor();
    }

    private function callPrivate(object $obj, string $method, array $args)
    {
        $ref = new ReflectionMethod($obj, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($obj, $args);
    }

    public function testImageUrlStripsJoomlaImageFragment(): void
    {
        $article = (object) [
            'images' => json_encode([
                'image_intro' => 'https://example.org/images/cover.jpg#joomlaImage://local-images/cover.jpg?width=800&height=600',
            ]),
        ];

        $url = $this->callPrivate($this->plugin(), 'getImageUrl', [$article]);

        $this->assertSame('https://example.org/images/cover.jpg', $url);
    }

    public function testImageUrlEmptyWhenNoIntroImage(): void
    {
        $article = (object) ['images' => json_encode(['image_intro' => ''])];

        $this->assertSame('', $this->callPrivate($this->plugin(), 'getImageUrl', [$article]));
    }

    public function testPlainIntroIsTruncated(): void
    {
        $article = (object) [
            'introtext' => '<p>' . str_repeat('абв ', 300) . '</p>',
            'fulltext'  => '',
        ];

        $text = $this->callPrivate($this->plugin(), 'getPlainIntro', [$article]);

        $this->assertLessThanOrEqual(493, mb_strlen($text));
        $this->assertStringEndsWith('...', $text);
        $this->assertStringNotContainsString('<p>', $text);
    }

    public function testPlainIntroAddsEllipsisWhenFulltextExists(): void
    {
        $article = (object) [
            'introtext' => '<p>Короткий вступ</p>',
            'fulltext'  => '<p>Продовження</p>',
        ];

        $this->assertSame(
            'Короткий вступ...',
            $this->callPrivate($this->plugin(), 'getPlainIntro', [$article])
        );
    }

    public function testSubscribedEventsMapping(): void
    {
        $events = NewArticleNotifications::getSubscribedEvents();

        $this->assertArrayHasKey('onContentAfterSave', $events);
    }
}
