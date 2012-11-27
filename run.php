<?php
require __DIR__ . '/config.php';
require __DIR__ . '/GoogleAnalyticsToGraphite.php';
require __DIR__ . '/GoogleAnalyticsAPI.php';

/********************************************************************
 *  Configure what gets pulled here.
 *
 *  Format is graphite name => array(
 *      'segment' => google analytics segment to pull,
 *      ['profile' => GA profile]
 *      ['metrics' => arrray(ga, metrics, to, pull)]   //max 10
 *  )
 *  @see http://code.google.com/apis/analytics/docs/gdata/gdataReferenceDataFeed.html#segment
 *
 *  Your data will show up as: google.analytics.name.* in graphite
 */
    $to_pull = array(
        'sitewide' => array('segment' => null),
        'facebook_referers' => array('segment' => 'dynamic::ga:source=@facebook.com', 'metrics' => array('ga:visitors')),
    );
/********************************************************************/

set_time_limit(60*60); //1 hour max (can change if many more segments are added)

date_default_timezone_set('UTC');

echo "Booting up the analytics to graphite piper... \n";

$days = array();
if(isset($argv[1])) {
    if (is_numeric($argv[1])) {
        echo "Running for the last ".abs($argv[1])." days\n";
        for ($past_days = abs($argv[1]); $past_days > 0; $past_days--) {
            $days[] = date('Y-m-d', time() - 60*60*24 * $past_days);
        }
    } elseif (strtotime($argv[1]) !== false) {
        echo "Running for {$argv[1]}\n";
        $days[] = $argv[1];
    }
    else {
        die("Unable to figure out what {$argv[1]} means\n");
    }
}
else {
    echo "No date given, running for yesterday\n";
    $days[] = date('Y-m-d', time() - 60*60*24);
}

$piper = new GoogleAnalyticsToGraphite(
    $config['graphite_host'], $config['graphite_port'],
    $config['ga_email'], $config['ga_password'], $config['default_ga_profile']
);

foreach( $days as $day ) {
    foreach( $to_pull as $name => $query ) {
        $segment = $query['segment'];
        $profile = $config['default_google_profile'];
        if( isset($query['profile']) ) {
            $profile = $query['profile'];
        }
        echo ' '.$day.': '.$name.' => '.$segment."\n";
        $metrics = $query['metrics'];
        $filters = $query['filters'];
        $dimensions = $query['dimensions'];
        $piper->pipeForDayToGraphite($day, $name, $segment, $profile, $metrics, $dimensions, $filters);
    }
}
echo "Done!\n";
