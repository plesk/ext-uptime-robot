<?php
// Copyright 1999-2017. Plesk International GmbH.

require_once('UptimeRobotAPI.php');

class IndexController extends pm_Controller_Action
{
    const DEFAULT_TIMESPAN = 30;

    public function init()
    {
        parent::init();

        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl().'/css/styles.css');
        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl().'/css/circle.css');

        $this->view->pageTitle = 'Uptime Robot';
        $this->view->tabs = [
            [
                'title' => pm_Locale::lmsg('overviewTitle'),
                'action' => 'overview',
            ],
            [
                'title' => pm_Locale::lmsg('settingsTitle'),
                'action' => 'settings',
            ]
        ];
    }

    public function indexAction()
    {

        if(pm_Settings::get('apikey')){
            $account = UptimeRobotAPI::fetchUptimeRobotAccount(pm_Settings::get('apikey'));

            if ($account->stat == 'ok'){
                $this->_forward('overview');
                return;
            }

            $this->_forward('setup');
            return;
        }

        $this->_forward('setup');
    }

    public function setupAction()
    {
        $apiKey = pm_Settings::get('apikey') ? pm_Settings::get('apikey') : '';
        $this->view->apikeyForm = new pm_Form_Simple();
        $this->view->apikeyForm->addElement('text', 'apikey', [ 'label' => pm_Locale::lmsg('setupApiKeyInputLabel'), 'required' => true, 'value' => $apiKey ]);
        $this->view->apikeyForm->addControlButtons(['cancelHidden' => true, 'sendTitle' => pm_Locale::lmsg('setupApiKeySaveButton')]);

        if ($this->getRequest()->isPost() && $this->view->apikeyForm->isValid($this->getRequest()->getPost())) {
            $apikey = $this->view->apikeyForm->getValue('apikey');
            pm_Settings::set('apikey', trim($apikey));

            if ($apikey){
                $account = UptimeRobotAPI::fetchUptimeRobotAccount($apikey);
                if ($account->stat == 'ok'){
                    $this->_status->addMessage('info', pm_Locale::lmsg('setupApiKeySaved'));
                } else {
                    $this->_status->addError(pm_Locale::lmsg('setupApiKeyInvalid').json_encode($account));
                }
            }
            
            $this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);
        }
    }

    public function settingsAction()
    {
        $apiKey = pm_Settings::get('apikey') ? pm_Settings::get('apikey') : '';
        $this->view->apikeyForm = new pm_Form_Simple();
        $this->view->apikeyForm->addElement('text', 'apikey', [ 'label' => 'API-Key', 'value' => $apiKey ]);
        $this->view->apikeyForm->addControlButtons(['cancelHidden' => true, 'sendTitle' => 'Save']);
       
        if ($this->getRequest()->isPost() && $this->view->apikeyForm->isValid($this->getRequest()->getPost())) {
            $apikey = $this->view->apikeyForm->getValue('apikey');
            pm_Settings::set('apikey', trim($apikey));

            if ($apikey){
                $account = UptimeRobotAPI::fetchUptimeRobotAccount($apikey);
                if ($account->stat == 'ok'){
                    $this->_status->addMessage('info', pm_Locale::lmsg('setupApiKeySaved'));
                } else {
                    $this->_status->addError(pm_Locale::lmsg('setupApiKeyInvalid'));
                }
            }
            
            $this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);
            return;
        }

        $account = UptimeRobotAPI::fetchUptimeRobotAccountDetails($apiKey);
        $this->view->accountForm = new pm_Form_Simple();
    	$this->view->accountForm->addElement('text', 'email', ['label' => pm_Locale::lmsg('settingsMail'), 'value' => $account->email , 'readonly' => true ]);
    	$this->view->accountForm->addElement('text', 'limit', ['label' => pm_Locale::lmsg('settingsMonitorLimit'), 'value' => $account->monitor_limit , 'readonly' => true ]);
    	$this->view->accountForm->addElement('text', 'interval', ['label' => pm_Locale::lmsg('settingsMonitorInterval'), 'value' => $account->monitor_interval , 'readonly' => true ]);
    	$this->view->accountForm->addElement('text', 'interval', ['label' => pm_Locale::lmsg('settingsUpMonitor'), 'value' => $account->up_monitors , 'readonly' => true ]);
    	$this->view->accountForm->addElement('text', 'interval', ['label' => pm_Locale::lmsg('settingsDownMonitor'), 'value' => $account->down_monitors , 'readonly' => true ]);
    	$this->view->accountForm->addElement('text', 'interval', ['label' => pm_Locale::lmsg('settingsPausedMonitor'), 'value' => $account->paused_monitors , 'readonly' => true ]);
    }

    public function overviewAction()
    {
        $timespan = self::DEFAULT_TIMESPAN;
        if ($this->getRequest()->getQuery('timespan')){
            $timespan = intval($this->getRequest()->getQuery('timespan'));
        }

        $monitors =  UptimeRobotAPI::fetchUptimeMonitors(pm_Settings::get('apikey'));
        $this->view->timespan = $timespan;
        $this->view->globalUptimePercentage = $this->_attachUptimePercentageToMonitors($monitors, $timespan);
        $this->view->monitorsList = $this->_getMonitorsList($monitors);
        $this->view->eventsList = $this->_getEventsList($monitors);

        $chartData = $this->_getChartDataFor($monitors, $timespan);
        $this->view->chartData = $chartData['data'];
        $this->view->chartMinRange = max(0, $chartData['minRange'] - 5);

        $this->view->monitors = $monitors;
    }


    ////////////
    // EVENTS //
    ////////////
    public function eventslistDataAction()
    {
        $monitors =  UptimeRobotAPI::fetchUptimeMonitors(pm_Settings::get('apikey'));
        $list = $this->_getEventsList($monitors);
        $this->_helper->json($list->fetchData());
    }

    private function _getEventsList($monitors)
    {
        $data = [];
        foreach ($monitors as &$monitor) {
            foreach ($monitor->logs as &$log) {
                $data[] = [
                    'column-1' => $this->_getHTMLByEventType($log->type),
                    'column-2' => '<a href="'.$monitor->url.'" target="_blank">'.$monitor->url.'</a>',
                    'column-3' => $this->_getHTMLByDateTime($log->datetime),
                    'column-4' => $log->reason->detail,
                    'column-5' => $this->_getHTMLByDuration($log->duration)
                ];                
            }
        }

        $sortBy = 'column-3';

        $options = [
            'pageable' => false,
            'defaultItemsPerPage' => 100,
            'defaultSortField' => $sortBy,
            'defaultSortDirection' => pm_View_List_Simple::SORT_DIR_UP,
            'searchable' => false
        ];

        $eventsList = new pm_View_List_Simple($this->view, $this->_request, $options);
        $eventsList->setData($data);
        $eventsList->setColumns([
            'column-1' => [
                'title' => pm_Locale::lmsg('overviewEventColEvent'),
                'sortable' => true,
                'searchable' => false,
                'noEscape' => true
            ], 
            
            'column-2' => [
                'title' =>  pm_Locale::lmsg('overviewEventColMonitor'),
                'sortable' => true,
                'searchable' => false,
                'noEscape' => true
            ], 
            
            'column-3' => [
                'title' =>  pm_Locale::lmsg('overviewEventColDateTime'),
                'sortable' => true,
                'searchable' => false
            ],

            'column-4' => [
                'title' =>  pm_Locale::lmsg('overviewEventColReason'), 
                'sortable' => true,
                'searchable' => false
            ],

            'column-5' => [
                'title' => pm_Locale::lmsg('overviewEventColDuration'),
                'sortable' => true,
                'searchable' => false
            ]
        ]);
        $eventsList->setDataUrl(array('action' => 'eventslist-data'));

        return $eventsList;
    }

    private function _getHTMLByEventType($type)
    {
        $type = intval($type);

        switch ($type) {
            case 1:
                return '<span class="event eventOffline"></span>'.pm_Locale::lmsg('overviewEventOffline');
            case 2:
                return '<span class="event eventOnline"></span>'.pm_Locale::lmsg('overviewEventOnline');                
            case 98:
                return '<span class="event eventStarted"></span>'.pm_Locale::lmsg('overviewEventStarted');
        }

        return 'Unknown Event Type';
    }

    private function _getHTMLByDateTime($dateTime)
    {
        return date('Y-m-d H:i:s', $dateTime);
    }

    private function _getHTMLByDuration($durationInSeconds)
    {
        $init = $durationInSeconds;

        $hours = floor($init / 3600);
        $minutes = floor(($init / 60) % 60);
        $seconds = $init % 60;

        $output = '';

        if($hours < 10){ $output .= '0'; }
        $output .= $hours.'h, ';
        
        if($minutes < 10){ $output .= '0'; }
        $output .= $minutes.'m';

        return $output;
    }


    //////////////
    // MONITORS //
    //////////////
    private function _getMonitorsList($monitors)
    {
    	$data = [];
        foreach ($monitors as &$monitor) {
            $data[] = [
                'column-1' => $monitor->id,
                'column-2' => '<a href="'.$monitor->url.'" target="_blank">'.$monitor->url.'</a>',
                'column-3' => ' ',
                'column-4' => $monitor->uptime[24].'%',
                'column-5' => $monitor->uptime[24*60].'%',
                'column-6' => $monitor->uptime[24*360].'%'
            ];
        }
        
        $options = [
            'pageable' => false,
            'defaultItemsPerPage' => 100
        ];

        $monitorsList = new pm_View_List_Simple($this->view, $this->_request, $options);
        $monitorsList->setData($data);
        $monitorsList->setColumns([
            'column-1' => [
                'title' => 'ID',
                'noEscape' => true,
                'searchable' => false
            ], 
            
            'column-2' => [
                'title' => 'URL',
                'noEscape' => true,
                'sortable' => true,
                'searchable' => false
            ],

            'column-3' => [
                'title' => 'Uptime: ',
                'noEscape' => true,
                'sortable' => false,
                'searchable' => false
            ],

            'column-4' => [
                'title' => 'last 24 '.pm_Locale::lmsg('overviewMonitorsHours'),
                'noEscape' => true,
                'searchable' => false,
                'sortable' => false
            ],

            'column-5' => [
                'title' => 'last 60 '.pm_Locale::lmsg('overviewMonitorsDays'),
                'noEscape' => true,
                'searchable' => false,
                'sortable' => false
            ],

            'column-6' => [
                'title' => 'last 360 '.pm_Locale::lmsg('overviewMonitorsDays'),
                'noEscape' => true,
                'searchable' => false,
                'sortable' => false
            ]
        ]);
        $monitorsList->setDataUrl(array('action' => 'monitorslist-data'));
    

        return $monitorsList;
    }

    public function monitorslistDataAction()
    {
        $monitors =  UptimeRobotAPI::fetchUptimeMonitors(pm_Settings::get('apikey'));
        $list = $this->_getMonitorsList($monitors);
        $this->_helper->json($list->fetchData());
    }

    ///////////
    // Chart //
    ///////////
    public function _getChartDataFor($monitors, $timespan)
    {
        $lastXDays = $this->_getLastDays($timespan);
        $monitorsLength = count($monitors);

        $yOnline = [];
        $yOffline = [];
        $textsOffline = [];
        $textsOnline = [];

        $minOnlinePercentage = 100;

        foreach($lastXDays as $currentDay){

            $duration = 0;
            $textOffline = '';
            $textsOnline = '';
            foreach ($monitors as &$monitor) {
                foreach ($monitor->logs as &$log) {
                    if($currentDay == date('Y-m-d', $log->datetime) && $log->type == 1){
                        $duration += ($log->duration/60/60); //seconds => hours
                        $textOffline .= $monitor->url.': '.($this->_getHTMLByDuration($log->duration)).'<br>';
                    }
                }
            }

            $offlinePercentage = ($duration / (24*$monitorsLength)) * 100;
            $onlinePercentage = 100 - $offlinePercentage;
            $minOnlinePercentage = min($minOnlinePercentage, $onlinePercentage);
            $yOffline[] = $offlinePercentage.'%';
            $yOnline[] = $onlinePercentage.'%';
            $textsOffline[] = $textOffline;
            $textsOnline[] = $textsOnline;
        }

        $data = [];

        $data[] = array(
            'x' => $lastXDays,  
            'y' => $yOnline,
            'name' => pm_Locale::lmsg('overviewGraphOnline'),
            'type' => 'bar',
            'hoverinfo' => 'text',
            'text' => $textsOnline,
            'marker' => [
                'color' => 'rgb(182, 240, 125)'
            ]
        );

        $data[] = array(
            'x' => $lastXDays,  
            'y' => $yOffline,
            'name' =>  pm_Locale::lmsg('overviewGraphOffline'),
            'type' => 'bar',
            'hoverinfo' => 'text',
            'text' => $textsOffline,
            'marker' => [
                'color' => 'rgb(240, 125, 125)'
            ]
        );

        return [
            'data' => $data,
            'minRange' => $minOnlinePercentage
        ];
    }

    private function _getLastDays($daysAmount)
    {
        $days = array();
        for($i = $daysAmount; $i >= 0; $i--){
            $days[] = date("Y-m-d", strtotime('-'. $i .' days'));
        }

        return $days;
    }


    ///////////////////
    // Global Uptime //
    ///////////////////
    private function _attachUptimePercentageToMonitors(&$monitors, $timespan){

        // 24 hours, 7 days, 30 days, 60 days, 180 days, 360 days
        $perdiods = [24, 24*7, 24*30, 24*60, 24*180, 24*360];
        $timespan = 24 * $timespan;

        $globalUptimes = [];

        foreach ($monitors as &$monitor) {

            $uptimeMap = [];

            foreach($perdiods as &$period){
                $durations = $this->_getOverallUptime($monitor, $period);

                // Init global uptime for period
                if(!$globalUptimes[$period]){
                    $globalUptimes[$period] = [];
                    $globalUptimes[$period]['online'] = 0;
                    $globalUptimes[$period]['offline'] = 0;
                }

                // Add to global uptime for each period seperated
                $globalUptimes[$period]['online'] += $durations['durationOnline'];
                $globalUptimes[$period]['offline'] += $durations['durationOffline'];

                // Calculate monitor uptime
                $uptimePercentage = $this->_calculateUptimePercentage($durations['durationOnline'], $durations['durationOffline']);
                $uptimePercentage = round($uptimePercentage,2,PHP_ROUND_HALF_DOWN);
                $uptimeMap[$period] = $uptimePercentage;
            }

            $monitor->uptime = $uptimeMap;
        }

        $timespanUptimePercentage = $this->_calculateUptimePercentage($globalUptimes[$timespan]['online'], $globalUptimes[$timespan]['offline']);
        return round($timespanUptimePercentage,2,PHP_ROUND_HALF_DOWN);
    }

    private function _getOverallUptime(&$monitor, $withinTheLastHours){

        // calculate the timestamp from where the stats will be calculated
        $date = new DateTime();
        $tosub = new DateInterval('PT'.$withinTheLastHours.'H');
        $date->sub($tosub);
        $x = $date->getTimestamp();

        $durationOffline = 0;
        $durationOnline = 0;

        // sort by datetime asc
        usort($monitor->logs, function ($a, $b)
        {
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
            if($monitor->logs[$i]->datetime > $x){
                if($i - 1 >= 0){
                    $index = $i - 1; // keep the entry before also, there a splitted result will be calculated
                } else {
                    $index = $i;
                }
                break;
            }
        }

        if($index == -1){
            $index = $length - 1;
        }

        // collect data
        $first = true;
        for($j = $index; $j < $length; $j++){

            // calculate the first splitted time and add to offline or online sum
            if($first == true){
                $delta = $x - $monitor->logs[$j]->datetime;
                $splitted = $monitor->logs[$j]->duration - $delta;
                if ($monitor->logs[$j]->type == 1){
                    $durationOffline += $splitted;
                } else if ($monitor->logs[$j]->type == 2){
                    $durationOnline += $splitted;
                }

                $first = false;
                continue;
            }

            // add the rest of the entries
            if ($monitor->logs[$j]->type == 1){
                $durationOffline += $monitor->logs[$j]->duration;
            } else if ($monitor->logs[$j]->type == 2){
                $durationOnline += $monitor->logs[$j]->duration;
            }
        }

        return [
            'durationOffline' => $durationOffline,
            'durationOnline' => $durationOnline
        ];
    }

    private function _calculateUptimePercentage($durationOnline, $durationOffline){
        $sum = $durationOffline + $durationOnline;
        $overallUptimePercentage = ($durationOnline / $sum) * 100;
        return $overallUptimePercentage;
    }
}
