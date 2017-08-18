<?php
// Copyright 1999-2017. Plesk International GmbH.

class Modules_UptimeRobot_List_Monitors
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
            $data[] = [
                'column-1' => $monitor->id,
                'column-2' => self::getHumandReadableMonitorType($monitor->type),
                'column-3' => self::getHumandReadableURL($monitor->type, $monitor->url),
                'column-4' => ' ',
                'column-5' => $monitor->uptime[24].'%',
                'column-6' => $monitor->uptime[24 * 60].'%',
                'column-7' => $monitor->uptime[24 * 360].'%'
            ];
        }

        $options = [
            'pageable'            => false,
            'defaultItemsPerPage' => 100
        ];

        $monitorsList = new pm_View_List_Simple($view, $request, $options);
        $monitorsList->setData($data);
        $monitorsList->setColumns(
            [
                'column-1' => [
                    'title'      => pm_Locale::lmsg('overviewMonitorID'),
                    'noEscape'   => true,
                    'searchable' => false
                ],

                'column-2' => [
                    'title'      => pm_Locale::lmsg('overviewMonitorType'),
                    'noEscape'   => true,
                    'sortable'   => true,
                    'searchable' => false
                ],

                'column-3' => [
                    'title'      =>  pm_Locale::lmsg('overviewMonitorURL'),
                    'noEscape'   => true,
                    'sortable'   => true,
                    'searchable' => false
                ],

                'column-4' => [
                    'title'      => pm_Locale::lmsg('overviewMonitorUptime'),
                    'noEscape'   => true,
                    'sortable'   => false,
                    'searchable' => false
                ],

                'column-5' => [
                    'title'      => pm_Locale::lmsg('overviewMonitorLast24Hours'),
                    'noEscape'   => true,
                    'searchable' => false,
                    'sortable'   => false
                ],

                'column-6' => [
                    'title'      => pm_Locale::lmsg('overviewMonitorLast60days'),
                    'noEscape'   => true,
                    'searchable' => false,
                    'sortable'   => false
                ],

                'column-7' => [
                    'title'      => pm_Locale::lmsg('overviewMonitorLast360days'),
                    'noEscape'   => true,
                    'searchable' => false,
                    'sortable'   => false
                ]
            ]);
        $monitorsList->setDataUrl(array('action' => 'monitorslist-data'));

        return $monitorsList;
    }

    /**
     * @param $monitorType
     *
     * @return string
     */
    private static function getHumandReadableMonitorType($monitorType) {
        switch ($monitorType) {
            case 1:
                return pm_Locale::lmsg('overviewMonitorTypeHttp');
            case 1:
                return pm_Locale::lmsg('overviewMonitorTypeKeyword');
            case 2:
                return pm_Locale::lmsg('overviewMonitorTypePing');
            case 3:
                return pm_Locale::lmsg('overviewMonitorTypePort');
            default:
                return pm_Locale::lmsg('overviewMonitorTypeUnknown');
        }
    }

    /**
     * @param $monitorType
     * @param $monitorUrl     
     *
     * @return string Returns a hyperref or just text depending on the $monitorType.
     */
    public static function getHumandReadableURL($monitorType, $monitorUrl) {

        // 3 => Ping
        if ($monitorType === 3) {
            return $monitorUrl;
        }

        // Starts with http or https
        if (substr($monitorUrl, 0, 7) == 'http://' || substr($monitorUrl, 0, 8) == 'https://') {
            return '<a href="'.$monitorUrl.'" target="_blank">'.$monitorUrl.'</a>';
        }

        return $monitorUrl;
    }
}
