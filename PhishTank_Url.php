<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This takes the data from Phishtank about whether or not a url
 * is in the database, is verified, and is a Phish and turns
 * it into an easier to use form.
 * 
 * @author David Hall <dhall@wustl.edu>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link http://cowsandmilk.homedns.org/PhishTank/
 * @see PhishTank
 */
class PhishTank_Url {
	
    /**
     * The url, which you wouldn't necessarily know what it was in the case
     * of submitting an email
     *
     * @var string
     */
    public $url;
    
    /**
     * It should be obvious
     *
     * @var string
     */
    public $in_database;
    
    /**
     * If its in the database, this is the id
     *
     * @var string
     */
    public $phish_id;
    
    /**
     * If in the database, the url for the page for info and voting
     *
     * @var string
     */
    public $phish_detail_page;
    
    /**
     * Is it verified?  You have to ask other people what this means
     *
     * @var string
     */
    public $verified;
    
    /**
     * If verified, when
     *
     * @var string
     */
    public $verified_at;
    
    /**
     * Is it valid?  I think this says if its really a Phish
     *
     * @var string
     */
    public $valid;
    
    /**
     * when was it submitted?
     *
     * @var string
     */
    public $submitted_at;
    
    /**
     * If you are submitting urls, this says if they added the url to the database
     * The only reason I know not to accept is if its already there, and since
     * the methods in PhishTank check this, I think this should always be true,
     * but who knows??
     *
     * @var string
     */
    public $accepted;
	
    /**
     * This reads the simplexml object created from the xml response of OpenDNS and
     * puts everything into the object properties as strings.
     *
     * @param SimpleXMLElement $urlXML the response from OpenDNS
     * @param string $source Whether you were checking for a Phish or submitting a Phish
     */
    public function __construct($urlXML, $source = 'check')
    {
        if ($source == 'check') {
            $this->url = (string) $urlXML->url;
            $this->in_database = (string) $urlXML->in_database;
            $this->phish_id = ($urlXML->phish_id instanceof SimpleXMLElement)
                               ? (string) $urlXML->phish_id
                               : '';
            $this->phish_detail_page = ($urlXML->phish_detail_page instanceof SimpleXMLElement)
                                ? (string) $urlXML->phish_detail_page
                                : '';
            $this->verified = ($urlXML->verified instanceof SimpleXMLElement)
                                ? (string) $urlXML->verified
                                : '';
            $this->verified_at = ($urlXML->verified_at instanceof SimpleXMLElement)
                                ? (string) $urlXML->verified_at
                                : '';
            $this->valid = ($urlXML->valid instanceof SimpleXMLElement)
                                ? (string) $urlXML->valid
                                : '';
            $this->submitted_at = ($urlXML->submitted_at instanceof SimpleXMLElement)
                                ? (string) $urlXML->submitted_at
                                : '';
        } elseif ($source == 'submit') {
            $this->accepted = (string) $urlXML->accepted;
            $this->phish_id = ($urlXML->phish_id instanceof SimpleXMLElement)
                                ? (string) $urlXML->phish_id
                                : '';
            $this->phish_detail_page = ($urlXML->phish_id instanceof SimpleXMLElement)
                                ? 'http://www.phishtank.com/phish_detail.php?phish_id=' . (string) $urlXML->phish_id
                                : '';
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