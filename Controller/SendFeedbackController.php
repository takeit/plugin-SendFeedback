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
        $settingsEntity = $em
            ->getRepository('Newscoop\SendFeedbackBundle\Entity\FeedbackSettings')
            ->findOneById(1);

        $to = $settingsEntity->getTo();
        $storeInDb = $settingsEntity->getStoreInDatabase();
        $allowAttachment = $settingsEntity->getAllowAttachments();
        $allowNonUsers = $settingsEntity->getAllowAnonymous();

        try {
            $requestLanguage = $em->getRepository('Newscoop\Entity\Language')->findOneByCode($request->getLocale());
        } catch (\Exception $e) {
            // Default to english
            $requestLanguage = 1;
        }
        $response = array('response' => '');
        $parameters = $request->request->all();
        $form = $this->container->get('form.factory')->create(new SendFeedbackType(), array(), array());

        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $user = $this->container->get('user')->getCurrentUser();
                } catch(\Exception $e) {
                    $user = null;
                }
                $attachment = $form['attachment']->getData();
                $date = new \DateTime("now");
                $userIsBanned = false;
                $errorOccured = false;

                if (
                    is_null($data['first_name']) ||
                    is_null($data['last_name']) ||
                    is_null($data['email']) ||
                    is_null($data['subject']) ||
                    is_null($data['message'])
                ) {
                    $errorOccured = true;
                    $response['response'] = array(
                        'status' => false,
                        'message' => $translator->trans('plugin.feedback.msg.notfilled'),
                        // 'post-first_name' => $request->request->get('sendFeedbackForm')['first_name'],
                        // 'post-last_name' => $request->request->get('sendFeedbackForm')['last_name'],
                        // 'post-email' => $request->request->get('sendFeedbackForm')['email'],
                        // 'post-subject' => $request->request->get('sendFeedbackForm')['subject'],
                        // 'post-message' => $request->request->get('sendFeedbackForm')['message']
                    );
                }

                if ($user && !$errorOccured) {
                    $acceptanceRepository = $em->getRepository('Newscoop\Entity\Comment\Acceptance');
                    if (isset($parameters['publication'])) {
                    	if ($acceptanceRepository->checkParamsBanned($user->getUsername(), $user->getEmail(), null, $parameters['publication'])) {
                            $userIsBanned = true;
                            $response['response'] = array(
                                'status' => false,
                                'message' => $translator->trans('plugin.feedback.msg.banned')
                            );
                    	}
                    }
                }

                if (!$userIsBanned && !$errorOccured) {
                    if ($allowNonUsers == 0 && is_null($user)) {
                        $errorOccured = true;
                        $response['response'] = array(
                            'status' => false,
                            'message' => $translator->trans('plugin.feedback.msg.errorlogged')
                        );
                    } else {

                        // Check we the form supplied custom reciever
                        if (isset($parameters['receivers'])) {
                            if (strpos($to, ',') !== false) {
                                $validEmails = array_map('trim', explode(',', $to));
                            } else {
                                $validEmails = array($to);
                            }
                            if (strpos($parameters['receivers'], ',') !== false) {
                                $receivers = array_map('trim', explode(',', $parameters['receivers']));
                            } else {
                                $receivers = array($parameters['receivers']);
                            }
                            foreach ($receivers as $receiver) {
                                if (!in_array($receiver, $validEmails)) {
                                    $errorOccured = true;
                                    $response['response'] = array(
                                        'status' => false,
                                        'message' => $translator->trans('plugin.feedback.msg.erroremail', array('$1' => $receiver))
                                    );
                                }
                            }
                            if (!$errorOccured) {
                                $to = $receivers;
                            }
                        } else {
                            if (strpos($to, ',') !== false) {
                                $to = array_map('trim', explode(',', $to));
                            }
                        }
                    }

                    if (!$errorOccured) {
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
                            'url' => isset($parameters['feedbackUrl']) ? $parameters['feedbackUrl'] : null,
                            'time_created' => new \DateTime(),
                            'language' => isset($parameters['language']) ? $parameters['language'] : $requestLanguage,
                            'status' => 'pending',
                            'attachment_type' => 'none',
                            'attachment_id' => 0
                        );

                        if ($allowAttachment == 1 && $attachment) {
                            if ($attachment->getClientSize() <= $attachment->getMaxFilesize() && $attachment->getClientSize() != 0) {
                                if (in_array($attachment->guessClientExtension(), array('png','jpg','jpeg','gif','pdf'))) {
                                    $response['response'] = $this->processAttachment($attachment, $user, $values, $to);
                                } else {
                                    $response['response'] = array(
                                        'status' => false,
                                        'message' => $translator->trans('plugin.feedback.msg.errorfile')
                                    );
                                }
                            } else {
                                $response['response'] = array(
                                    'status' => false,
                                    'message' => $translator->trans('plugin.feedback.msg.errorsize', array('%size%' => $preferencesService->MaxUploadFileSize))
                                );
                            }
                        } else {

                            if (isset($parameters['publication']) && $storeInDb == 1 && $allowNonUsers == 0) {
                                $feedbackRepository = $em->getRepository('Newscoop\Entity\Feedback');
                                $feedback = new Feedback();
                                $feedbackRepository->save($feedback, $values);
                                $feedbackRepository->flush();
                			}
                            $this->sendMail($values, $user, $to, $attachment);

                            $response['response'] = array(
                                'status' => true,
                                'message' => $translator->trans('plugin.feedback.msg.success')
                            );
                        }
                    }
                }
            } else {
                $response['response'] = array(
                    'status' => false,
                    'message' => 'Invalid Form'
                );
    	    }

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
    private function processAttachment($attachment, $user, $values, $to)
    {
        $imageService = $this->container->get('image');
        $attachmentService = $this->container->get('attachment');
        $em = $this->container->get('em');
        $translator = $this->container->get('translator');
        $feedbackRepository = $em->getRepository('Newscoop\Entity\Feedback');
        $language = $values['language'];
        if (!($language instanceof \Newscoop\Entity\Language)) {
            $language = $em->getRepository('Newscoop\Entity\Language')->findOneById($language);
        }

        $settingsEntity = $em
            ->getRepository('Newscoop\SendFeedbackBundle\Entity\FeedbackSettings')
            ->findOneById(1);
        $storeInDb = $settingsEntity->getStoreInDatabase();
        $allowNonUsers = $settingsEntity->getAllowAnonymous();

        $feedback = new Feedback();
        $source = array(
            'user' => $user,
            'source' => 'feedback'
        );

        if (strstr($attachment->getClientMimeType(), 'image')) {

            $image = $imageService->upload($attachment, $source);
            $image->setStatus(LocalImage::STATUS_UNAPPROVED);
            $em->persist($image);
            $em->flush();

            $values['attachment_type'] = 'image';
            $values['attachment_id'] = $image->getId();

            if ($allowNonUsers == 0 && $storeInDb == 1) {
                $feedbackRepository->save($feedback, $values);
                $feedbackRepository->flush();
            }

            $this->sendMail($values, $user, $to, $image);

            $this->get('dispatcher')
                ->dispatch('image.delivered', new GenericEvent($this, array(
                    'user' => $user,
                    'image_id' => $image->getId()
                )));

            return array(
                'status' => true,
                'message' => $translator->trans('plugin.feedback.msg.successimage')
            );
        }

        $file = $attachmentService->upload($attachment, '', $language, $source);
        $file->setStatus(Attachment::STATUS_UNAPPROVED);
        $em->persist($file);
        $em->flush();

        $values['attachment_type'] = 'document';
        $values['attachment_id'] = $file->getId();

        if ($allowNonUsers == 0 && $storeInDb == 1) {
            $feedbackRepository->save($feedback, $values);
            $feedbackRepository->flush();
        }

        $this->sendMail($values, $user, $to, $file);

        $this->get('dispatcher')
            ->dispatch('document.delivered', new GenericEvent($this, array(
                'user' => $user,
                'document_id' => $file->getId()
            )));

        return array(
            'status' => true,
            'message' => $translator->trans('plugin.feedback.msg.successfile')
        );
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
}
