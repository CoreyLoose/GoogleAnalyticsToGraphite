<?php

class GoogleAnalyticsToGraphite {
    /** @var float Graphite ignores values of 0, so we put in a real small value to get the datapoint recorded */
    const GRAPHITE_ALMOST_ZERO = 0.0001;

    private $ga_api;

    public $graphite_data_prefix = 'google.analytics.';

    /**
     * Google API caps at 10 metrics per request
     *
     * @var array
     */
    public $metrics_to_pull = array(
        'ga:newVisits', 'ga:visitors', 'ga:pageviews', 'ga:avgTimeOnSite',
        'ga:organicSearches', 'ga:bounces', 'ga:uniquePurchases',
        'ga:itemsPerPurchase', 'ga:transactionRevenue', 'ga:transactions'
    );

    public function __construct($graphite_host, $graphite_port, $ga_email, $ga_password, $default_ga_profile) {
        $this->graphite_connection = fsockopen($graphite_host, $graphite_port, $error_number, $error_string, 100);

        if (!$this->graphite_connection) {
            throw new Exception("Cannot connect to graphite: $error_string ($error_number)\n");
        }

        $this->ga_api = new GoogleAnalyticsAPI($ga_email, $ga_password, $default_ga_profile);
    }

    /**
     * Take metrics, by the hour, from google analytics and push them into graphite
     *
     * @param $pull_for_day
     * @param $segment_name
     * @param $segment
     * @param $profile
     * @param null $metrics
     * @param null $dimensions
     * @param null $filters
     */
    public function pipeForDayToGraphite(
        $pull_for_day,
        $segment_name,
        $segment,
        $profile,
        $metrics = null,
        $dimensions = null,
        $filters = null
    ) {
        if (!$metrics) {
            $metrics = $this->metrics_to_pull;
        }

        if (!$dimensions) {
            $dimensions = 'ga:hour';
        }
        $request_params = array(
            'ids' => $profile,
            'metrics' => implode(',', $metrics),
            'dimensions' => $dimensions,
            'start-date' => $pull_for_day,
            'end-date' => $pull_for_day,
            'max-results' => 50,
            'v' => 2
        );

        if ($segment !== null) {
            $request_params['segment'] = $segment;
        }

        if ($filters !== null) {
            $request_params['filters'] = $filters;
        }

        $analytics_data = $this->ga_api->query($request_params);

        if (!is_array($analytics_data['ga:hour'])) {
            throw new Exception('Google analytics data pull failed: ' . print_r($analytics_data, true));
        }

        $graphite_format = '';
        foreach ($analytics_data['ga:hour'] as $hour => $hour_data) {
            $time = strtotime($pull_for_day . ' ' . $hour . ':00:00');
            foreach ($hour_data as $metric_name => $metric_value) {
                if ($metric_value == '0' || $metric_value == '0.0') {
                    $metric_value = self::GRAPHITE_ALMOST_ZERO;
                }
                $graphite_format .= $this->graphite_data_prefix . $segment_name . '.' . $metric_name . ' ' . $metric_value . ' ' . $time . "\n";
            }
        }

        fwrite($this->graphite_connection, $graphite_format);
    }
}