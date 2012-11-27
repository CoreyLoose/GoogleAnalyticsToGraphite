<?php
class GoogleAnalyticsAPI {

    public $goog_auth_url = 'https://www.google.com/accounts/ClientLogin';
    public $goog_api_url = 'https://www.google.com/analytics/feeds/data';

    private $google_analytics_auth_token;

    private $default_ga_profile;

    private $google_analytics_auth = array(
      'accountType' => 'GOOGLE',
      'source' => 'GAPI-1.3',
      'service' => 'analytics'
    );

    public function __construct($ga_email, $ga_pasword, $default_ga_profile) {
        $this->google_analytics_auth['Email'] = $ga_email;
        $this->google_analytics_auth['Passwd'] = $ga_pasword;
        $this->default_ga_profile = $default_ga_profile;
        $this->google_analytics_auth_token = $this->authenticateWithGoogleAnalytics();
    }

    public function query( $request_params ) {

        if ( !isset($request_params['ids']) ) {
            $request_params['ids'] = $this->default_ga_profile;
        }
        if ( !isset($request_params['v']) ) {
            $request_params['v'] = 2;
        }

        $request_url = $this->goog_api_url.'?'.http_build_query($request_params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: GoogleLogin auth='.$this->google_analytics_auth_token['Auth']));
        $response = curl_exec($ch);
        curl_close($ch);

        $xml_response = simplexml_load_string($response);

        return $this->parseAnalyticsXmlResponse($xml_response);
    }

    public function closeConnection() {
        fclose($this->graphite_connection);
    }

    private function authenticateWithGoogleAnalytics() {
        $curl_auth = curl_init();
        $auth_request_url = $this->goog_auth_url.'?'.http_build_query($this->google_analytics_auth);
        curl_setopt($curl_auth, CURLOPT_URL, $auth_request_url);
        curl_setopt($curl_auth, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_auth, CURLOPT_SSL_VERIFYPEER, false);
        $auth_response = curl_exec($curl_auth);
        curl_close($curl_auth);

        //Convert newline delimited variables into url format then import to array
        parse_str(str_replace(array("\n","\r\n"), '&', $auth_response), $auth_token);

        if ( !is_array($auth_token) || empty($auth_token['Auth']) ) {
            throw new Exception('Unable to authenticate with google analytics');
        }

        return $auth_token;
    }

    /**
     * Analytics sends back xml that looks like:
     * <feed>
     *  <entry>
     *   <id>http://www.google.com/analytics/feeds/data?ids=ga:4473919&amp;ga:hour=01&amp;start-date=2011-10-01&amp;end-date=2011-11-01</id>
     *   <updated>2011-10-31T17:00:00.001-07:00</updated>
     *   <title type="text">ga:hour=01</title>
     *   <link rel="alternate" type="text/html" href="http://www.google.com/analytics"/>
     *   <dxp:dimension name="ga:hour" value="01"/>
     *   <dxp:metric confidenceInterval="0.0" name="ga:newVisits" type="integer" value="618684"/>
     *   <dxp:metric confidenceInterval="0.0" name="ga:visitors" type="integer" value="1813048"/>
     *  </entry>
     *  ...
     * </feed>
     *
     * Parse this out into an array that looks like:
     * array(
     *  'dimension name' => array(
     *     'metric name' => metric value
     *     ...
     *   )
     *   ...
     * )
     *
     * @param $xml_response
     * @return array
     */
    private function parseAnalyticsXmlResponse( $xml_response ) {
        $data = array();
        foreach ( $xml_response->children() as $child ) {
            if ( $child->getName() != 'entry' ) {
                continue;
            }
            $dimensions = $child->xpath('dxp:dimension');
            $metrics = $child->xpath('dxp:metric');

            foreach ( $dimensions as $dimension ) {
                $dimension_attributes = $dimension->attributes();
                $dimension_name = (string)$dimension_attributes->name;
                $dimension_value = (string)$dimension_attributes->value;
                if ( !isset($dimension_name) ) {
                    $data[$dimension_name] = array();
                }
                $data[$dimension_name][$dimension_value] = array();
                foreach ( $metrics as $metic ) {
                    $metric_attributes = $metic->attributes();
                    $metric_name = (string)$metric_attributes->name;
                    $metric_value = (string)$metric_attributes->value;
                    $data[$dimension_name][$dimension_value][$metric_name] = $metric_value;
                }
            }
        }
        return $data;
    }
}
