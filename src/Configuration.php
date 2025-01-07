<?php

namespace DailyDesk\Monitor;

use Inspector\Configuration as BaseConfiguration;

class Configuration extends BaseConfiguration
{
    protected $url = 'http://chasingdata.test'; // -> https://monitor.dailydesk.app

    protected $version = 'dev-main';
}
