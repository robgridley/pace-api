<?php

namespace Pace\Model;

use BadMethodCallException;

trait Attachments
{
    /**
     * Attach a file to the model.
     *
     * @param string $name
     * @param string $content
     * @param string|null $field
     * @param int|string|null $keyName
     * @return \Pace\Model
     */
    public function attachFile($name, $content, $field = null, $keyName = null)
    {
        $key = $this->client->attachment()->add($this->type, $this->key($keyName), $field, $name, $content);

        return $this->client->model('FileAttachment')->read($key);
    }

    /**
     * The file attachments relationship.
     *
     * @return \Pace\XPath\Builder
     */
    public function fileAttachments()
    {
        return $this->morphMany('FileAttachment');
    }

    /**
     * Get the file attachment content.
     *
     * @return string
     */
    public function getContent()
    {
        if ($this->type !== 'FileAttachment') {
            throw new BadMethodCallException('Call to method which only exists on FileAttachment');
        }

        return $this->client->attachment()->getByKey($this->attachment)['content'];
    }
}
