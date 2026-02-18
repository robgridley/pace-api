<?php

namespace Pace\Model;

use BadMethodCallException;
use Pace\Model;
use Pace\XPath\Builder;

trait Attachments
{
    /**
     * Attach a file to the model.
     *
     * @param string $name
     * @param string $content
     * @param string|null $field
     * @param string|null $keyName
     * @return Model
     */
    public function attachFile(string $name, string $content, ?string $field = null, ?string $keyName = null): Model
    {
        $key = $this->client->attachment()->add($this->type, $this->key($keyName), $field, $name, $content);

        return $this->client->model('FileAttachment')->read($key);
    }

    /**
     * The file attachments relationship.
     *
     * @return Builder
     */
    public function fileAttachments(): Builder
    {
        return $this->morphMany('FileAttachment');
    }

    /**
     * Get the file attachment content.
     *
     * @return string
     */
    public function getContent(): string
    {
        if ($this->type !== 'FileAttachment') {
            throw new BadMethodCallException('Call to method which only exists on FileAttachment');
        }

        return $this->client->attachment()->getByKey($this->attachment)['content'];
    }
}
