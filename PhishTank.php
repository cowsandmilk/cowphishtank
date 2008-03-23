<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Class for Talking to PhishTank using streams and generally returning
 * SimpleXML objects or PhishTank_Url objects
 * 
 * @author David Hall <dhall@wustl.edu>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link http://cowsandmilk.homedns.org/PhishTank/
 * @see PhishTank_Url
 */

/**
 * This says whether to use POST or get as the request method.
 * POST allows you to use any length of fields, urls, etc.
 * GET doesn't, so if anyone has any good reason to use GET, let me know
 */
define('REQUEST_METHOD','POST');

/**
 * A PHP translation of the PhishTank API with some helper methods
 * 
 * If you want installation help, see INSTALL
 * 
 * This class uses SimpleXML and doesn't use curl because I think curl is a bad
 * idea for forward compatibility with PHP.  So, wwe sacrifice some BC and look
 * ahead.  I'm a fan.
 * 
 * This needs 5.1.x or later because SimpleXML didn't like CDATA before then,
 * which PhishTank responds with.
 * 
 * In the spirit of being in love with SimpleXML, the configuration is read from
 * phishtank_config.xml and is stored as a SimpleXMLElement that you can read
 * all your wonderful variables from.
 * 
 * Work Needed
 * -----
 * I could add url format checking (ensure it is valid), but my preferred way is using
 * filters, which can be seen implemented in setup-examples.php, but Filter is only
 * standard in 5.2.x and beyond, and I didn't want that dependency.  Plus, PhishTank
 * does its own validation of urls.
 * 
 * Bugs
 * -----
 * - Many of the https stream functions seem to suffer same bug as described here:
 * http://bugs.php.net/bug.php?id=39039 
 * or maybe there's just something weird about PhishTank's https.  
 * This is corrected by supressing errors in PhishTank.php
 * -a lot of the PhishTank api isn't documented and I don't have time to document.
 * Thus, most error messages are not handled too well, especially those that
 * aren't xml.  In fact, I'd rather it return xml messages with an error
 * element or something.
 */
class PhishTank
{
    /**
     * Config file
     * 
     * This holds your app_key, shared_secret, frob, authorization_url,
     * api_key, and username.  You don't need to touch it though, you can get
     * to those things because of our wonderful __get below
     *
     * @var SimpleXMLElement
     */
    private $config;
    
    /**
     * Your token for the object
     * 
     * Everyone knows you're not supposed to keep your token, so we leave it out of
     * the config file.  You get it on construct, you lose it on destruct.
     *
     * @var string
     */
    private $token;
    
    /**
     * Set up your life for using PhishTank
     * 
     * The constructor does multiple things.
     * 
     * First, if your configuration file doesn't exist, it makes one for you.
     * This means if you ever dislike your config file, just delete it, a blank
     * one will be made for you
     * 
     * Second, it loads that config file into the class property for you
     * 
     * Third, if you have everything configured with the username and api_key,
     * it sets you up with your token.  Pretty jazzy.  I worry here whether someone
     * can set up their user and then want a token right then, but we'll see how we
     * solve that later.
     */
    public function __construct()
    {
        if (!file_exists('phishtank_config.xml')) {
            $this->createBlankConfig();
        }
        
        $this->config = simplexml_load_file('phishtank_config.xml');
        
        if ($this->isConfigured('user')) {
            $this->getToken();
        }
    }
    
    /**
     * Simple Magical Method to get variables
     * 
     * we have our variables sitting in a simplexmlelement object
     * and we need them.  so we can order them up right here.
     * whoo hoo.
     *
     * @param string $var
     * @return string
     */
    public function __get($var)
    {
        return (string) $this->config->$var;
    }
	
    /**
     * Set Up the configuration file
     * 
     * During Installation, there are essentially 3 steps:
     * 1) set up the application with its app_key and shared_secret
     * 2) use these to get a frob and authorization_url where the user can log in
     * 3) PhishTank returns the user's username along with an api_key
     * 
     * Thisw encompasses all three steps of this setup and stores the appropriate
     * values from these steps in the config file.
     *
     * @param string $section either application, frob, or user, depending on the step
     * @param array $params the application set up requires user parameters
     * @return bool whether or not the config file was put to disk
     */
    public function setup($section, $params = array())
    {
        switch ($section) {
        case 'application':
            $this->config->app_key = $params['app_key'];
            $this->config->shared_secret = $params['shared_secret'];
            return $this->config->asXML('phishtank_config.xml');
            break;
        case 'frob':
            $path = explode('/',$_SERVER['PHP_SELF']);
            array_pop($path);
            $path = implode('/',$path);
            $callback_url = 'http://'.rtrim($_SERVER['HTTP_HOST'],'/').$path.'/user-setup.php';
            $response = $this->auth_frob_request($callback_url);
            $this->config->frob = (string) $response->results->frob;
            $this->config->authorization_url = (string) $response->results->authorization_url;
            return $this->config->asXML('phishtank_config.xml');
            break;
        case 'user':
            $response = $this->auth_frob_status($this->frob);
            if((string) $response->results->status != 'approved'){
                return false;
			}
            $this->config->username = (string) $response->results->username;
            $this->config->api_key = (string) $response->results->apikey;
            $this->getToken();
            return $this->config->asXML('phishtank_config.xml');
            break;
        default:
            exit('Unknown Section '.$section);
        }
    }
    
    /**
     * sees if the sections from set up are properly configured
     * 
     * Essentially, we can tell if someone has already been through a phase based on
     * whether values have been entered.
     * 
     * Note that proper configuration of application must be linked to the verifyApp()
     * method which uses misc_ping to see if the provided values are valid.
     *
     * @param string $section either application, frob, or user, depending on the step
     * @return bool true if there are values for the section
     */
    public function isConfigured($section)
    {
        switch ($section) {
        case 'application':
            if (strlen($this->config->app_key) == 0
                || strlen($this->config->shared_secret) == 0) {
                    return false;
            }
            return true;
            break;
        case 'frob':
            if(strlen($this->config->frob) == 0
                || strlen($this->config->authorization_url) == 0) {
                    return false;
            }
            return true;
            break;
        case 'user':
            if(strlen($this->config->api_key) == 0
                || strlen($this->config->username) == 0) {
                    return false;
            }
            return true;
            break;
        default:
            exit('Unknown Section '.$section);
        }
    }
    
    /**
     * creates a blank config file for use
     * 
     * If there's no configuration file on disk, there's nothing to read when
     * looking for it.  So we create an xml file using DOM, then translate
     * that into simplexml where elements are added and then write
     * the xml file to disk.
     *
     * @return bool whether or not the config file was put to disk
     */
    private function createBlankConfig()
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($dom->createElement('config'));
        $config = simplexml_import_dom($dom);
        $config->addChild('app_key');
        $config->addChild('shared_secret');
        $config->addChild('frob');
        $config->addChild('authorization_url');
        $config->addChild('api_key');
        $config->addChild('username');
        return $config->asXML('phishtank_config.xml');
    }
	
    /**
     * Talk to PhishTank
     * 
     * The workhorse of the class, this thing is what talks to PhishTank
     * It takes in what you want to say to PhishTank, merges
     * that with your app_key and shared_secret, makes  a signiature
     * then either sends a POST or GET request over https and gets
     * either xml or a serialized array, which it either turns into
     * a SimpleXML object or deserializes the array.
     * 
     * Talking using https requires that you have OpenSSL enabled.
     * Also, certain https errors are consistently being returned, but the methods
     * work, so errors are suppressed for now until someone smart fixes it.
     *
     * @param array $parameters additional parameters such as kind of request and url
     * @param string $method POST or GET
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#connecting
     */
    private function sendAPI($parameters, $method = REQUEST_METHOD)
    {
        //This function requires having https as a stream protocol . . . check if it exists
        if(!in_array('https',stream_get_wrappers( ))) {
            exit('HTTPS required as a stream protocol, please recompile PHP with --with=openssl');
        }
        
        $sig_string = '';
        $defaultparameters = array(
            'app_key'         => $this->app_key,
			'responseformat'  => 'xml',
			'version'         => '1',
        );
        
        //merge the passed parameters on top of the default parameters, this allows replacement of defaults and adds any needed ones
        $parameters = array_merge($defaultparameters, $parameters); 
        
        ksort($parameters);
        foreach($parameters as $key=>$value) {
            $sig_string .= $key . $value;
        }
        $sig_string         = $this->shared_secret . $sig_string;
		$parameters['sig']  = md5($sig_string);
		$parameter_string   = http_build_query($parameters);
		
        switch (strtoupper($method)) {//strtoupper for people who do things like post
        case 'GET':
            $address = 'https://api.phishtank.com/api/?'.$parameter_string;
            if ($parameters['responseformat'] == 'xml') {
                return @simplexml_load_file($address,NULL,LIBXML_NOCDATA);
                //random fatal protocol error, hence the error suppressesion
            } elseif ($parameters['responseformat'] == 'php') {
                $opts = array(
                    'http' => array(
                        'method' => 'GET'
                    )
                );
                $context = stream_context_create($opts);
                return unserialize(file_get_contents($address, false, $context));
            } else {
                exit('Unkown Response Format');
            }
            break;
        case 'POST':
            $opts = array(
                'http'=>array(
                    'method'=>'POST',
                        'header'  => 'Content-type: application/x-www-form-urlencoded',
                        'content' => $parameter_string
                )
            );
            $context = stream_context_create($opts);
            
            $response =  @file_get_contents('https://api.phishtank.com/api/', false, $context);//see comment above about ssl errors
            if ($parameters['responseformat'] == 'xml') {
                return simplexml_load_string($response,NULL,LIBXML_NOCDATA);
            }elseif($parameters['responseformat'] == 'php') {
                return unserialize($response);
            }
            else {
                exit('Unkown Response Format '.$parameters['responseformat']);
            }
            break;
        default:
            exit('Unknown Request Method '.$method);
        }
    }
    
    /**
     * Implementation of PhishTank misc.ping
     * 
     * See if your app key and shared secret are right enough
     * for PhishTank to recognize the existence of your app
     * is used by verifyApp()
     *
     * @param string $responseformat
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#miscping
     */
    public function misc_ping($responseformat = '')
    {
        $parameters['action'] = 'misc.ping';
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Implementation of PhishTank auth.frob.request
     * 
     * Get yourself a frob, which the user can use to get an API
     * Key for your application
     * is handled well by setup('frob')
     *
     * @param string $callback_url
     * @param string $responseformat
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#authfrobrequest
     */
    public function auth_frob_request($callback_url='',$responseformat = '')
    {
        $parameters['action'] = 'auth.frob.request';
        if (strlen($callback_url) > 0) {
            $parameters['callback_url'] = $callback_url;
        }
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Implementation of PhishTank auth.frob.status
     * 
     * See if the frob has been used by the user to get
     * an API key, and then get that key and the username
     * is handled well by setup('user')
     *
     * @param string $frob
     * @param string $responseformat
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#authfrobstatus
     */
    public function auth_frob_status($frob, $responseformat='')
    {
        $parameters['action'] = 'auth.frob.status';
        $parameters['frob']   = $frob;
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Implementation of PhishTank auth.token.request
     * 
     * Get yourself a token so you can have yourself a session
     * This is private, but go hit up getToken which takes
     * what's returned from this and sets you up with everything
     * Or rather, the constructor uses getToken, so you don't have to do that.
     *
     * @param string $username username that you used to get your API Key
     * @param string $api_key API Key going with your username
     * @param string $responseformat specify either xml or php response
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#authtokenrequest
     */
    private function auth_token_request($username = '', $api_key = '', $responseformat='')
	{
        $parameters['action']   = 'auth.token.request';
        $parameters['username'] = ($username == '')
                                ? $this->username
                                : $username;
        $parameters['api_key']  = ($api_key == '')
                                ? $this->api_key
                                : $api_key;
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Implementation of PhishTank auth.tken.status
     * 
     * See if your token is still good
     * Hit up tokenGood() which tells you if the token is good
     *
     * @param string $token token given by PhishTank for the session
     * @param string $username username corresponding to the token
     * @param string $api_key API Key corresponding to the token
     * @param string $responseformat specify either xml or php response
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#authtokenstatus
     */
    public function auth_token_status($token = '', $username = '', $api_key = '', $responseformat='')
    {
        $parameters['action']   = 'auth.token.status';
        $parameters['token']    = ($token == '')
                                ? $this->token
                                : $token;
        $parameters['username'] = ($username == '')
                                ? $this->username
                                : $username;
        $parameters['api_key']  = ($api_key == '')
                                ? $this->api_key
                                : $api_key;
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Implementation of PhishTank auth.token.revoke
     * 
     * Give that token up once the session is over
     * called from __destruct
     *
     * @param string $token token given by PhishTank for the session
     * @param string $username username corresponding to the token
     * @param string $api_key API Key corresponding to the token
     * @param string $responseformat specify either xml or php response
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#authtokenrevoke
     */
    private function auth_token_revoke($token = '', $username = '', $api_key = '', $responseformat='')
    {
        $parameters['action']   = 'auth.token.revoke';
        $parameters['token']    = ($token == '')
                                ? $this->token
                                : $token;
        $parameters['username'] = ($username == '')
                                ? $this->username
                                : $username;
        $parameters['api_key']  = ($api_key == '')
                                ? $this->api_key
                                : $api_key;
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Implementation of PhishTank check.url
     * 
     * see if a url is in the PhishTank database and then see if its been verified
     * and also if its a valid Phish
     *
     * @param string $url url to check
     * @param string $token token given by PhishTank for the session
     * @param string $responseformat specify either xml or php response
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#checkurl
     */
    public function check_url($url, $token = '', $responseformat='')
    {
        $parameters['action'] = 'check.url';
        $parameters['url']    = $url;
        $parameters['token']  = ($token == '') 
                              ? $this->token
                              : $token;
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Implementation of PhishTank check.email
     * 
     * similar to check_url, instead scans the email for addresses
     * that it then sees if they're in the PhishTank database and if
     * they've been verified and are valid Phishes
     *
     * @param string $email text (and headers) of email to check
     * @param string $token token given by PhishTank for the session
     * @param string $responseformat specify either xml or php response
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#checkemail
     */
    public function check_email($email, $token = '', $responseformat='') {
        $parameters['action'] = 'check.email';
        $parameters['email']  = $email;
        $parameters['token']  = ($token == '')
                              ? $this->token
                              : $token;
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Implementation of PhishTank submit.url
     * 
     * You should use submitUrl, which is the kind method
     * that checks out whether or not the url is already in
     * the database
     * But yeah, this gives the url to PhishTank
     *
     * @param string $url url to submit
     * @param string $token token given by PhishTank for the session
     * @param string $username username corresponding to the token
     * @param string $responseformat specify either xml or php response
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#submiturl
     */
    private function submit_url($url, $token = '', $username = '', $responseformat='')
    {
        $parameters['action']   = 'submit.url';
        $parameters['url']      = $url;
        $parameters['token']    = ($token == '')
                                ? $this->token
                                : $token;
        $parameters['username'] = ($username == '')
                                ? $this->username
                                : $username;
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Implementation of PhishTank submit.email
     * 
     * you should use submitEmail(), which is the kind
     * version that checks if all the urls in the email
     * have already been submitted
     * but yeah, this submits the email
     *
     * @param string $email text (and headers) of email to submit
     * @param striing $token token given by PhishTank for the session
     * @param string $username username corresponding to the token
     * @param string $responseformat specify either xml or php response
     * @return SimpleXMLElement|array depends on the provided responseformat
     * @see http://www.phishtank.com/api_documentation.php#submitemail
     */
    private function submit_email($email, $token = '', $username = '', $responseformat='')
    {
        $parameters['action']   = 'submit.email';
        $parameters['email']    = $email;
        $parameters['token']    = ($token == '')
                                ? $this->token
                                : $token;
        $parameters['username'] = ($username == '')
                                ? $this->username
                                : $username;
        if (strlen($responseformat) > 0) {
            $parameters['responseformat'] = $responseformat;
        }
        return $this->sendAPI($parameters);
    }
    
    /**
     * Don't want the hassle of the tokens and don't need to submit?
     * 
     * This function uses the quick check schema with either
     * the preferred post method by default or the deprecated
     * GET method
     *
     * @param string $url url to check
     * @param string $method use POST (preferred) or GET to check url
     * @return SimpleXMLElement
     * @see http://www.phishtank.com/blog/2006/10/30/simple-developer-method-for-checking-individual-urls/
     */
    public static function simple_check_url($url, $method = REQUEST_METHOD)
    {
        $encoded_url = base64_encode($url);
        switch (strtoupper($method)) {
        case 'POST':
            $parameter_string = http_build_query(array('url'=>$encoded_url));
            $opts = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content'=>$parameter_string
                )
            );
            $context = stream_context_create($opts);
            
            $response = file_get_contents('http://checkurl.phishtank.com/checkurl/', false, $context);
            return simplexml_load_string($response,NULL,LIBXML_NOCDATA);
            break;
        case'GET':
            return simplexml_load_file('http://checkurl.phishtank.com/checkurl/?'.$parameter_string,'',LIBXML_NOCDATA);
            break;
        default:
            exit('Unknown Request Method '.$method);
        }
    }
    
    /**
     * See if Your App is Configured right
     * 
     * if you have a valid app_key and shared_secret, this will hook you
     * up with confirmation that PhishTank talks back.
     *
     * @return bool of whether PhishTank pinged back
     */
    public function verifyApp()
    {
        $response = $this->misc_ping();
        if (($response instanceof SimpleXMLElement)
            && is_numeric((string) $response->results->pong)) {
                return true;
        } else {
                return false;
        }
    }
    
    /**
     * Get the Token
     * 
     * This gets the token, then it stores it in a private variable
     * If a token is already set (length greater than 0) and is still good,
     * nothing happens
     */
    private function getToken()
    {
        if (strlen($this->token) == 0 || !$this->tokenGood()) {
            $response = $this->auth_token_request();
            $this->token = (string) $response->results->token;
        }
    }
    
    /**
     * Check if the token is good
     * 
     * using the auth_token_status() method, this checks out if the token you have
     * is still good
     *
     * @return bool of whether your token is still valid
     */
    private function tokenGood()
    {
        $response = $this->auth_token_status();
        if (($response instanceof SimpleXMLElement)
            && (string) $response->results->valid == 'true') {
                return true;
        } else {
                return false;
        }
    }
    
    /**
     * See if a URL is in PhishTank and other things
     * 
     * this gives you back a PhishTank_Url object, see its
     * class to see what's in there about the Url
     * this function uses the full api to carry this out with
     * tokens and all that jazz
     *
     * @param string $url the url of what you wanna check
     * @return PhishTank_Url
     */
    public function checkUrl($url)
    {
        $response = $this->check_url($url);
        return new PhishTank_Url($response->results->url0);
    }
    
    /**
     * No Token, but want to check a url?  use this
     *
     * @param string $url the url of what you wanna check
     * @return PhishTank_Url
     */
    public static function simpleCheckUrl($url)
    {
        $response = self::simple_check_url($url);
        return new PhishTank_Url($response->results->url0);
    }
    
    /**
     * Check an email to see if there are Phishes in the email
     *
     * @param string $email
     * @return array urls key holds an array of PhishTank_Urls objects,
     *                      inDatabase says if they were all in PhishTank already
     */
    public function checkEmail($email)
    {
        $check = $this->check_email($email);
        $urls = array();
        $inDatabase = true;
        foreach ($check->results->children() as $url) {
            $phishtankurl = new PhishTank_Url($url);
            if ($phishtankurl->in_database != 'true') {
                $inDatabase = false;
            }
            $urls[] = $phishtankurl;
        }
        return array(
            'urls' => $urls,
            'inDatabase' => $inDatabase
        );
    }
    
    /**
     * Submit a url to PhishTank, if its not already there
     *
     * @param string $url
     * @return PhishTank_Url for the url, be it the new one or the one already at PhishTank
     */
    public function submitUrl($url)
    {
        $checkURL = $this->checkUrl($url);
        if($checkURL->in_database == 'false') {
            $submit = $this->submit_url($url);
            return new PhishTank_Url($submit->results, 'submit');
        }else {
            return $checkURL;
        }
    }
    
    /**
     * see if the contents of an email are already in PhishTank, and if not,
     * submit
     * 
     * This is a little weird because I didn't understand why the response format for
     * submit_email only had one url, as opposed to check_email
     * 
     * I knew if all of the urls were in the database, it shouldn't be submitted, but
     * if any of the urls are not in the database, the whole email is submitted.
     * 
     * I wasn't sure if this was appropriate or if one url isn't there, should we then
     * use submit_url to send that url?  I don't know.  I don't care.
     *
     * @param string $email
     * @return array urls key holds an array of PhishTank_Urls objects,
     *                      inDatabase says if they were all in PhishTank already
     */
    public function submitEmail($email)
    {
        $check = $this->checkEmail($email);
        if ($check['inDatabase']) {
            return $check;
        } else {
            $submit = $this->submit_email($email);
            return array(
                'urls' => array(new PhishTank_Url($submit->results, 'submit')),
                'inDatabase' => $check['inDatabase']
            );
        }
    }
    
    /**
     * If there's a token, revoke it upon object destruction
     *
     */
    public function __destruct()
    {
        if (strlen($this->token) > 0) {
            $this->auth_token_revoke();
        }
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */
?>