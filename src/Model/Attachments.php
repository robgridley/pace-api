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
     * @param int|string|null $primaryKey
     * @return \Pace\Model
     */
    public function attach($name, $content, $primaryKey = null)
    {
        $key = $this->client->attachment()->add($this->type, $this->key($primaryKey), null, $name, $content);

        return $this->client->model('FileAttachment')->read($key);
    }

    /**
     * Get the model's attachments.
     *
     * @return \Pace\KeyCollection
     */
    public function attachments()
    {
        return $this->morphMany('FileAttachment')->get();
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
