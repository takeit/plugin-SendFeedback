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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\SendFeedbackBundle\Form\Type\SendFeedbackType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Newscoop\Entity\Feedback;
use Newscoop\EventDispatcher\Events\GenericEvent;
use Newscoop\Entity\Attachment;
use Newscoop\Image\LocalImage;

/**
 * Send feedback controller
 */
class SendFeedbackController extends Controller
{
    /**
     * @Route("/plugin/send-feedback")
     * @Method("POST")
     */
    public function indexAction(Request $request)
    {
        $translator = $this->container->get('translator');
        $em = $this->container->get('em');
        $preferencesService = $this->container->get('system_preferences_service');
        $pluginSettings = $em->getRepository('Newscoop\SendFeedbackBundle\Entity\FeedbackSettings')->findOneById(1);
        $to = $pluginSettings->getTo();
        $allowNonUsers = $pluginSettings->getAllowAnonymous();

        try {
            $locale = $em->getRepository('Newscoop\Entity\Language')->findOneByCode($request->getLocale());
        } catch (\Exception $e) {
            $locale = 1; // Default to english
        }

        $form = $this->createForm(new SendFeedbackType(), array());
        $form->handleRequest($request);
        if ($form->isValid()) {
            $parameters = $request->request->all();
            $data = $form->getData();
            $userService = $this->container->get('user');
            try {
                $user = $userService->getCurrentUser();
            } catch(\Exception $e) {
                // return error if not allowed for annonymous and user not found
                if ($allowNonUsers == 0) {
                    return $this->createResponse($request, array(
                        'response' => array(
                            'status' => false,
                            'message' => $translator->trans('plugin.feedback.msg.errorlogged')
                        )
                    ));
                }

                // Load default plugin user
                $user = $userService->loadUserByUsername('sendfeedback');
            }

            // User exists and publication is set - check if not banned
            if (!is_null($user) && isset($parameters['publication'])) {
                $userIsBanned = $em->getRepository('Newscoop\Entity\Comment\Acceptance')
                    ->checkParamsBanned($user->getUsername(), $user->getEmail(), null, $parameters['publication']);
            	if ($userIsBanned) {
                    return $this->createResponse($request, array(
                        'response' => array(
                            'status' => false,
                            'message' => $translator->trans('plugin.feedback.msg.banned')
                        )
                    ));
            	}
            }

            // Check if the form provides custom recievers
            if (isset($parameters['receivers'])) {
                $validEmails = array($to);
                if (strpos($to, ',') !== false) {
                    $validEmails = array_map('trim', explode(',', $to));
                }

                if (strpos($parameters['receivers'], ',') !== false) {
                    $receivers = array_map('trim', explode(',', $parameters['receivers']));
                } else {
                    $receivers = array($parameters['receivers']);
                }

                foreach ($receivers as $receiver) {
                    if (!in_array($receiver, $validEmails)) {
                        return $this->createResponse($request, array(
                            'response' => array(
                                'status' => false,
                                'message' => $translator->trans('plugin.feedback.msg.erroremail', array('$1' => $receiver))
                            )
                        ));
                    }
                }
                $to = $receivers;
            } else {
                if (strpos($to, ',') !== false) {
                    $to = array_map('trim', explode(',', $to));
                }
            }

            $values = array(
                'user' => $user,
                'publication' => isset($parameters['publication']) ? $parameters['publication'] : null,
                'section' => isset($parameters['section']) ? $parameters['section'] : null,
                'article' => isset($parameters['article']) ? $parameters['article'] : null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'subject' => $data['subject'],
                'message' => $data['message'],
                'url' => isset($parameters['feedbackUrl']) ? $parameters['feedbackUrl'] : '#',
                'time_created' => new \DateTime(),
                'time_updated' => new \DateTime(),
                'language' => isset($parameters['language']) ? $parameters['language'] : $locale,
                'status' => 'pending',
                'attachment_type' => 'none',
                'attachment_id' => 0
            );

            $attachment = $form['attachment']->getData();
            $processedAttachment = null;
            if ($pluginSettings->getAllowAttachments() == 1 && $attachment) {
                if ($attachment->getClientSize() <= $attachment->getMaxFilesize() && $attachment->getClientSize() != 0) {
                    if (in_array($attachment->guessClientExtension(), array('png','jpg','jpeg','gif','pdf'))) {
                        $values = $this->processAttachment($attachment, $user, $values, $to);
                        $processedAttachment = $values['attachment'];
                        unset($values['attachment']);
                    } else {
                        return $this->createResponse($request, array(
                            'response' => array(
                                'status' => false,
                                'message' => $translator->trans('plugin.feedback.msg.errorfile')
                            )
                        ));
                    }
                } else {
                    return $this->createResponse($request, array(
                        'response' => array(
                            'status' => false,
                            'message' => $translator->trans('plugin.feedback.msg.errorsize', array('%size%' => $preferencesService->MaxUploadFileSize))
                        )
                    ));
                }
            }

            if ($pluginSettings->getStoreInDatabase() == 1) {
                $feedbackRepository = $em->getRepository('Newscoop\Entity\Feedback');
                $feedbackRepository->save(new Feedback(), $values);
                $feedbackRepository->flush();
            }

            $this->sendMail($values, $user, $to, $processedAttachment);

            return $this->createResponse($request, array(
                'response' => array(
                    'status' => true,
                    'message' => $translator->trans('plugin.feedback.msg.success')
                )
            ));
        } else {
            return $this->createResponse($request, array(
                'response' => array(
                    'status' => false,
                    'message' => 'Invalid Form'
                )
            ));
	    }
    }

    /**
     * Send e-mail message with feedback
     *
     * @param array                $values Values
     * @param Newscoop\Entity\User $user   User
     * @param string               $to     Email that messages will be sent
     * @param UploadedFile|null    $file   Uploaded file dir
     *
     * @return void
     */
    private function sendMail($values, $user, $to, $file = null)
    {
        $translator = $this->container->get('translator');
        $emailService = $this->container->get('email');
        $preferencesService = $this->container->get('system_preferences_service');

        $userProfile = null;
        $siteName = $preferencesService->SiteTitle;
        $fromAddress = $preferencesService->EmailFromAddress;

        if ($user instanceof \Newscoop\Entity\User) {
            $zendRouter = $this->container->get('zend_router');
            $request = $this->container->get('request');
            $link = $request->getScheme() . '://' . $request->getHttpHost();
            $userProfile = $link . $zendRouter->assemble(array('controller' => 'user', 'action' => 'profile', 'module' => 'default')) ."/". urlencode($user->getUsername());
            $fromAddress = $user->getEmail();
        }

        $message = $this->renderView(
            'NewscoopSendFeedbackBundle::email.txt.twig',
            array(
                'siteName' => $siteName,
                'name' => sprintf('%s %s', $values['first_name'], $values['last_name']),
                'profile' => $userProfile,
                'email' => $values['email'],
                'page' => $values['url'],
                'subject' => $values['subject'],
                'message' => $values['message']
            )
        );

        $subject = $translator->trans('plugin.feedback.email.subject_mail', array('%siteName%' => $siteName, '%subject%' => strip_tags($values['subject'])));
        $attachmentDir = '';
        if ($file instanceof LocalImage) {
            $imageService = $this->container->get('image');
            $attachmentDir = $imageService->getImagePath().$file->getBasename();
        }

        if ($file instanceof Attachment) {
            $attachmentService = $this->container->get('attachment');
            $attachmentDir = $attachmentService->getStorageLocation($file);
        }

        $emailService->send($subject, $message, $to, $fromAddress, $attachmentDir);
    }

    /**
     * Process attachment
     *
     * @param UploadedFile         $attachment Uploaded file
     * @param Newscoop\Entity\User $user       User
     * @param array                $values     Values
     *
     * @return array
     */
    private function processAttachment($file, $user, $values)
    {
        $imageService = $this->container->get('image');
        $attachmentService = $this->container->get('attachment');
        $em = $this->container->get('em');
        $pluginSettings = $em->getRepository('Newscoop\SendFeedbackBundle\Entity\FeedbackSettings')->findOneById(1);
        $source = array('user' => $user, 'source' => 'feedback');
        $language = $values['language'];
        if (!($language instanceof \Newscoop\Entity\Language)) {
            $language = $em->getRepository('Newscoop\Entity\Language')->findOneById($language);
        }

        if (strstr($file->getClientMimeType(), 'image')) {
            $image = $imageService->upload($file, $source);
            $image->setStatus(LocalImage::STATUS_UNAPPROVED);
            $em->persist($image);
            $em->flush();

            $values['attachment_type'] = 'image';
            $values['attachment_id'] = $image->getId();
            $values['attachment'] = $image;
        } else {
            $attachment = $attachmentService->upload($file, '', $language, $source);
            $attachment->setStatus(Attachment::STATUS_UNAPPROVED);
            $em->persist($attachment);
            $em->flush();

            $values['attachment_type'] = 'document';
            $values['attachment_id'] = $attachment->getId();
            $values['attachment'] = $attachment;
        }

        $this->get('dispatcher')
            ->dispatch('document.delivered', new GenericEvent($this, array(
                'user' => $user,
                'document_id' => $values['attachment_id']
            )));

        return $values;
    }

    /**
     * Unparses a url which is parse with parse_url()
     *
     * @param  array $parsed_url
     *
     * @return string
     */
    private function unparse_url(array $parsed_url)
    {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    private function createResponse($request, $response)
    {
        $parameters = $request->request->all();
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($response);
        } else {
            if (!$response['response']['status']) {
                $redirectUrl = (isset($parameters['feedbackUrl'])) ? $parameters['feedbackUrl'] : '/';
                $redirectUrlParts = parse_url($redirectUrl);

                $redirectQuery = array();
                parse_str($redirectUrlParts['query'], $redirectQuery);
                $redirectQuery['feedback_error'] = $response['response']['message'];
                $redirectUrlParts['query'] = http_build_query($redirectQuery);

                $redirectUrl = $this->unparse_url($redirectUrlParts);
            } else {
                $redirectUrl = (isset($parameters['redirect_path'])) ? $parameters['redirect_path'] : '/';
            }
            return $this->redirect($redirectUrl);
        }
    }
}
