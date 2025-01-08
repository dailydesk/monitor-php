<?php

namespace DailyDesk\Monitor;

use Inspector\Configuration as BaseConfiguration;

class Configuration extends BaseConfiguration
{
    protected $url = 'https://monitor.dailydesk.app';
    protected $ingestionKey;
    protected $enabled = true;
    protected $maxItems = 1000;
    protected $version = 'dev-main';
    protected $options = [];
}
