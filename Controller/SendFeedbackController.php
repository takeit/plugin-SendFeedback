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
use Newscoop\SendFeedbackBundle\Form\Type\SendFeedbackType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SendFeedbackController extends Controller
{
    /**
    * @Route("/plugin/send-feedback")
    * @Template()
    */
    public function indexAction(Request $request)
    {   
        $preferencesService = $this->container->get('system_preferences_service');
        $translator = $this->container->get('translator');
        $isAttached = false;
        $fileName = null;
        $form = $this->container->get('form.factory')->create(new SendFeedbackType(), array(), array());

        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $data = $form->getData();
                $user = $this->container->get('user')->getCurrentUser();
                $toEmail = $preferencesService->SendFeedbackEmail;
                $attachment = $form['attachment'];
                $cacheDir = __DIR__ . '/../../../../cache';
                $date = new \DateTime("now");
                $fileDir = $cacheDir . '/' . $date->format('Y-m-d-H:i:s') . $attachment->getData()->getClientOriginalName();
                if ($user) {
                    if ($attachment && $attachment->getData()->getClientSize() <= $attachment->getData()->getMaxFilesize() && $attachment->getData()->getClientSize() != 0) {
                        switch ($attachment->getData()->guessClientExtension()) {
                            case 'png':
                                $this->storeInCache($attachment, $cacheDir, $fileDir);
                                break;
                            case 'jpg':
                                $this->storeInCache($attachment, $cacheDir, $fileDir);
                                break;
                            case 'jpeg':
                                $this->storeInCache($attachment, $cacheDir, $fileDir);
                                break;
                            case 'gif':
                                $this->storeInCache($attachment, $cacheDir, $fileDir);
                                break;
                            case 'pdf':
                                $this->storeInCache($attachment, $cacheDir, $fileDir);
                                break;
                            
                            default:
                                $this->get('session')->getFlashBag()->add('error', $translator->trans('plugin.feedback.msg.errorfile'));

                                return $this->redirect($this->generateUrl('newscoop_sendfeedback_sendfeedback_index'));
                        }

                        $isAttached = true;
                        $this->sendMail($request, $data['subject'], $user, $toEmail, $data['message'], $isAttached, $fileDir);
                        $this->get('session')->getFlashBag()->add('success', $translator->trans('plugin.feedback.msg.saved'));

                        return $this->redirect($this->generateUrl('newscoop_sendfeedback_sendfeedback_index'));
                    }

                    $this->get('session')->getFlashBag()->add('error', $translator->trans('plugin.feedback.msg.errorsize', array('%size%' => $attachment->getData()->getMaxFilesize())));

                    return $this->redirect($this->generateUrl('newscoop_sendfeedback_sendfeedback_index'));
                } else {
                    $this->get('session')->getFlashBag()->add('error', $translator->trans('plugin.feedback.msg.error'));

                    return $this->redirect($this->generateUrl('newscoop_sendfeedback_sendfeedback_index'));
                }
            } 
        }

        return array(
            'form' => $form->createView()
        );
    }

    /**
     * Send e-mail message with feedback
     *
     * @param Request              $request     Request
     * @param string               $subject     Feedback subject
     * @param Newscoop\Entity\User $user        User
     * @param string               $to          Email that messages will be sent
     * @param string               $userMessage User feedback
     * @param string               $isAttached  Is file attached
     * @param UploadedFile|null    $attachment  Uplaoded file dir
     *
     * @return void
     */
    public function sendMail($request, $subject, $user, $to, $userMessage, $isAttached, $file = null)
    {   
        $translator = $this->container->get('translator');
        $link = $request->getScheme() . '://' . $request->getHttpHost();
        $message = \Swift_Message::newInstance()
            ->setSubject($translator->trans('plugin.feedback.email.subject', array('%subject%' => $subject)))
            ->setFrom('rmuszynski1@gamail.com')//$user->getEmail())
            ->setTo($to)
            ->setBody(
                $this->renderView(
                    'NewscoopSendFeedbackBundle::email.txt.twig',
                    array(
                        'userMessage' => $userMessage,
                        'message' => $translator->trans('plugin.feedback.email.message', array(
                            '%userLink%' => $link . '/user/profile/amerigo',//$link . '/user/profile/'.$user->getUsername(),
                            '%siteLink%' => $link,
                        ))
                    )
                )
            )
            ->setContentType("text/html");

        if($isAttached){
            $message->attach(\Swift_Attachment::fromPath($file));
        }

        $this->container->get('mailer')->send($message);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Stores uploaded file in cache
     *
     * @param UploadedFile $attachment Uplaoded file
     * @param string       $cacheDir   Cache dir
     * @param string       $fileDir    Uploaded file dir
     *
     * @return void|Exception
     */
    public function storeInCache($attachment, $cacheDir, $fileDir) 
    {
        try {
            $attachment->getData()->move($cacheDir, $fileDir);
        } catch (\Exception $e) {
            throw new \Exception("Fatal error occurred!", 1);
        }
    }
}