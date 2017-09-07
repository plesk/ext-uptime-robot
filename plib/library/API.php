<?php
// Copyright 1999-2017. Plesk International GmbH.

/**
 * Class Modules_UptimeRobot_API
 *
 * Helper class for the Uptime Robot API
 */
class Modules_UptimeRobot_API
{
    /**
     * Fetches the account data for the transmitted API key
     *
     * @param string $apikey
     *
     * @return mixed|stdClass
     */
    public static function fetchUptimeRobotAccount($apikey)
    {
        return self::doApiCallCurl($apikey, 'https://api.uptimerobot.com/v2/getAccountDetails');
    }

    /**
     * Fetches the account stats for the transmitted API key
     *
     * @param string $apikey
     *
     * @return mixed|stdClass
     */
    public static function fetchUptimeRobotAccountStat($apikey)
    {
        $response = self::doApiCallCurl($apikey, 'https://api.uptimerobot.com/v2/getAccountDetails');

        if (!empty($response->stat)) {
            return $response->stat;
        }

        return new stdClass();
    }

    /**
     * Fetches the account information for the transmitted API key
     *
     * @param string $apikey
     *
     * @return mixed|stdClass
     */
    public static function fetchUptimeRobotAccountDetails($apikey)
    {
        $response = self::doApiCallCurl($apikey, 'https://api.uptimerobot.com/v2/getAccountDetails');

        if (!empty($response->account)) {
            return $response->account;
        }

        return new stdClass();
    }

    /**
     * Fetches all monitors with logs for the transmitted API key
     *
     * @param string $apikey
     *
     * @return mixed|stdClass
     */
    public static function fetchUptimeMonitors($apikey)
    {
        $response = self::doApiCallCurl($apikey, 'https://api.uptimerobot.com/v2/getMonitors', array('logs' => 1));

        if (!empty($response->monitors)) {
            return $response->monitors;
        }

        return array();
    }

    /**
     * Helper function for the cURL request to the Uptime Robot API
     *
     * @param string $apikey
     * @param string $command
     * @param array  $post_fields_extra
     *
     * @return mixed|stdClass
     */
    private static function doApiCallCurl($apikey, $command, $post_fields_extra = array())
    {
        $post_fields = array(
            'api_key' => $apikey,
            'format'  => 'json'
        );

        if (!empty($post_fields_extra)) {
            $post_fields = array_merge($post_fields, $post_fields_extra);
        }

        $curl = curl_init();
        curl_setopt_array(
            $curl, array(
            CURLOPT_URL            => $command,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => http_build_query($post_fields),
            CURLOPT_HTTPHEADER     => array(
                'cache-control: no-cache',
                'content-type: application/x-www-form-urlencoded'
            )
        ));

        if (pm_ProductInfo::isWindows()) {
            $caPath = __DIR__ . '/externals/cacert.pem';
            $caPath = str_replace('/', DIRECTORY_SEPARATOR, $caPath);

            curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
            curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,1);
            curl_setopt($curl,CURLOPT_CAINFO,$caPath);
            curl_setopt($curl,CURLOPT_CAPATH,$caPath);
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return new stdClass();
        }

        return json_decode($response);
    }
}
