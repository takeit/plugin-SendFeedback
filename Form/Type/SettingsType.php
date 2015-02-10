<?php
/**
 * @package Newscoop\SendFeedbackBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SendFeedbackBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class SettingsType extends AbstractType
{   
    public function buildForm(FormBuilderInterface $builder, array $options)
    {   
        $builder
            ->add('toEmail', 'email', array(
                'label' => 'plugin.feedback.label.toemail',
                'error_bubbling' => true,
                'required' => true
            ))
            ->add('allowNonUsers', 'choice', array(
                'choices' => array(
                    'Y' => 'plugin.feedback.label.yesoption',
                    'N' => 'plugin.feedback.label.nooption'
                ),
                'label' => 'plugin.feedback.label.allownonusers',
                'error_bubbling' => true,
                'multiple' => false,
                'expanded' => true,
                'required' => true
            ));
    }

    public function getName()
    {
        return 'omniboxFeedbackSettings';
    }
}
