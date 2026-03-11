<?php

namespace Mollie\Payment\Adapter;

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Mollie\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Mollie\Infrastructure\Logger\LogData;
use Mollie\Infrastructure\Logger\LoggerConfiguration;

class JoomlaLoggerAdapter implements ShopLoggerAdapter
{
    private static bool $initialized = false;

    public function __construct()
    {
        $this->initializeLogger();
    }

    /**
     * @return void
     */
    private function initializeLogger(): void
    {
        if (self::$initialized) {
            return;
        }

        Log::addLogger(
            [
                'text_file' => 'com_mollie.php',
                'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'
            ],
            Log::ALL,
            ['com_mollie']
        );

        self::$initialized = true;
    }

    /**
     * @param   LogData  $data
     * @return  void
     */
    public function logMessage(LogData $data): void
    {
        $config = LoggerConfiguration::getInstance();
        if ($data->getLogLevel() > $config->getMinLogLevel()) {
            return;
        }

        $priority = $this->mapLogLevel($data->getLogLevel());

        $message = sprintf(
            '[%s] [%s] %s',
            $data->getIntegration(),
            $data->getComponent(),
            $data->getMessage()
        );

        if (!empty($data->getContext())) {
            $contextData = [];
            foreach ($data->getContext() as $contextItem) {
                $contextData[$contextItem->getName()] = $contextItem->getValue();
            }
            $message .= ' | Context: ' . json_encode($contextData);
        }

        Log::add($message, $priority, 'com_mollie');
    }

    /**
     * @param   int  $logLevel
     *
     * @return  int
     */
    private function mapLogLevel(int $logLevel): int
    {
        return match ($logLevel) {
            0 => Log::ERROR,
            1 => Log::WARNING,
            2 => Log::INFO,
            3 => Log::DEBUG,
            default => Log::INFO,
        };
    }
}
