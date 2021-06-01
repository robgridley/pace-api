<?php

namespace Pace\Services;

use Pace\Service;

class ReportService extends Service
{
    /**
     * Execute the specified report.
     *
     * @param array $wrapper
     * @return array
     */
    public function executeReport(array $wrapper): array
    {
        $response = $this->soap->executeReport(['in0' => $wrapper]);

        return (array)$response->out;
    }

    /**
     * Print the specified report.
     *
     * @param array $wrapper
     */
    public function printReport(array $wrapper): void
    {
        $this->soap->printReport(['in0' => $wrapper]);
    }
}
