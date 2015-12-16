<?php
/**
 * @author Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric z.u.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Get settings for the feedback plugin
 *
 * usage:
 *         {{ get_feedback_settings  }} - get feedback plugins settings
 * or:
 *         {{ get_feedback_settings assign="settings" }} - get feedback plugins settings and assign to variable
 *
 * Type:     function
 * Name:     get_feedback_settings
 * Purpose:  Get settings from feedback plugin
 *
 * @param array
 *     $params Parameters
 * @param object
 *     $smarty The Smarty object
 */
function smarty_function_get_feedback_settings($params, &$smarty)
{
    $em = \Zend_Registry::get('container')->get('em');

    $feedbackSettings = $em
        ->getRepository('Newscoop\SendFeedbackBundle\Entity\FeedbackSettings')
        ->createQueryBuilder('f')
        ->select('f')
        ->where('f.id = :id')
        ->setParameter('id', 1)
        ->setMaxResults(1)
        ->getQuery()
        ->getArrayResult();

    if (array_key_exists('assign', $params)) {
        $smarty->assign($params['assign'], $feedbackSettings[0]);
    } else {
        return $feedbackSettings[0];
    }
}
