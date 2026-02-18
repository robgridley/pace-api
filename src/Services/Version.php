<?php

namespace Pace\Services;

use Pace\Service;

class Version extends Service
{
    /**
     * Get the Pace version.
     *
     * @return array
     */
    public function get(): array
    {
        $response = $this->soap->getVersion();

        $version['string'] = $response->out;

        if (preg_match('/(\d+)\.(\d+)\-(\d+)/', $version['string'], $matches)) {
            $keys = ['major', 'minor', 'patch'];
            $values = array_map('intval', array_slice($matches, 1));
            $version = array_merge($version, array_combine($keys, $values));
        }

        return $version;
    }
}
