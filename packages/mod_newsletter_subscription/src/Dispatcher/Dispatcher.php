<?php

namespace Zdebska\Module\NewsletterSubscription\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $data['formAction'] = Uri::getInstance()->toString(['path', 'query']);

        $this->handleSubmission($data['params']);

        return $data;
    }

    private function handleSubmission($params): void
    {
        $app   = $this->getApplication();
        $input = $app->getInput();

        if (strtoupper($input->getMethod()) !== 'POST') {
            return;
        }

        $subscribe   = $input->post->get('subscribe', null, 'cmd') !== null;
        $unsubscribe = $input->post->get('unsubscribe', null, 'cmd') !== null;

        if (!$subscribe && !$unsubscribe) {
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN_NOTICE'), 'warning');

            return;
        }

        $email  = $input->post->get('email', '', 'string');
        $helper = $this->getHelperFactory()->getHelper('NewsletterSubscriptionHelper');

        if ($subscribe) {
            $helper->subscribe($email, $params, $app);
        } else {
            $helper->unsubscribe($email, $app);
        }

        // Redirect (PRG) so a refresh does not resubmit the form.
        $app->redirect(Uri::getInstance()->toString(['path', 'query']));
    }
}
