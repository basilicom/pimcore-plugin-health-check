<?php

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\RobotsTxtNotAvailableException;
use Exception;

class RobotsTxtCheck implements CheckInterface
{
    use ConfigurationTrait;

    public function check(): void
    {
        try {
            $robotsArray = [];
            $robotsTxt = fopen($_SERVER['DOCUMENT_ROOT'] . '/robots.txt', 'r');
            while (!feof($robotsTxt)) {
                $robotsArray[] = fgets($robotsTxt);
            }
            fclose($robotsTxt);
        } catch (Exception) {
            throw new RobotsTxtNotAvailableException("robots.txt is not available or not readable.");
        }

        foreach ($robotsArray as $robotsString) {
            if (strtolower(str_replace(' ', '', $robotsString)) === 'disallow:/') {
                throw new RobotsTxtNotAvailableException('robots.txt disallows whole domain.');
            }
        }
    }

    public function isActive(): bool
    {
        return $this->isEnabled('robots_txt_check_enabled');
    }
}