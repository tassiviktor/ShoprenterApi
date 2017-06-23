<?php

/**
 * Requestor exeption
 */
class RequestorException extends Exception {
    
}

/**
 * Low level api requestor
 * 
 * @author Viktor Tassi <contact@tassiviktor.hu>
 * @copyright  2017 Viktor Tassi
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @version    Release: V2.1
 * 
 */
class RequestorV2 {

    /**
     * Shoprenter V2 API url scheme
     */
    CONST URL = 'http://%s.api.shoprenter.hu';

    /**
     * Secure API URL
     */
    CONST SURL = 'https://%s.api.shoprenter.hu';

    /**
     * Supported response formats
     */
    CONST FORMAT_JSON = 'json'; //Default;
    CONST FORMAT_XML = 'xml';

    /**
     * Default useragent to shown
     */
    CONST DEFAULT_USERAGENT = 'ApiRequestor 2.1 (C) Viktor Tassi';

    /**
     * Real Shoprenter API URL
     * 
     * @var string 
     */
    protected $url;

    /**
     * API username
     * 
     * @var string 
     */
    protected $url_username;

    /**
     * API key
     * 
     * @var string 
     */
    protected $url_password;

    /**
     * Useragent to shown 
     * @var string
     */
    protected $url_useragent;

    /**
     * Response format
     * 
     * @var string 
     */
    protected $responseFormat = 'json';

    /**
     * Process response.
     * 
     * Process result to array when JSON, SimpleXML object when xml
     * 
     * @var bool 
     */
    protected $processResponse = TRUE;

    /**
     * Storage for last response
     * 
     * @var array|Object 
     */
    public $response = NULL;

    /**
     * Contstructor
     * 
     * @param string $shopName
     * @param string $userName
     * @param string $apiKey
     * @param string|null $userAgent
     */
    public function __construct($userName, $apiKey = '', $shopName, $userAgent = NULL, $secure = FALSE) {
        $this->url = sprintf($secure ? self::SURL : self::URL, $shopName);
        $this->url_username = $userName;
        $this->url_password = $apiKey;
        $this->url_useragent = $userName ? $userAgent : self::DEFAULT_USERAGENT;
    }

    /**
     * Execute API call
     * 
     * Here's an example of how to use:
     * 
     * <code>
     * 
     * require_once 'Requestorv2.php';
     * 
     * $requestorv2 = new RequestorV2('your.username', 'yourapikey', 'yourshopname');
     * 
     * $response = $requestorv2->setResponseFormat(RequestorV2::FORMAT_JSON)
     *              ->setProcessResponse(TRUE)
     *              ->execute('GET', '/manufacturers');
     * 
     * // Or simply:
     * 
     * $response = $requestorv2->execute('GET', '/manufacturers');
     * 
     * </code>
     * 
     * @param string $method one of GET POST PUT DELETE
     * @param string $url Endpoint URL. FQDN or relative url can be used.
     * @param array $data
     */
    public function execute($method, $url, array $data = array()) {
        $ch = curl_init();

        $url = $this->completeEndpointURL($url);

        curl_setopt($ch, CURLOPT_URL, $url);
        $this->setupCurl($ch);

        switch ($method) {
            case 'GET':
                //
                break;
            case 'POST':
                $query = http_build_query(array('data' => $data));
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
                break;
            case 'PUT':
                $query = http_build_query(array('data' => $data));
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                throw new RequestorException('Unknown HTTP method.');
        }

        //
        $this->response = curl_exec($ch);

        // On hard errors we thrown exceptions
        if (curl_errno($ch)) {
            throw new RequestorException(curl_error($ch));
        }

        curl_close($ch);
        $this->processResponse();
        return $this->response;
    }

    /**
     * Allows to use relative or absolute URIs easily
     * 
     * @param string $url
     * @return string
     */
    public function completeEndpointURL($url) {
        return stripos($url, 'http') === 0 ? $url : (rtrim($this->url, '/') . '/' . ltrim($url, '/'));
    }

    /**
     * Setup CURL parameters. 
     * 
     * @param resource $ch CURL Resource handler
     */
    protected function setupCurl(&$ch) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->url_useragent);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->url_username . ":" . $this->url_password);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_CRLF, TRUE);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type: multiform/post-data"]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/" . $this->responseFormat]);
    }

    /**
     * Preprocess response when required
     * 
     * @throws RequestorException
     */
    protected function processResponse() {
        if ($this->processResponse) {
            switch ($this->responseFormat) {
                case static::FORMAT_JSON:
                    $this->response = json_decode($this->response, TRUE);
                    break;
                case static::FORMAT_XML:
                    $this->response = $this->parseXml($this->response);
                    break;
                default :
                    throw new RequestorException('Unknown response format. Cannot process.');
            }
        }
    }

    /**
     * Set response format 
     * 
     * @param string $format
     * @return \RequestorV2
     * @throws RequestorException
     */
    public function setResponseFormat($format) {
        if (!in_array($format, [static::FORMAT_JSON, static::FORMAT_XML])) {
            throw new RequestorException('Unknown response format.');
        }
        $this->responseFormat = $format;
        return $this;
    }

    /**
     * Tells requestor to preprocess result or not
     * 
     * @param boolean $process
     * @return \RequestorV2
     */
    public function setProcessResponse($process) {
        $this->processResponse = !!($process);
        return $this;
    }

    /**
     * Generate simplexml object from result
     * 
     * @param string $xml
     * @return \SimpleXMLElement
     * @throws RequestorException
     */
    protected function parseXml($xml) {
        $xml = new SimpleXMLElement($xml, LIBXML_NOCDATA);
        if (!($xml instanceof SimpleXMLElement)) {
            throw new RequestorException('Cannot parse XML data.');
        }
        return $xml;
    }

}
