<?php

/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric z.u.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SendFeedbackBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Feedback plugin configuration entity
 *
 * @ORM\Entity()
 * @ORM\Table(name="plugin_feedback_config")
 */
class FeedbackSettings
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer", name="id")
     * @var string
     */
    private $id;

    /**
     * @ORM\Column(type="text", name="to_email")
     * @var text
     */
    private $to;

    /**
     * @ORM\Column(type="boolean", name="store_in_database")
     * @var boolean
     */
    private $storeInDatabase;

    /**
     * @ORM\Column(type="boolean", name="allow_attachments")
     * @var boolean
     */
    private $allowAttachments;

    /**
     * @ORM\Column(type="boolean", name="allow_anonymous")
     * @var boolean
     */
    private $allowAnonymous;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set id
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets the value of to.
     *
     * @return mixed
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * Sets the value of to.
     *
     * @param mixed $to Value to set
     *
     * @return self
     */
    public function setTo($to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Gets the value of storeInDatabase.
     *
     * @return mixed
     */
    public function getStoreInDatabase()
    {
        return $this->storeInDatabase;
    }

    /**
     * Sets the value of storeInDatabase.
     *
     * @param mixed $storeInDatabase Value to set
     *
     * @return self
     */
    public function setStoreInDatabase($storeInDatabase)
    {
        $this->storeInDatabase = $storeInDatabase;

        return $this;
    }

    /**
     * Gets the value of allowAttachments.
     *
     * @return mixed
     */
    public function getAllowAttachments()
    {
        return $this->allowAttachments;
    }

    /**
     * Sets the value of allowAttachments.
     *
     * @param mixed $allowAttachments Value to set
     *
     * @return self
     */
    public function setAllowAttachments($allowAttachments)
    {
        $this->allowAttachments = $allowAttachments;

        return $this;
    }

    /**
     * Gets the value of allowAnonymous.
     *
     * @return mixed
     */
    public function getAllowAnonymous()
    {
        return $this->allowAnonymous;
    }

    /**
     * Sets the value of allowAnonymous.
     *
     * @param mixed $allowAnonymous Value to set
     *
     * @return self
     */
    public function setAllowAnonymous($allowAnonymous)
    {
        $this->allowAnonymous = $allowAnonymous;

        return $this;
    }
}
