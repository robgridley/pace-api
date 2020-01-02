<?php

namespace Pace\Report;

use finfo;

class File
{
    /**
     * The file's content.
     *
     * @var string
     */
    protected $content;

    /**
     * The file's media type.
     *
     * @var string|null
     */
    protected $mediaType;

    /**
     * Create a new file instance.
     *
     * @param string $content
     * @param string|null $mediaType
     */
    public function __construct(string $content, string $mediaType = null)
    {
        $this->content = $content;
        $this->mediaType = $mediaType;
    }

    /**
     * Create a new file instance from a Base64-encoded file.
     *
     * @param string $content
     * @param string|null $mediaType
     * @return static
     */
    public static function fromBase64(string $content, string $mediaType = null): self
    {
        return new static(base64_decode($content), $mediaType);
    }

    /**
     * Get the file's media type.
     *
     * @return string
     */
    public function getMediaType(): string
    {
        if (!is_null($this->mediaType)) {
            return $this->mediaType;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        return $finfo->buffer(substr($this->content, 0, 65536)) ?: 'application/octet-stream';
    }

    /**
     * Get the file's content.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
