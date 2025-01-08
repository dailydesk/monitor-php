<?php

namespace DailyDesk\Monitor;

use Inspector\Configuration as BaseConfiguration;

class Configuration extends BaseConfiguration
{
    public const VERSION = '1.x-dev';

    protected $version = self::VERSION;
    protected $url = 'https://monitor.dailydesk.app';
    protected $ingestionKey;
    protected $enabled = true;
    protected $maxItems = 1000;
    protected $options = [];
}
