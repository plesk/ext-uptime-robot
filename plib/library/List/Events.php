<?php
// Copyright 1999-2017. Plesk International GmbH.

class Modules_UptimeRobot_List_Events
{
    /**
     * @param $monitors
     * @param $view
     * @param $request
     *
     * @return pm_View_List_Simple
     */
    public static function getList($monitors, $view, $request)
    {
        $data = [];

        foreach ($monitors as &$monitor) {
            foreach ($monitor->logs as &$log) {
                $data[] = [
                    'column-1' => self::getHumandReadableEventTypeText($log->type),
                    'column-2' => Modules_UptimeRobot_List_Monitors::getHumandReadableURL($monitor->type, $monitor->url),
                    'column-3' => self::getHumandReadableDateTimeText($log->datetime),
                    'column-4' => $log->reason->detail,
                    'column-5' => self::getHumandReadableDurationText($log->duration)
                ];
            }
        }

        $sortBy = 'column-3';

        $options = [
            'pageable'             => true,
            'defaultItemsPerPage'  => 100,
            'defaultSortField'     => $sortBy,
            'defaultSortDirection' => pm_View_List_Simple::SORT_DIR_UP,
            'searchable'           => false
        ];

        $eventsList = new pm_View_List_Simple($view, $request, $options);
        $eventsList->setData($data);
        $eventsList->setColumns(
            [
                'column-1' => [
                    'title'      => pm_Locale::lmsg('overviewEventColEvent'),
                    'sortable'   => true,
                    'searchable' => false,
                    'noEscape'   => true
                ],
                'column-2' => [
                    'title'      => pm_Locale::lmsg('overviewEventColMonitor'),
                    'sortable'   => true,
                    'searchable' => false,
                    'noEscape'   => true
                ],
                'column-3' => [
                    'title'      => pm_Locale::lmsg('overviewEventColDateTime'),
                    'sortable'   => true,
                    'searchable' => false
                ],
                'column-4' => [
                    'title'      => pm_Locale::lmsg('overviewEventColReason'),
                    'sortable'   => true,
                    'searchable' => false
                ],
                'column-5' => [
                    'title'      => pm_Locale::lmsg('overviewEventColDuration'),
                    'sortable'   => true,
                    'searchable' => false
                ]
            ]);

        $eventsList->setDataUrl(array('action' => 'eventslist-data'));

        return $eventsList;
    }

    /**
     * @param $type
     *
     * @return string
     */
    private static function getHumandReadableEventTypeText($type)
    {
        $type = intval($type);

        switch ($type) {
            case 1:
                return '<span class="event eventOffline"></span>'.pm_Locale::lmsg('overviewEventOffline');
            case 2:
                return '<span class="event eventOnline"></span>'.pm_Locale::lmsg('overviewEventOnline');
            case 98:
                return '<span class="event eventPaused"></span>'.pm_Locale::lmsg('overviewEventPaused');
            case 99:
                return '<span class="event eventStarted"></span>'.pm_Locale::lmsg('overviewEventStarted');
        }

        return pm_Locale::lmsg('overviewEventUnknown');
    }

    /**
     * @param $dateTime
     *
     * @return false|string
     */
    private static function getHumandReadableDateTimeText($dateTime)
    {
        return date('Y-m-d H:i:s', $dateTime);
    }

    /**
     * @param $durationInSeconds
     *
     * @return string
     */
    public static function getHumandReadableDurationText($durationInSeconds)
    {
        $init = $durationInSeconds;

        $hours = floor($init / 3600);
        $minutes = floor(($init / 60) % 60);
        $seconds = $init % 60;

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
}
