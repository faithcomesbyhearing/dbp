<?php

namespace App\Logs;

use Monolog\Formatter\LineFormatter;

class CloudfrontApiKeyFormatter
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        $format = "%datetime% %message% %context% %extra%\n";
        $date_format = 'Y-m-d H:m:s';

        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new LineFormatter(
                $format,
                $date_format,
                false,
                true
            ));
        }
    }
}
