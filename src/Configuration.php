<?php

namespace DailyDesk\Monitor;

use Inspector\Configuration as BaseConfiguration;

class Configuration extends BaseConfiguration
{
    public const VERSION = '0.1.1';

    protected $version = self::VERSION;
    protected $url = 'https://monitor.dailydesk.app';
    protected $ingestionKey;
    protected $enabled = true;
    protected $maxItems = 1000;
    protected $options = [];
}
