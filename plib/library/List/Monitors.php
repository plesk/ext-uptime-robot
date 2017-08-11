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
                'column-2' => '<a href="'.$monitor->url.'" target="_blank">'.$monitor->url.'</a>',
                'column-3' => ' ',
                'column-4' => $monitor->uptime[24].'%',
                'column-5' => $monitor->uptime[24 * 60].'%',
                'column-6' => $monitor->uptime[24 * 360].'%'
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
                    'title'      => 'ID',
                    'noEscape'   => true,
                    'searchable' => false
                ],

                'column-2' => [
                    'title'      => 'URL',
                    'noEscape'   => true,
                    'sortable'   => true,
                    'searchable' => false
                ],

                'column-3' => [
                    'title'      => 'Uptime: ',
                    'noEscape'   => true,
                    'sortable'   => false,
                    'searchable' => false
                ],

                'column-4' => [
                    'title'      => 'last 24 '.pm_Locale::lmsg('overviewMonitorsHours'),
                    'noEscape'   => true,
                    'searchable' => false,
                    'sortable'   => false
                ],

                'column-5' => [
                    'title'      => 'last 60 '.pm_Locale::lmsg('overviewMonitorsDays'),
                    'noEscape'   => true,
                    'searchable' => false,
                    'sortable'   => false
                ],

                'column-6' => [
                    'title'      => 'last 360 '.pm_Locale::lmsg('overviewMonitorsDays'),
                    'noEscape'   => true,
                    'searchable' => false,
                    'sortable'   => false
                ]
            ]);
        $monitorsList->setDataUrl(array('action' => 'monitorslist-data'));

        return $monitorsList;
    }
}
