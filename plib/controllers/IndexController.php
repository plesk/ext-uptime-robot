<?php
// Copyright 1999-2017. Plesk International GmbH.

/**
 * Class IndexController
 */
class IndexController extends pm_Controller_Action
{
    protected $_accessLevel = ['admin'];
    private $api_key;
    const DEFAULT_TIMESPAN = 30;

    /**
     * Initialize controller
     */
    public function init()
    {
        parent::init();

        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl().'/css/styles.css');
        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl().'/css/circle.css');

        $this->api_key = pm_Settings::get('apikey', '');

        $this->view->pageTitle = 'Uptime Robot';
        $this->view->tabs = [
            [
                'title'  => pm_Locale::lmsg('overviewTitle'),
                'action' => 'overview',
            ],
            [
                'title'  => pm_Locale::lmsg('settingsTitle'),
                'action' => 'settings',
            ]
        ];
    }

    /**
     * Index Action
     */
    public function indexAction()
    {
        if ($this->api_key) {
            $account = Modules_UptimeRobot_API::fetchUptimeRobotAccount($this->api_key);

            if ($account->stat == 'ok') {
                $this->_forward('overview');

                return;
            }
        }

        $this->_forward('setup');
    }

    /**
     * Setup Action
     */
    public function setupAction()
    {
        $this->view->apikeyForm = new pm_Form_Simple();
        $this->view->apikeyForm->addElement(
            'text', 'apikey', [
            'label'    => pm_Locale::lmsg('setupApiKeyInputLabel'),
            'required' => true,
            'value'    => $this->api_key
        ]);
        $this->view->apikeyForm->addControlButtons(
            [
                'cancelHidden' => true,
                'sendTitle'    => pm_Locale::lmsg('setupApiKeySaveButton')
            ]);

        if ($this->getRequest()->isPost() && $this->view->apikeyForm->isValid($this->getRequest()->getPost())) {
            $api_key = $this->view->apikeyForm->getValue('apikey');
            pm_Settings::set('apikey', trim($api_key));

            if ($api_key) {
                $account = Modules_UptimeRobot_API::fetchUptimeRobotAccount($api_key);
                if ($account->stat == 'ok') {
                    $this->_status->addMessage('info', pm_Locale::lmsg('setupApiKeySaved'));
                } else {
                    $this->_status->addError(pm_Locale::lmsg('setupApiKeyInvalid').json_encode($account));
                }
            }

            $this->_helper->json(
                [
                    'redirect' => pm_Context::getBaseUrl()
                ]);
        }
    }

    /**
     * Settings Action
     */
    public function settingsAction()
    {
        $this->view->apikeyForm = new pm_Form_Simple();
        $this->view->apikeyForm->addElement(
            'text', 'apikey', [
            'label' => 'API-Key',
            'value' => $this->api_key
        ]);
        $this->view->apikeyForm->addControlButtons(
            [
                'cancelHidden' => true,
                'sendTitle'    => 'Save'
            ]);

        if ($this->getRequest()->isPost() && $this->view->apikeyForm->isValid($this->getRequest()->getPost())) {
            $api_key = $this->view->apikeyForm->getValue('apikey');
            pm_Settings::set('apikey', trim($api_key));

            if ($api_key) {
                $account = Modules_UptimeRobot_API::fetchUptimeRobotAccount($api_key);

                if ($account->stat == 'ok') {
                    $this->_status->addMessage('info', pm_Locale::lmsg('setupApiKeySaved'));
                } else {
                    $this->_status->addError(pm_Locale::lmsg('setupApiKeyInvalid'));
                }
            }

            $this->_helper->json(
                [
                    'redirect' => pm_Context::getBaseUrl()
                ]);

            return;
        }

        $account = Modules_UptimeRobot_API::fetchUptimeRobotAccountDetails($this->api_key);
        $this->view->accountForm = new pm_Form_Simple();
        $this->view->accountForm->addElement(
            'text', 'email', [
            'label'    => pm_Locale::lmsg('settingsMail'),
            'value'    => $account->email,
            'readonly' => true
        ]);
        $this->view->accountForm->addElement(
            'text', 'limit', [
            'label'    => pm_Locale::lmsg('settingsMonitorLimit'),
            'value'    => $account->monitor_limit,
            'readonly' => true
        ]);
        $this->view->accountForm->addElement(
            'text', 'interval', [
            'label'    => pm_Locale::lmsg('settingsMonitorInterval'),
            'value'    => $account->monitor_interval,
            'readonly' => true
        ]);
        $this->view->accountForm->addElement(
            'text', 'interval', [
            'label'    => pm_Locale::lmsg('settingsUpMonitor'),
            'value'    => $account->up_monitors,
            'readonly' => true
        ]);
        $this->view->accountForm->addElement(
            'text', 'interval', [
            'label'    => pm_Locale::lmsg('settingsDownMonitor'),
            'value'    => $account->down_monitors,
            'readonly' => true
        ]);
        $this->view->accountForm->addElement(
            'text', 'interval', [
            'label'    => pm_Locale::lmsg('settingsPausedMonitor'),
            'value'    => $account->paused_monitors,
            'readonly' => true
        ]);
    }

    /**
     * Overview Action
     */
    public function overviewAction()
    {
        $timespan = self::DEFAULT_TIMESPAN;

        if ($this->getRequest()->getQuery('timespan')) {
            $timespan = intval($this->getRequest()->getQuery('timespan'));
        }

        $monitors = Modules_UptimeRobot_API::fetchUptimeMonitors($this->api_key);
        $this->view->timespan = $timespan;
        $this->view->globalUptimePercentage = $this->_attachUptimePercentageToMonitors($monitors, $timespan);
        $this->view->monitorsList = Modules_UptimeRobot_List_Monitors::getList($monitors, $this->view, $this->_request);
        $this->view->eventsList = Modules_UptimeRobot_List_Events::getList($monitors, $this->view, $this->_request);

        $chartData = $this->_getChartDataFor($monitors, $timespan);
        $this->view->chartData = $chartData['data'];
        $this->view->chartMinRange = max(0, $chartData['minRange'] - 5);

        $this->view->monitors = $monitors;
    }

    /**
     * Events List Data Action
     */
    public function eventslistDataAction()
    {
        $monitors = Modules_UptimeRobot_API::fetchUptimeMonitors($this->api_key);
        $list = Modules_UptimeRobot_List_Events::getList($monitors, $this->view, $this->_request);
        $this->_helper->json($list->fetchData());
    }

    /**
     * Monitors List Data Action
     */
    public function monitorslistDataAction()
    {
        $monitors = Modules_UptimeRobot_API::fetchUptimeMonitors($this->api_key);
        $this->_attachUptimePercentageToMonitors($monitors);
        $list = Modules_UptimeRobot_List_Monitors::getList($monitors, $this->view, $this->_request);
        $this->_helper->json($list->fetchData());
    }

    /**
     * Crates the chart data
     *
     * @param $monitors
     * @param $timespan
     *
     * @return array
     */
    public function _getChartDataFor($monitors, $timespan)
    {
        $lastXDays = $this->_getLastDays($timespan);
        $monitorsLength = count($monitors);

        $yOnline = [];
        $yOffline = [];
        $textsOffline = [];
        $textsOnline = [];

        $minOnlinePercentage = 100;

        foreach ($lastXDays as $currentDay) {
            $duration = 0;
            $textOffline = '';
            $textsOnline = '';

            foreach ($monitors as &$monitor) {
                foreach ($monitor->logs as &$log) {
                    if ($currentDay == date('Y-m-d', $log->datetime) && $log->type == 1) {
                        $duration += ($log->duration / 60 / 60); //seconds => hours
                        $textOffline .= $monitor->url.': '.($this->_getHTMLByDuration($log->duration)).'<br>';
                    }
                }
            }

            if ($monitorsLength === 0) {
                $offlinePercentage = 0;    
            } else {
                $offlinePercentage = ($duration / (24 * $monitorsLength)) * 100;
            }

            $onlinePercentage = 100 - $offlinePercentage;
            $minOnlinePercentage = min($minOnlinePercentage, $onlinePercentage);
            $yOffline[] = $offlinePercentage.'%';
            $yOnline[] = $onlinePercentage.'%';
            $textsOffline[] = $textOffline;
            $textsOnline[] = $textsOnline;
        }

        $data = [];

        $data[] = array(
            'x'         => $lastXDays,
            'y'         => $yOnline,
            'name'      => pm_Locale::lmsg('overviewGraphOnline'),
            'type'      => 'bar',
            'hoverinfo' => 'text',
            'text'      => $textsOnline,
            'marker'    => [
                'color' => 'rgb(182, 240, 125)'
            ]
        );

        $data[] = array(
            'x'         => $lastXDays,
            'y'         => $yOffline,
            'name'      => pm_Locale::lmsg('overviewGraphOffline'),
            'type'      => 'bar',
            'hoverinfo' => 'text',
            'text'      => $textsOffline,
            'marker'    => [
                'color' => 'rgb(240, 125, 125)'
            ]
        );

        return [
            'data'     => $data,
            'minRange' => $minOnlinePercentage
        ];
    }

    /**
     * Get HTML by duration in seconds
     *
     * @param $durationInSeconds
     *
     * @return string
     */
    private function _getHTMLByDuration($durationInSeconds)
    {
        $hours = floor($durationInSeconds / 3600);
        $minutes = floor(($durationInSeconds / 60) % 60);
        $seconds = $durationInSeconds % 60;

        $output = '';

        if ($hours < 10) {
            $output .= '0';
        }

        $output .= $hours.'h, ';

        if ($minutes < 10) {
            $output .= '0';
        }

        $output .= $minutes.'m';

        return $output;
    }

    /**
     * Gets the last days for the chart
     *
     * @param $daysAmount
     *
     * @return array
     */
    private function _getLastDays($daysAmount)
    {
        $days = array();

        for ($i = $daysAmount; $i >= 0; $i--) {
            $days[] = date("Y-m-d", strtotime('-'.$i.' days'));
        }

        return $days;
    }

    /**
     * Creates the global uptime percentage for monitors
     *
     * @param $monitors
     * @param $timespan
     *
     * @return float
     */
    private function _attachUptimePercentageToMonitors(&$monitors, $timespan = 30)
    {
        // 24 hours, 7 days, 30 days, 60 days, 180 days, 360 days
        $perdiods = [
            24,
            24 * 7,
            24 * 30,
            24 * 60,
            24 * 180,
            24 * 360
        ];
        $timespan = 24 * $timespan;

        $globalUptimes = [];

        foreach ($monitors as &$monitor) {
            $monitor->uptime = [];

            // Do not check when monitor is paused
            // 0 = paused; 1 = not checked yet; 2 = up; 8 = seems down; 9 = down
            if($monitor->status === 0){
                $monitor->uptime = false;
                continue;
            }

            foreach ($perdiods as &$period) {
                $durations = $this->_getOverallUptime($monitor, $period);

                // Init global uptime for period
                if (array_key_exists($period, $globalUptimes) == false) {
                    $globalUptimes[$period] = [];
                    $globalUptimes[$period]['online'] = 0;
                    $globalUptimes[$period]['offline'] = 0;
                }
                
                // Add to global uptime for each period seperated
                $globalUptimes[$period]['online'] += $durations['durationOnline'];
                $globalUptimes[$period]['offline'] += $durations['durationOffline'];

                // Calculate monitor uptime
                $uptimePercentage = $this->_calculateUptimePercentage($durations['durationOnline'], $durations['durationOffline']);
                $uptimePercentage = round($uptimePercentage, 2, PHP_ROUND_HALF_DOWN);
                $monitor->uptime[$period] = $uptimePercentage;
            }
        }

        if (($globalUptimes[$timespan]['online'] + $globalUptimes[$timespan]['offline']) === 0) {
            return false;
        }

        $timespanUptimePercentage = $this->_calculateUptimePercentage($globalUptimes[$timespan]['online'], $globalUptimes[$timespan]['offline']);
        return round($timespanUptimePercentage, 2, PHP_ROUND_HALF_DOWN);
    }

    /**
     * Gets the overall uptime value
     *
     * @param $monitor
     * @param $withinTheLastHours
     *
     * @return array
     */
    private function _getOverallUptime(&$monitor, $withinTheLastHours)
    {
        // calculate the timestamp from where the stats will be calculated
        $date = new DateTime();
        $tosub = new DateInterval('PT'.$withinTheLastHours.'H');
        $date->sub($tosub);
        $x = $date->getTimestamp();

        $durationOffline = 0;
        $durationOnline = 0;

        // sort by datetime asc
        usort(
            $monitor->logs, function($a, $b) {
            if ($a->datetime == $b->datetime) {
                return 0;
            }

            return ($a->datetime < $b->datetime) ? -1 : 1;
        });

        $index = -1;

        // detect the index where to start from
        $length = count($monitor->logs);

        for ($i = 0; $i < $length; $i++) {

            // care about all entries that are later then x, but also take the last one that was smaller then x
            if ($monitor->logs[$i]->datetime > $x) {
                if ($i - 1 >= 0) {
                    $index = $i - 1; // keep the entry before also, there a splitted result will be calculated
                } else {
                    $index = $i;
                }

                break;
            }
        }

        if ($index == -1) {
            $index = $length - 1;
        }

        // collect data
        $first = true;

        for ($j = $index; $j < $length; $j++) {

            // calculate the first splitted time and add to offline or online sum
            if ($first == true) {
                $delta = $x - $monitor->logs[$j]->datetime;
                $splitted = $monitor->logs[$j]->duration - $delta;

                if ($monitor->logs[$j]->type == 1) {
                    $durationOffline += $splitted;
                } else if ($monitor->logs[$j]->type == 2) {
                    $durationOnline += $splitted;
                }

                $first = false;
                continue;
            }

            // add the rest of the entries
            if ($monitor->logs[$j]->type == 1) {
                $durationOffline += $monitor->logs[$j]->duration;
            } else if ($monitor->logs[$j]->type == 2) {
                $durationOnline += $monitor->logs[$j]->duration;
            }
        }

        return [
            'durationOffline' => $durationOffline,
            'durationOnline'  => $durationOnline
        ];
    }

    /**
     * Calculates the uptime percentage
     *
     * @param $durationOnline
     * @param $durationOffline
     *
     * @return float|int
     */
    private function _calculateUptimePercentage($durationOnline, $durationOffline)
    {
        if($durationOnline === 0 && $durationOffline === 0){
            return 100; // 
        }

        $sum = $durationOffline + $durationOnline;
        $overallUptimePercentage = ($durationOnline / $sum) * 100;

        return $overallUptimePercentage;
    }
}
