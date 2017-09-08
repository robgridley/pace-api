<?php

namespace Pace\Services;

use Finfo;
use Pace\Service;

class AttachmentService extends Service
{
    /**
     * Add a new attachment to the vault.
     *
     * @param string $object
     * @param mixed $key
     * @param string|null $attribute
     * @param string $name
     * @param string $content
     * @return string
     */
    public function add($object, $key, $attribute, $name, $content)
    {
        $attachment = [
            'name' => $name,
            'content' => base64_encode($content),
            'mimeType' => $this->guessMimeType($name, $content),
            'fileExtension' => pathinfo($name, PATHINFO_EXTENSION),
        ];

        $request = [
            'in0' => $object,
            'in1' => $key,
            'in2' => $attribute,
            'in3' => $attachment,
        ];

        $response = $this->soap->addAttachment($request);

        return $response->out;
    }

    /**
     * Get an attachment from the vault by the specified key.
     *
     * @param string $key
     * @return array
     */
    public function getByKey($key)
    {
        $request = ['in0' => $key];

        $response = $this->soap->getAttachmentFromKey($request);

        $attachment = (array)$response->out;
        $attachment['content'] = base64_decode($attachment['content']);

        return $attachment;
    }

    /**
     * Remove an attachment from the vault by the specified key.
     *
     * @param string $key
     */
    public function removeByKey($key)
    {
        $request = ['in0' => $key];

        $this->soap->removeAttachmentFromKey($request);
    }

    /**
     * Guess the MIME type for the specified file.
     *
     * @param string $name
     * @param string $content
     * @return string
     */
    protected function guessMimeType($name, $content)
    {
        $finfo = new Finfo(FILEINFO_MIME_TYPE);

        return $finfo->buffer($content) ?: 'application/octet-stream';
    }
}
