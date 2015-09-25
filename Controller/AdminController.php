<?php
/**
 * @package Newscoop\SendFeedbackBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SendFeedbackBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Newscoop\SendFeedbackBundle\Form\Type\SettingsType;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;

class AdminController extends Controller
{
    /**
    * @Route("/admin/send-feedback")
    * @Template()
    */
    public function indexAction(Request $request)
    {
        $preferencesService = $this->container->get('system_preferences_service');
        $translator = $this->container->get('translator');
        $form = $this->container->get('form.factory')->create(new SettingsType(), array(
            'toEmail' => $preferencesService->SendFeedbackEmail,
            'storeInDatabase' => $preferencesService->StoreFeedbackInDatabase,
            'allowAttachments' => $preferencesService->AllowFeedbackAttachments,
            'allowNonUsers' => $preferencesService->AllowFeedbackFromNonUsers,
        ), array());

        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $data = $form->getData();

                if (strpos($data['toEmail'], ',') !== false) {
                    $emails = explode(',', $data['toEmail']);
                } else {
                    $emails = array($data['toEmail']);
                }

                $emailConstraint = new EmailConstraint();
                foreach ($emails as $email) {
                    $emailConstraint->message = $translator->trans('plugin.feedback.msg.errorinvalidemail', array('$1' => $email));
                    $errors = $this->get('validator')->validateValue(
                        $email,
                        $emailConstraint
                    );
                }

                if (count($errors) > 0) {

                    $errorMsg = array();
                    foreach ($errors as $error) {
                        $errorMsg[] = $error->getMessage();
                    }
                    $this->get('session')->getFlashBag()->add('error', implode('<br>', $errorMsg));

                } else {
                    $preferencesService->set('SendFeedbackEmail', $data['toEmail']);
                    $preferencesService->set('StoreFeedbackInDatabase', $data['storeInDatabase']);
                    $preferencesService->set('AllowFeedbackAttachments', $data['allowAttachments']);
                    $preferencesService->set('AllowFeedbackFromNonUsers', $data['allowNonUsers']);

                    $this->get('session')->getFlashBag()->add('success', $translator->trans('plugin.feedback.msg.saved'));
                }

                return $this->redirect($this->generateUrl('newscoop_sendfeedback_admin_index'));
            }

            $this->get('session')->getFlashBag()->add('error', $translator->trans('plugin.feedback.msg.error'));

            return $this->redirect($this->generateUrl('newscoop_sendfeedback_admin_index'));
        }

        return array(
            'form' => $form->createView()
        );
    }
}
