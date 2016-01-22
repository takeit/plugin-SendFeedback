<?php
/**
 * @package Newscoop\SendFeedbackBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SendFeedbackBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Newscoop\EventDispatcher\Events\GenericEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Newscoop\SendFeedbackBundle\Entity\FeedbackSettings;

/**
 * Event lifecycle management
 */
class LifecycleSubscriber implements EventSubscriberInterface
{
    private $container;

    private $em;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->em = $this->container->get('em');
    }

    public function install(GenericEvent $event)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->updateSchema($this->getClasses(), true);

        // Generate proxies for entities
        $this->em->getProxyFactory()->generateProxyClasses($this->getClasses(), __DIR__ . '/../../../../library/Proxy');

        $settingsEntity = new FeedbackSettings();
        $settingsEntity->setId(1);
        $settingsEntity->setTo('email@example.com');
        $settingsEntity->setStoreInDatabase(false);
        $settingsEntity->setAllowAttachments(false);
        $settingsEntity->setAllowAnonymous(false);

        $this->em->persist($settingsEntity);
        $this->em->flush();
    }

    public function update(GenericEvent $event)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->updateSchema($this->getClasses(), true);

        // Generate proxies for entities
        $this->em->getProxyFactory()->generateProxyClasses($this->getClasses(), __DIR__ . '/../../../../library/Proxy');

        $preferencesService = $this->container->get('system_preferences_service');
        $settingsEntity = $this
            ->em
            ->getRepository('Newscoop\SendFeedbackBundle\Entity\FeedbackSettings')
            ->findOneById(1);
        if (is_null($settingsEntity)) {
            $settingsEntity = new FeedbackSettings();
            $settingsEntity->setId(1);

            if ($preferencesService->get('SendFeedbackEmail', null) === null) {
                $settingsEntity->setTo('email@example.com');
            } else {
                $settingsEntity->setTo($preferencesService->get('SendFeedbackEmail'));
                $removeEmail = $this->em->getRepository('Newscoop\NewscoopBundle\Entity\SystemPreferences')->findOneBy(array(
                    'option' => 'SendFeedbackEmail'
                ));
                $this->em->remove($removeEmail);
            }
            if ($preferencesService->get('SendFeedbackEmail', null) === null) {
                $settingsEntity->setStoreInDatabase(false);
            } else {
                $settingsEntity->setStoreInDatabase((($preferencesService->get('StoreFeedbackInDatabase') == 'N') ? false : true));
                $removeDatabase = $this->em->getRepository('Newscoop\NewscoopBundle\Entity\SystemPreferences')->findOneBy(array(
                    'option' => 'StoreFeedbackInDatabase'
                ));
                $this->em->remove($removeDatabase);
            }
            if ($preferencesService->get('AllowFeedbackAttachments', null) === null) {
                $settingsEntity->setAllowAttachments(false);
            } else {
                $settingsEntity->setAllowAttachments((($preferencesService->get('AllowFeedbackAttachments') == 'N') ? false : true));
                $removeAttachments = $this->em->getRepository('Newscoop\NewscoopBundle\Entity\SystemPreferences')->findOneBy(array(
                    'option' => 'AllowFeedbackAttachments'
                ));
                $this->em->remove($removeAttachments);
            }
            if ($preferencesService->get('AllowFeedbackFromNonUsers', null) === null) {
                $settingsEntity->setAllowAnonymous(false);
            } else {
                $settingsEntity->setAllowAnonymous((($preferencesService->get('AllowFeedbackFromNonUsers') == 'N') ? false : true));
                $removeNonUserPref = $this->em->getRepository('Newscoop\NewscoopBundle\Entity\SystemPreferences')->findOneBy(array(
                    'option' => 'AllowFeedbackFromNonUsers'
                ));
                $this->em->remove($removeNonUserPref);
            }
        }
        $this->em->persist($settingsEntity);
        $this->em->flush();
    }

    public function remove(GenericEvent $event)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->dropSchema($this->getClasses(), true);
    }

    public static function getSubscribedEvents()
    {
        return array(
            'plugin.install.newscoop_send_feedback_plugin' => array('install', 1),
            'plugin.update.newscoop_send_feedback_plugin' => array('update', 1),
            'plugin.remove.newscoop_send_feedback_plugin' => array('remove', 1),
        );
    }

    private function getClasses(){
        return array(
            $this->em->getClassMetadata('Newscoop\SendFeedbackBundle\Entity\FeedbackSettings'),
        );
    }
}
