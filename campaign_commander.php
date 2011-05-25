<?php

/**
 * Campaign Commander class
 *
 * This source file can be used to communicate with Campaign Commander (http://campaigncommander.com)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-campaign-commander-member-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * Changelog since 1.0.0
 * - modified the class to reflect the current API.
 * - implemented all Message-methods.
 * - implemented all URL-management-methods.
 * - implemented all test-group-methods.
 * - implemented all segment-methods.
 * - implemented all campaign-methods.
 * - implemented all banner-methods.
 *
 * License
 * Copyright (c), Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author			Tijs Verkoyen <php-campaign-commander-member@verkoyen.eu>
 * @version			1.1.0
 *
 * @copyright		Copyright (c), Tijs Verkoyen. All rights reserved.
 * @license			BSD License
 */
class CampaignCommander
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// URL for the api
	const WSDL_URL = 'apiccmd/services/CcmdService?wsdl';

	// current version
	const VERSION = '1.1.0';


	/**
	 * The API-key that will be used for authenticating
	 *
	 * @var	string
	 */
	private $key;


	/**
	 * The login that will be used for authenticating
	 *
	 * @var	string
	 */
	private $login;


	/**
	 * The password that will be used for authenticating
	 *
	 * @var	string
	 */
	private $password;


	/**
	 * The server to use
	 *
	 * @var	string
	 */
	private $server = 'http://emvapi.emv3.com';


	/**
	 * The SOAP-client
	 *
	 * @var	SoapClient
	 */
	private $soapClient;


	/**
	 * The token
	 *
	 * @var	string
	 */
	private $token = null;


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 60;


	/**
	 * The user agent
	 *
	 * @var	string
	 */
	private $userAgent;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string $login		Login provided for API access.
	 * @param	string $password	The password.
	 * @param	string $key			Manager Key copied from the CCMD web application.
	 */
	public function __construct($login, $password, $key, $server = null)
	{
		$this->setLogin($login);
		$this->setPassword($password);
		$this->setKey($key);
		if($server !== null) $this->setServer($server);
	}


	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		// is the connection open?
		if($this->soapClient !== null)
		{
			try
			{
				// close
				$this->closeApiConnection();
			}

			// catch exceptions
			catch(Exception $e)
			{
				// do nothing
			}

			// reset vars
			$this->soapClient = null;
			$this->token = null;
		}
	}


	/**
	 * Make the call
	 *
	 * @return	mixed
	 * @param	string $method					The method to be called.
	 * @param	array[optional] $parameters		The parameters.
	 */
	private function doCall($method, array $parameters = array())
	{
		// open connection if needed
		if($this->soapClient === null || $this->token === null)
		{
			// build options
			$options = array('soap_version' => SOAP_1_1,
							 'trace' => self::DEBUG,
							 'exceptions' => false,
							 'connection_timeout' => $this->getTimeOut(),
							 'user_agent' => $this->getUserAgent());

			// create client
			$this->soapClient = new SoapClient($this->getServer() . '/' . self::WSDL_URL, $options);

			// build login parameters
			$loginParameters['login'] = $this->getLogin();
			$loginParameters['pwd'] = $this->getPassword();
			$loginParameters['key'] = $this->getKey();

			// make the call
			$response = $this->soapClient->__soapCall('openApiConnection', array($loginParameters));

			// validate
			if(is_soap_fault($response))
			{
				// init var
				$message = 'Internal Error';

				// more detailed message available
				if(isset($response->detail->ConnectionServiceException->description)) $message = (string) $response->detail->ConnectionServiceException->description;

				// internal debugging enabled
				if(self::DEBUG)
				{
					echo '<pre>';
					echo 'last request<br />';
					var_dump($this->soapClient->__getLastRequest());
					echo 'response<br />';
					var_dump($response);
					echo '</pre>';
				}

				// throw exception
				throw new CampaignCommanderException($message);
			}

			// validate response
			if(!isset($response->return)) throw new CampaignCommanderException('Invalid response');

			// set token
			$this->token = (string) $response->return;
		}

		// redefine
		$method = (string) $method;
		$parameters = (array) $parameters;

		// loop parameters
		foreach($parameters as $key => $value)
		{
			// strings should be UTF8
			if(gettype($value) == 'string') $parameters[$key] = utf8_encode($value);
		}

		// add token
		$parameters['token'] = $this->token;

		// make the call
		$response = $this->soapClient->__soapCall($method, array($parameters));

		// validate response
		if(is_soap_fault($response))
		{
			// init var
			$message = 'Internal Error';

			// more detailed message available
			if(isset($response->detail->ConnectionServiceException->description)) $message = (string) $response->detail->ConnectionServiceException->description;
			if(isset($response->detail->MemberServiceException->description)) $message = (string) $response->detail->MemberServiceException->description;
			if(isset($response->detail->CcmdServiceException->description))
			{
				$message = (string) $response->detail->CcmdServiceException->description;
				if(isset($response->detail->CcmdServiceException->fields)) $message .= ' fields: ' . $response->detail->CcmdServiceException->fields;
				if(isset($response->detail->CcmdServiceException->status)) $message .= ' status: ' . $response->detail->CcmdServiceException->status;
			}

			// internal debugging enabled
			if(self::DEBUG)
			{
				echo '<pre>';
				var_dump(htmlentities($this->soapClient->__getLastRequest()));
				var_dump($this);
				echo '</pre>';
			}

			// throw exception
			throw new CampaignCommanderException($message);
		}

		// empty reply
		if(!isset($response->return)) return null;

		// return the response
		return $response->return;
	}


	/**
	 * Get the key
	 *
	 * @return	string
	 */
	private function getKey()
	{
		return (string) $this->key;
	}


	/**
	 * Get the login
	 *
	 * @return	string
	 */
	private function getLogin()
	{
		return (string) $this->login;
	}


	/**
	 * Get the password
	 *
	 * @return	string
	 */
	private function getPassword()
	{
		return $this->password;
	}


	/**
	 * Get the server
	 *
	 * @return	string
	 */
	private function getServer()
	{
		return $this->server;
	}


	/**
	 * Get the timeout that will be used
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the useragent that will be used. Our version will be prepended to yours.
	 * It will look like: "PHP Campaign Commander/<version> <your-user-agent>"
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP Campaign Commander/' . self::VERSION . ' ' . $this->userAgent;
	}


	/**
	 * Set the Key that has to be used
	 *
	 * @return	void
	 * @param	string $key		The key to set.
	 */
	private function setKey($key)
	{
		$this->key = (string) $key;
	}


	/**
	 * Set the login that has to be used
	 *
	 * @return	void
	 * @param	string $login	The login to use.
	 */
	private function setLogin($login)
	{
		$this->login = (string) $login;
	}


	/**
	 * Set the password that has to be used
	 *
	 * @return	void
	 * @param	string $password	The password to use.
	 */
	private function setPassword($password)
	{
		$this->password = (string) $password;
	}


	/**
	 * Set the server that has to be used.
	 *
	 * @return	void
	 * @param	string $server
	 */
	private function setServer($server)
	{
		$this->server = (string) $server;
	}


	/**
	 * Set the timeout
	 * After this time the request will stop. You should handle any errors triggered by this.
	 *
	 * @return	void
	 * @param	int $seconds	The timeout in seconds.
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Set the user-agent for you application
	 * It will be appended to ours, the result will look like: "PHP Campaign Commander/<version> <your-user-agent>"
	 *
	 * @return	void
	 * @param	string $userAgent	Your user-agent, it should look like <app-name>/<app-version>.
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = (string) $userAgent;
	}


// connection methods
	/**
	 * Close the connection
	 *
	 * @return	bool	true if the connection was closes, otherwise false
	 */
	public function closeApiConnection()
	{
		// make the call
		$response = $this->doCall('closeApiConnection');

		// validate response
		if($response == 'connection closed')
		{
			// reset vars
			$this->soapClient = null;
			$this->token = null;

			return true;
		}

		// fallback
		return false;
	}


// message methods
	/**
	 * Create email-message.
	 *
	 * @return	string								The message ID.
	 * @param	string $name						Name of the message.
	 * @param	string $description					Description of the message.
	 * @param	string $subject						Subject of the message.
	 * @param	string $from						From name.
	 * @param	string $fromEmail					From email-address.
	 * @param	string $to							To name.
	 * @param	string $body						Body of the email.
	 * @param	string $encoding					Encoding to use.
	 * @param	string $replyTo						Reply-to name.
	 * @param	string $replyToEmail				Reply-to email.
	 * @param	bool[optional] $bounceback			Use as bounceback message?
	 * @param	bool[optional] $unsubscribe			Use unsubscribe feature of Windows Live Mail.
	 * @param	string[optional] $unsublinkpage		Unjoin URL, imporve deliverability displaying a unsubscribe button in Windows Live Mail.
	 */
	public function createEmailMessage($name, $description, $subject, $from, $fromEmail, $to, $body, $encoding, $replyTo, $replyToEmail, $bounceback = false, $unsubscribe = false, $unsublinkpage = null)
	{
		// build parameters
		$parameters = array();
		$parameters['name'] = (string) $name;
		$parameters['description'] = (string) $description;
		$parameters['subject'] = (string) $subject;
		$parameters['from'] = (string) $from;
		$parameters['fromEmail'] = (string) $fromEmail;
		$parameters['to'] = (string) $to;
		$parameters['body'] = (string) $body;
		$parameters['encoding'] = (string) $encoding;
		$parameters['replyTo'] = (string) $replyTo;
		$parameters['replyToEmail'] = (string) $replyToEmail;
		$parameters['isBounceback'] = ($bounceback) ? '1' : '0';
		$parameters['hotmailUnsubFlg'] = ($unsubscribe) ? '1' : '0';
		if($unsublinkpage !== null) $parameters['hotmailUnsubUrl'] = (string) $unsublinkpage;

		// make the call
		return $this->doCall('createEmailMessage', $parameters);
	}


	/**
	 * Create email-message.
	 *
	 * @return	string				The message ID.
	 * @param	array $message		The message object.
	 */
	public function createEmailMessageByObj(array $message)
	{
		// build parameters
		$parameters = array();
		$parameters['message'] = $message;

		// make the call
		return $this->doCall('createEmailMessageByObj', $parameters);
	}


	/**
	 * Create SMS-message.
	 *
	 * @return	string				The message ID.
	 * @param	string $name		Name of the message.
	 * @param	string $desc		Description of the message.
	 * @param	string $from		From name.
	 * @param	string $body		Body of the SMS.
	 */
	public function createSmsMessage($name, $desc, $from, $body)
	{
		// build parameters
		$parameters = array();
		$parameters['name'] = (string) $name;
		$parameters['desc'] = (string) $desc;
		$parameters['from'] = (string) $from;
		$parameters['body'] = (string) $body;

		// make the call
		return $this->doCall('createSMSMessage', $parameters);
	}


	/**
	 * Create SMS-message.
	 *
	 * @return	string				The message ID.
	 * @param	array $message		The message object.
	 */
	public function createSmsMessageByObj(array $message)
	{
		// build parameters
		$parameters = array();
		$parameters['message'] = $message;

		// make the call
		return $this->doCall('createSmsMessageByObj', $parameters);
	}


	/**
	 * Delete message.
	 *
	 * @return	bool			true if delete was successful.
	 * @param	string $id		ID of the message.
	 */
	public function deleteMessage($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = $message;

		// make the call
		return $this->doCall('deleteMessage', $parameters);
	}


	/**
	 * Update a message field.
	 *
	 * @return	bool			true if the update was successful.
	 * @param	string $id		ID of the message.
	 * @param	string $field	The field to update.
	 * @param	mixed $value	The value to set.
	 */
	public function updateMessage($id, $field, $value)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['field'] = (string) $field;
		$parameters['value'] = $mixed;

		// make the call
		return $this->doCall('updateMessage', $parameters);
	}


	/**
	 * Update email-message.
	 *
	 * @return	bool				true if the update was successful.
	 * @param	array $message		The message object.
	 */
	public function updateMessageByObj(array $message)
	{
		// build parameters
		$parameters = array();
		$parameters['message'] = $message;

		// make the call
		return $this->doCall('updateMessageByObj', $parameters);
	}


	/**
	 * Clone a message.
	 *
	 * @return	string				ID of the newly created message.
	 * @param	string $id			ID of the message.
	 * @param	string $newName		Name of the newly created message.
	 */
	public function cloneMessage($id, $newName)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['newName'] = (string) $newName;

		// make the call
		return $this->doCall('cloneMessage', $parameters);
	}


	/**
	 * Get message.
	 *
	 * @return	array			The message object.
	 * @param	string $id		ID of the message.
	 */
	public function getMessage($id)
	{
		// build parameters
		$parameters['id'] = (string) $id;

		// make the call
		return (array) $this->doCall('getMessage', $parameters);
	}


	/**
	 * Get last email-messages.
	 *
	 * @return	array			IDs of messages.
	 * @param	int $limit	Maximum number of messages to retrieve.
	 */
	public function getLastEmailMessages($limit)
	{
		// build parameters
		$parameters['limit'] = (int) $limit;

		// make the call
		return (array) $this->doCall('getLastEmailMessages', $parameters);
	}


	/**
	 * Get last SMS-messages.
	 *
	 * @return	array			IDs of messages.
	 * @param	int $limit	Maximum number of messages to retrieve.
	 */
	public function getLastSmsMessages($limit)
	{
		// build parameters
		$parameters['limit'] = (int) $limit;

		// make the call
		return (array) $this->doCall('getLastSmsMessages', $parameters);
	}


	/**
	 * Get email-messages by field.
	 *
	 * @return	array			IDs of messages matching the search.
	 * @param	string $field	Field to search.
	 * @param	mixed $value	Value to search.
	 * @param	int $limit		Maximum number of messages to retrieve.
	 */
	public function getEmailMessagesByField($field, $value, $limit)
	{
		// build parameters
		$parameters = array();
		$parameters['field'] = (string) $field;
		$parameters['value'] = $value;
		$parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('getEmailMessagesByField', $parameters);
	}


	/**
	 * Get SMS-messages by field.
	 *
	 * @return	array			IDs of messages matching the search.
	 * @param	string $field	Field to search.
	 * @param	mixed $value	Value to search.
	 * @param	int $limit		Maximum number of messages to retrieve.
	 */
	public function getSmsMessagesByField($field, $value, $limit)
	{
		// build parameters
		$parameters = array();
		$parameters['field'] = (string) $field;
		$parameters['value'] = $value;
		$parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('getSmsMessagesByField', $parameters);
	}


	/**
	 * Get messages by period.
	 *
	 * @return	array				IDs of messages matching the search.
	 * @param	int $dateBegin		Begin date of the period.
	 * @param	int $dateEnd		End date of the period.
	 */
	public function getMessagesByPeriod($dateBegin, $dateEnd)
	{
		// build parameters
		$parameters['dateBegin'] = date('Y-m-d H:i:s', (int) $dateBegin);
		$parameters['dateEnd'] = date('Y-m-d H:i:s', (int) $dateEnd);

		// make the call
		return (array) $this->doCall('getMessagesByPeriod', $parameters);
	}


	/**
	 * Get email-message preview.
	 *
	 * @return	array				Preview of the message.
	 * @param	string $messageId	ID of the message.
	 * @param	string $part		Part of the message to preview (HTML or text).
	 */
	public function getEmailMessagePreview($messageId, $part = 'HTML')
	{
		// @todo	validate
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['part'] = $part;

		// make the call
		return $this->doCall('getEmailMessagePreview', $parameters);
	}


	/**
	 * Get SMS-message preview.
	 *
	 * @return	string				Preview of the SMS-message.
	 * @param	string $messageId	ID of the message.
	 */
	public function getSmsMessagePreview($messageId)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;

		// make the call
		return $this->doCall('getSmsMessagePreview', $parameters);
	}


	/**
	 * Activate tracking for all links.
	 *
	 * @return	array
	 * @param	string $id		ID of the message.
	 */
	public function trackAllLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('trackAllLinks', $parameters);
	}


	/**
	 * Deactivate link tracking for all links.
	 *
	 * @return	bool			true if the untrack operation was successful.
	 * @param	string $id		ID of the message.
	 */
	public function untrackAllLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('untrackAllLinks', $parameters);
	}


	/**
	 * Tracks a link based on its position in an email.
	 *
	 * @return	array				The order number of the URL.
	 * @param	string $id			ID of the message.
	 * @param	string $position	Position of the link to update in the message.
	 * @param	string $part		HTML or text.
	 */
	public function trackLinkByPosition($id, $position, $part = 'HTML')
	{
		// @todo	validate
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['position'] = (string) $position;
		$parameters['part'] = (string) $part;

		// make the call
		return $this->doCall('trackLinkByPosition', $parameters);
	}


	/**
	 * Get a list of all teh tracked links in an email.
	 *
	 * @return	array			List of IDs of the tracked links.
	 * @param	string $id		ID of the message.
	 */
	public function getAllTrackedLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getAllTrackedLinks', $parameters);
	}


	/**
	 * Retrieves the unused tracked links for an email.
	 *
	 * @return	array			List of IDs of the unused tracked links.
	 * @param	string $id		ID of the message.
	 */
	public function getAllUnusedTrackedLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getAllUnusedTrackedLinks', $parameters);
	}


	/**
	 * Retrieves all the trackable links in an email.
	 *
	 * @return	array			List of IDs of the trackable links.
	 * @param	string $id		ID of the message.
	 */
	public function getAllTrackableLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getAllTrackableLinks', $parameters);
	}


	/**
	 * Sends a test email campaign to a group of recipients.
	 *
	 * @return							true if successfull, false otherwise.
	 * @param	string $id				The ID of the message to test.
	 * @param	string $groupId			The ID of the group to use for the test.
	 * @param	string $campaignName	The name of the test campaign.
	 * @param	string $subject			The subject of the message to test.
	 * @param	string $part			The part of the message to send, allowed values are: HTML, TXT, MULTIPART.
	 */
	public function testEmailMessageByGroup($id, $groupId, $campaignName, $subject, $part = 'MULTIPART')
	{
		// @todo	validate
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['groupId'] = (string) $groupId;
		$parameters['campaignName'] = (string) $campaignName;
		$parameters['subject'] = (string) $subject;
		$parameters['part'] = (string) $part;

		// make the call
		return $this->doCall('testEmailMessageByGroup', $parameters);
	}


	/**
	 * Sends a test email campaign to a member.
	 *
	 * @return							true if successfull, false otherwise.
	 * @param	string $id				The ID of the message to test.
	 * @param	string $memberId		The ID of the member to use for the test.
	 * @param	string $campaignName	The name of the test campaign.
	 * @param	string $subject			The subject of the message to test.
	 * @param	string $part			The part of the message to send, allowed values are: HTML, TXT, MULTIPART.
	 */
	public function testEmailMessageByMember($id, $memberId, $campaignName, $subject, $part = 'MULTIPART')
	{
		// @todo	validate
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['memberId'] = (string) $memberId;
		$parameters['campaignName'] = (string) $campaignName;
		$parameters['subject'] = (string) $subject;
		$parameters['part'] = (string) $part;

		// make the call
		return $this->doCall('testEmailMessageByMember', $parameters);
	}


	/**
	 * Sends a test email campaign to a member.
	 *
	 * @return							true if successfull, false otherwise.
	 * @param	string $id				The ID of the message to test.
	 * @param	string $memberId		The ID of the member to use for the test.
	 * @param	string $campaignName	The name of the test campaign.
	 */
	public function testSmsMessage($id, $memberId, $campaignName)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['memberId'] = (string) $memberId;
		$parameters['campaignName'] = (string) $campaignName;

		// make the call
		return $this->doCall('testSmsMessage', $parameters);
	}


	/**
	 * Retrieves the email address of the default sender.
	 *
	 * @return	string		The email address of the default sender.
	 */
	public function getDefaultSender()
	{
		// make the call
		return $this->doCall('getDefaultSender');
	}


	/**
	 * Get a list of validated alternate senders.
	 *
	 * @return	array	The list of email addresses.
	 */
	public function getValidatedAltSenders()
	{
		// make the call
		return $this->doCall('getValidatedAltSenders');
	}


	/**
	 * Get a list of not validated alternate senders.
	 *
	 * @return	array	The list of email addresses.
	 */
	public function getNotValidatedSenders()
	{
		// make the call
		return $this->doCall('getNotValidatedSenders');
	}


// url management methods
	/**
	 * Creates a standard link for an email.
	 *
	 * @return	string				ID of the created URL.
	 * @param	string $messageId	ID of the message.
	 * @param	string $name		Name of the URL.
	 * @param	string $url			URL to add.
	 */
	public function createStandardUrl($messageId, $name, $url)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		$parameters['url'] = (string) $url;

		// make the call
		return $this->doCall('createStandardUrl', $parameters);
	}


	/**
	 * Scans your message from top to bottom and replaces first occurrence of &&& with [EMV LINK]ORDER[EMV /LINK] (where ORDER is the standard link order number).
	 *
	 * @return	int					The order number of the url.
	 * @param	string $messageId	The ID for the message.
	 * @param	string $name		The name of the URL.
	 * @param	string $url			The url of the link.
	 */
	public function createAndAddStandardUrl($messageId, $name, $url)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		$parameters['url'] = (string) $url;

		// make the call
		return $this->doCall('createAndAddStandardUrl', $parameters);
	}


	/**
	 * Creates an unsubscribe link for an email.
	 *
	 * @return	string							ID of the created URL.
	 * @param	string $messageId				ID of the message.
	 * @param	string $name					Name of the URL.
	 * @param	string[optional] $pageOk		URL to call when unsubscribe was successful.
	 * @param	string[optional] $messageOk		Message to display when unsubscribe was successful.
	 * @param	string[optional] $pageError		URL to call when unsubscribe was unsuccessful.
	 * @param	string[optional] $messageError	Message to display when unsubscribe was unsuccessful.
	 */
	public function createUnsubscribeUrl($messageId, $name, $pageOk = null, $messageOk = null, $pageError = null, $messageError = null)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		if($pageOk !== null) $parameters['pageOK'] = (string) $pageOk;
		if($messageOk !== null) $parameters['messageOK'] = (string) $messageOk;
		if($pageError !== null) $parameters['pageError'] = (string) $pageError;
		if($messageError !== null) $parameters['messageError'] = (string) $messageError;

		// make the call
		return $this->doCall('createUnsubscribeUrl', $parameters);
	}


	/**
	 * Scans your message from top to bottom and replaces the first occurrence of &&& with [EMV LINK]ORDER[EMV /LINK] (where ORDER is the unsubscribe link order number).
	 *
	 * @return	string							The order number of the url.
	 * @param	string $messageId				ID of the message.
	 * @param	string $name					Name of the URL.
	 * @param	string[optional] $pageOk		URL to call when unsubscribe was successful.
	 * @param	string[optional] $messageOk		Message to display when unsubscribe was successful.
	 * @param	string[optional] $pageError		URL to call when unsubscribe was unsuccessful.
	 * @param	string[optional] $messageError	Message to display when unsubscribe was unsuccessful.
	 */
	public function createAndAddUnsubscribeUrl($messageId, $name, $pageOk = null, $messageOk = null, $pageError = null, $messageError = null)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		if($pageOk !== null) $parameters['pageOK'] = (string) $pageOk;
		if($messageOk !== null) $parameters['messageOK'] = (string) $messageOk;
		if($pageError !== null) $parameters['pageError'] = (string) $pageError;
		if($messageError !== null) $parameters['messageError'] = (string) $messageError;

		// make the call
		return $this->doCall('createAndAddUnsubscribeUrl', $parameters);
	}


	/**
	 * Creates an personalised link for an email
	 *
	 * @return	string				The order number of the URL.
	 * @param	string $messageId	ID of the message.
	 * @param	string $name		Name of the URL.
	 * @param	string $url			URL to add.
	 */
	public function createPersonalisedUrl($messageId, $name, $url)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		$parameters['url'] = (string) $url;

		// make the call
		return $this->doCall('createPersonalisedUrl', $parameters);
	}


	/**
	 * Scans your message from top to bottom and replaces the first occirrence of &&& with [EMV LINK]ORDER[EMV /LINK] (where ORDER is the personalized link order number).
	 *
	 * @return	string				The order number of the URL.
	 * @param	string $messageId	ID of the message.
	 * @param	string $name		Name of the URL.
	 * @param	string $url			URL to add.
	 */
	public function createAndAddPersonalisedUrl($messageId, $name, $url)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		$parameters['url'] = (string) $url;

		// make the call
		return $this->doCall('createAndAddPersonalisedUrl', $parameters);
	}


	/**
	 * Creates an update URL.
	 *
	 * @return	string					The order number of the URL.
	 * @param	string $messageId		ID of the message.
	 * @param	string $name			Name of the URL.
	 * @param	mixed $parameters		Update parameters to apply to the member table (for a particular member).
	 * @param	string $pageOk			Url to call when unsubscribe was successful.
	 * @param	string $messageOk		Message to display when unsubscribe was successful.
	 * @param	string $pageError		Url to call when unsubscribe was unsuccessful.
	 * @param	string $messageError	Message to display when unsubscribe was unssuccessful.
	 */
	public function createUpdateUrl($messageId, $name, $parameters, $pageOk, $messageOk, $pageError, $messageError)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		$parameters['parameters'] = $parameters;
		$parameters['pageOK'] = (string) $pageOk;
		$parameters['messageOK'] = (string) $messageOk;
		$parameters['pageError'] = (string) $pageError;
		$parameters['messageError'] = (string) $messageError;

		// make the call
		return $this->doCall('createUpdateUrl', $parameters);
	}


	/**
	 * Scans your message from top to bottom and replaces the first occirrence of &&& with [EMV LINK]ORDER[EMV /LINK] (where ORDER is the update link order number).
	 *
	 * @return	string					The order number of the URL.
	 * @param	string $messageId		ID of the message.
	 * @param	string $name			Name of the URL.
	 * @param	mixed $parameters		Update parameters to apply to the member table (for a particular member).
	 * @param	string $pageOk			Url to call when unsubscribe was successful.
	 * @param	string $messageOk		Message to display when unsubscribe was successful.
	 * @param	string $pageError		Url to call when unsubscribe was unsuccessful.
	 * @param	string $messageError	Message to display when unsubscribe was unssuccessful.
	 */
	public function createAndAddUpdateUrl($messageId, $name, $parameters, $pageOk, $messageOk, $pageError, $messageError)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		$parameters['parameters'] = $parameters;
		$parameters['pageOK'] = (string) $pageOk;
		$parameters['messageOK'] = (string) $messageOk;
		$parameters['pageError'] = (string) $pageError;
		$parameters['messageError'] = (string) $messageError;

		// make the call
		return $this->doCall('createAndAddUpdateUrl', $parameters);
	}


	/**
	 * Creates an action link for an email.
	 *
	 * @return									The order number of the URL.
	 * @param	string $messageId				The ID of the message to which to add a URL.
	 * @param	string $name					The name of the URL.
	 * @param	string $action					The action to perform.
	 * @param	string[optional] $pageOk		URL to call when unsubscribe was successful.
	 * @param	string[optional] $messageOk		Message to display when unsubscribe was successful.
	 * @param	string[optional] $pageError		URL to call when unsubscribe was unsuccessful.
	 * @param	string[optional] $messageError	Message to display when unsubscribe was unsuccessful.
	 */
	public function createActionUrl($messageId, $name, $action, $pageOk = null, $messageOk = null, $pageError = null, $messageError = null)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		$parameters['action'] = (string) $action;
		if($pageOk !== null) $parameters['pageOK'] = (string) $pageOk;
		if($messageOk !== null) $parameters['messageOK'] = (string) $messageOk;
		if($pageError !== null) $parameters['pageError'] = (string) $pageError;
		if($messageError !== null) $parameters['messageError'] = (string) $messageError;

		// make the call
		return $this->doCall('createActionUrl', $parameters);
	}


	/**
	 * Scans your message from top to bottom and replaces the first occirrence of &&& with [EMV LINK]ORDER[EMV /LINK] (where ORDER is the action link order number).
	 *
	 * @return									The order number of the URL.
	 * @param	string $messageId				The ID of the message to which to add a URL.
	 * @param	string $name					The name of the URL.
	 * @param	string $action					The action to perform.
	 * @param	string[optional] $pageOk		URL to call when unsubscribe was successful.
	 * @param	string[optional] $messageOk		Message to display when unsubscribe was successful.
	 * @param	string[optional] $pageError		URL to call when unsubscribe was unsuccessful.
	 * @param	string[optional] $messageError	Message to display when unsubscribe was unsuccessful.
	 */
	public function createdAndAddActionUrl($messageId, $name, $action, $pageOk = null, $messageOk = null, $pageError = null, $messageError = null)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;
		$parameters['action'] = (string) $action;
		if($pageOk !== null) $parameters['pageOK'] = (string) $pageOk;
		if($messageOk !== null) $parameters['messageOK'] = (string) $messageOk;
		if($pageError !== null) $parameters['pageError'] = (string) $pageError;
		if($messageError !== null) $parameters['messageError'] = (string) $messageError;

		// make the call
		return $this->doCall('createdAndAddActionUrl', $parameters);
	}


	/**
	 * Creates a mirror URL for an email.
	 *
	 * @return	string				The order number of the url.
	 * @param	string $messageId	ID of the message.
	 * @param	string $name		Name of the URL.
	 */
	public function createMirrorUrl($messageId, $name)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;

		// make the call
		return $this->doCall('createMirrorUrl', $parameters);
	}


	/**
	 * Scans your message from top to bottom and automatically replaces the first occurrence of &&& with [EMV LINK]ORDER[EMV /LINK] (where ORDER is the mirror link order number).
	 *
	 * @return	string				The order number of the url.
	 * @param	string $messageId	ID of the message.
	 * @param	string $name		Name of the URL.
	 */
	public function createAndAddMirrorUrl($messageId, $name)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['name'] = (string) $name;

		// make the call
		return $this->doCall('createAndAddMirrorUrl', $parameters);
	}


	/**
	 * Scans your message from top to bottom and automatically replaces the first occurrence of &&& with [EMV SHARE lang=xx] (where xx is the language identifier).
	 *
	 * @return	bool
	 * @param	string $messageId				The ID of the message.
	 * @param	bool $linkType					The link type, true for link, false for button.
	 * @param	string[optional] $buttonUrl		The URL of the sharebutton.
	 * @param	string[optional] $language		The language, possible values are: us, en, fr, de, nl, es, ru, sv, it, cn, tw, pt, br, da, ja, ko.
	 */
	public function addShareLink($messageId, $linkType, $buttonUrl = null, $language = null)
	{
		// @todo	validate
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['linkType'] = (bool) $linkType;
		if($buttonUrl !== null) $parameters['buttonUrl'] = (string) $buttonUrl;
		if($language !== null) $parameters['language'] = (string) $language;

		// make the call
		return $this->doCall('addShareLink', $parameters);
	}


	/**
	 * Update an URL by field.
	 *
	 * @return	bool				true if the update was successful.
	 * @param	string $messageId	ID of the message.
	 * @param	int $order			Order of the URL.
	 * @param	string $field		Field to update.
	 * @param	mixed $value		Value to set.
	 */
	public function updateUrlByField($messageId, $order, $field, $value)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['order'] = (int) $order;
		$parameters['field'] = (string) $field;
		$parameters['value'] = $value;

		// make the call
		return $this->doCall('updateUrlByField', $parameters);
	}


	/**
	 * Delete an URL.
	 *
	 * @return	bool				true if the delete was successful.
	 * @param	string $messageId	ID of the message.
	 * @param	int $order			Order of the URL.
	 */
	public function deleteUrl($messageId, $order)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['order'] = (int) $order;

		// make the call
		return $this->doCall('deleteUrl', $parameters);
	}


	/**
	 * Get an URL by his order
	 *
	 * @return	array				The URL parameters.
	 * @param	string $messageId	ID of the message.
	 * @param	int $order			Order of the URL.
	 */
	public function getUrlByOrder($messageId, $order)
	{
		// build parameters
		$parameters = array();
		$parameters['messageId'] = (string) $messageId;
		$parameters['order'] = (int) $order;

		// make the call
		return $this->doCall('getUrlByOrder', $parameters);
	}


// segment methods
	/**
	 * Creates a segment.
	 *
	 * @return	string							The ID of the created segment.
	 * @param	string $name					The name of the segment.
	 * @param	string $sampleType				The portion of the segment uses, possible values are: ALL, PERCENT, FIX.
	 * @param	string[optional] $description	The description of the segment.
	 * @param	float[optional] $sampleRate		The percentage/number of members from the segment.
	 */
	public function segmentationCreateSegment($name, $sampleType, $description = null, $sampleRate = null)
	{
		// @todo	validation
		// build parameters
		$parameters = array();
		$parameters['name'] = (string) $name;
		if($description !== null) $parameters['desc'] = (string) $description;
		$parameters['sampleType'] = (string) $sampleType;
		if($sampleRate !== null) $parameters['sampleRate'] = (float) $sampleRate;

		// make the call
		return $this->doCall('segmentationCreateSegment', $parameters);
	}


	/**
	 * Delete a segment
	 *
	 * @return	bool			true if successfull, false otherwise.
	 * @param	string $id		The ID of the segment.
	 */
	public function segmentationDeleteSegment($id)
	{
		// build parameters
		$parameters = array();
		$parameters['difflistId'] = (string) $id;

		// make the call
		return $this->doCall('segmentationDeleteSegment', $parameters);
	}


	/**
	 * Adds alphanumeric demographic criteria to a segment.
	 *
	 * @return	bool								true on success, false otherwise.
	 * @param	array $stringDemographicCriteria	The criteria object.
	 */
	public function segmentationAddStringDemographicCriteriaByObj(array $stringDemographicCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['stringDemographicCriteria'] = $stringDemographicCriteria;

		// make the call
		return $this->doCall('segmentationAddStringDemographicCriteriaByObj', $parameters);
	}


	/**
	 * Adds numeric demographic criteria to a segment.
	 *
	 * @return	bool								true on success, false otherwise.
	 * @param	array $numericDemographicCriteria	The criteria object.
	 */
	public function segmentationAddNumericDemographicCriteriaByObj(array $numericDemographicCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['numericDemographicCriteria'] = $numericDemographicCriteria;

		// make the call
		return $this->doCall('segmentationAddNumericDemographicCriteriaByObj', $parameters);
	}


	/**
	 * Adds date demographic criteria to a segment.
	 *
	 * @return	bool								true on success, false otherwise.
	 * @param	array $dateDemographicCriteria	The criteria object.
	 */
	public function segmentationAddDateDemographicCriteriaByObj(array $dateDemographicCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['dateDemographicCriteria'] = $dateDemographicCriteria;

		// make the call
		return $this->doCall('segmentationAddDateDemographicCriteriaByObj', $parameters);
	}


	/**
	 * Adds campaign action criteria to a segment.
	 *
	 * @return	bool					true on success, false otherwise.
	 * @param	array $actionCriteria	The criteria object.
	 */
	public function segmentationAddCampaignActionCriteriaByObj(array $actionCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['actionCriteria'] = $actionCriteria;

		// make the call
		return $this->doCall('segmentationAddCampaignActionCriteriaByObj', $parameters);
	}


	/**
	 * Adds campaign tracked link criteria to a segment.
	 *
	 * @return	bool							true on success, false otherwise.
	 * @param	array $trackableLinkCriteria	The criteria object.
	 */
	public function segmentationAddCampaignTrackableLinkCriteriaByObj(array $trackableLinkCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['trackableLinkCriteria'] = $trackableLinkCriteria;

		// make the call
		return $this->doCall('segmentationAddCampaignTrackableLinkCriteriaByObj', $parameters);
	}


	/**
	 * Adds reflex campaign action criteria to a segment.
	 *
	 * @return	bool							true on success, false otherwise.
	 * @param	array $actionCriteria	The criteria object.
	 */
	public function segmentationAddSerieActionCriteriaByObj(array $actionCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['actionCriteria'] = $actionCriteria;

		// make the call
		return $this->doCall('segmentationAddSerieActionCriteriaByObj', $parameters);
	}


	/**
	 * Adds reflex campaign tracked link criteria to a segment.
	 *
	 * @return	bool							true on success, false otherwise.
	 * @param	array $trackableLinkCriteria	The criteria object.
	 */
	public function segmentationAddSerieTrackableLinkCriteriaByObj(array $trackableLinkCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['trackableLinkCriteria'] = $trackableLinkCriteria;

		// make the call
		return $this->doCall('segmentationAddSerieTrackableLinkCriteriaByObj', $parameters);
	}


	/**
	 * Adds social criteria to a segment.
	 *
	 * @return	bool							true on success, false otherwise.
	 * @param	array $socialNetworkCriteria	The criteria object.
	 */
	public function segmentationAddSocialNetworkCriteriaByObj(array $socialNetworkCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['socialNetworkCriteria'] = $socialNetworkCriteria;

		// make the call
		return $this->doCall('segmentationAddSocialNetworkCriteriaByObj', $parameters);
	}


	/**
	 * Adds quick segment criteria to segment.
	 *
	 * @return	bool					true on success, false otherwise.
	 * @param	array $recencyCriteria	The criteria object.
	 */
	public function segmentationAddRecencyCriteriaByObj(array $recencyCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['recencyCriteria'] = $recencyCriteria;

		// make the call
		return $this->doCall('segmentationAddRecencyCriteriaByObj', $parameters);
	}


	/**
	 * Adds DataMart criteria to a segment.
	 *
	 * @return	bool					true on success, false otherwise.
	 * @param	array $dataMartCriteria	The criteria object.
	 */
	public function segmentationAddDataMartCriteriaByObj(array $dataMartCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['dataMartCriteria'] = $dataMartCriteria;

		// make the call
		return $this->doCall('segmentationAddDataMartCriteriaByObj', $parameters);
	}


	/**
	 * Retrieves a segment.
	 *
	 * @return	array
	 * @param	string $id	The ID of the segment.
	 */
	public function segmentationGetSegmentById($id)
	{
		// build parameters
		$parameters = array();
		$parameters['difflistId'] = (string) $id;

		// make the call
		return $this->doCall('segmentationGetSegmentById', $parameters);
	}


	/**
	 * Retrieves a list of segments.
	 *
	 * @return	array				A list of segmentation objects.
	 * @param	int $page			The current page.
	 * @param	int $itemsPerPage	The number of items per page.
	 */
	public function segmentationGetSegmentList($page, $itemsPerPage)
	{
		// build parameters
		$parameters = array();
		$parameters['page'] = (int) $page;
		$parameters['nbItemsPerPage'] = (int) $itemsPerPage;

		// make the call
		return $this->doCall('segmentationGetSegmentList', $parameters);
	}


	/**
	 * Get the criteria used in a segment.
	 *
	 * @return	array		A segmentation object
	 * @param	string $id	The ID of the segment.
	 */
	public function segmentationGetSegmentCriterias($id)
	{
		// build parameters
		$parameters = array();
		$parameters['difflistId'] = (string) $id;

		// make the call
		return $this->doCall('segmentationGetSegmentCriterias', $parameters);
	}


	/**
	 * Retrieves a list of DataMart segments
	 *
	 * @return	array
	 * @param	int $page			The current page.
	 * @param	int $itemsPerPage	The number of items per page.
	 */
	public function segmentationGetPersoFragList($page, $itemsPerPage)
	{
		// build parameters
		$parameters = array();
		$parameters['pageNumber'] = (int) $page;
		$parameters['nbItemPerpage'] = (int) $itemsPerPage;

		// make the call
		return $this->doCall('segmentationGetPersoFragList', $parameters);
	}


	/**
	 * Delete a criteria cell.
	 *
	 * @return	bool					true on success, false otherwise.
	 * @param	string $id				The ID of the segment.
	 * @param	int $orderCriteria		The ofder or the criteria.
	 */
	public function segmentationDeleteCriteria($id, $orderCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['difflistId'] = (string) $id;
		$parameters['orderCriteria'] = (int) $orderCriteria;

		// make the call
		return $this->doCall('segmentationDeleteCriteria', $parameters);
	}


	/**
	 * Updates a segment.
	 *
	 * @return	bool							true on success, false otherwise
	 * @param	string $id						The ID of the segment.
	 * @param	string $name					The name of the segment.
	 * @param	string $sampleType				The portion of the segment uses, possible values are: ALL, PERCENT, FIX.
	 * @param	float[optional] $sampleRate		The percentage/number of members from the segment.
	 */
	public function segmentationUpdateSegment($id, $name, $sampleType, $sampleRate = null)
	{
		// @todo	validation
		// build parameters
		$parameters = array();
		$parameters['Id'] = (string) $id;
		$parameters['name'] = (string) $name;
		$parameters['sampleType'] = (string) $sampleType;
		if($sampleRate !== null) $parameters['sampleRate'] = (float) $sampleRate;

		// make the call
		return $this->doCall('segmentationUpdateSegment', $parameters);
	}


	/**
	 * Updates alphanumeric demographic criteria to a segment.
	 *
	 * @return	bool								true on success, false otherwise.
	 * @param	array $stringDemographicCriteria	The criteria object.
	 */
	public function segmentationUpdateStringDemographicCriteriaByObj(array $stringDemographicCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['stringDemographicCriteria'] = $stringDemographicCriteria;

		// make the call
		return $this->doCall('segmentationUpdateStringDemographicCriteriaByObj', $parameters);
	}


	/**
	 * Updates numeric demographic criteria to a segment.
	 *
	 * @return	bool								true on success, false otherwise.
	 * @param	array $numericDemographicCriteria	The criteria object.
	 */
	public function segmentationUpdateNumericDemographicCriteriaByObj(array $numericDemographicCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['numericDemographicCriteria'] = $numericDemographicCriteria;

		// make the call
		return $this->doCall('segmentationUpdateNumericDemographicCriteriaByObj', $parameters);
	}


	/**
	 * Updates date demographic criteria to a segment.
	 *
	 * @return	bool								true on success, false otherwise.
	 * @param	array $dateDemographicCriteria	The criteria object.
	 */
	public function segmentationUpdateDateDemographicCriteriaByObj(array $dateDemographicCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['dateDemographicCriteria'] = $dateDemographicCriteria;

		// make the call
		return $this->doCall('segmentationUpdateDateDemographicCriteriaByObj', $parameters);
	}


	/**
	 * Updates campaign action criteria to a segment.
	 *
	 * @return	bool					true on success, false otherwise.
	 * @param	array $actionCriteria	The criteria object.
	 */
	public function segmentationUpdateCampaignActionCriteriaByObj(array $actionCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['actionCriteria'] = $actionCriteria;

		// make the call
		return $this->doCall('segmentationUpdateCampaignActionCriteriaByObj', $parameters);
	}


	/**
	 * Updates campaign tracked link criteria to a segment.
	 *
	 * @return	bool							true on success, false otherwise.
	 * @param	array $trackableLinkCriteria	The criteria object.
	 */
	public function segmentationUpdateCampaignTrackableLinkCriteriaByObj(array $trackableLinkCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['trackableLinkCriteria'] = $trackableLinkCriteria;

		// make the call
		return $this->doCall('segmentationUpdateCampaignTrackableLinkCriteriaByObj', $parameters);
	}


	/**
	 * Updates reflex campaign action criteria to a segment.
	 *
	 * @return	bool							true on success, false otherwise.
	 * @param	array $actionCriteria	The criteria object.
	 */
	public function segmentationUpdateSerieActionCriteriaByObj(array $actionCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['actionCriteria'] = $actionCriteria;

		// make the call
		return $this->doCall('segmentationUpdateSerieActionCriteriaByObj', $parameters);
	}


	/**
	 * Updates reflex campaign tracked link criteria to a segment.
	 *
	 * @return	bool							true on success, false otherwise.
	 * @param	array $trackableLinkCriteria	The criteria object.
	 */
	public function segmentationUpdateSerieTrackableLinkCriteriaByObj(array $trackableLinkCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['trackableLinkCriteria'] = $trackableLinkCriteria;

		// make the call
		return $this->doCall('segmentationUpdateSerieTrackableLinkCriteriaByObj', $parameters);
	}


	/**
	 * Updates social criteria to a segment.
	 *
	 * @return	bool							true on success, false otherwise.
	 * @param	array $socialNetworkCriteria	The criteria object.
	 */
	public function segmentationUpdateSocialNetworkCriteriaByObj(array $socialNetworkCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['socialNetworkCriteria'] = $socialNetworkCriteria;

		// make the call
		return $this->doCall('segmentationUpdateSocialNetworkCriteriaByObj', $parameters);
	}


	/**
	 * Updates quick segment criteria to segment.
	 *
	 * @return	bool					true on success, false otherwise.
	 * @param	array $recencyCriteria	The criteria object.
	 */
	public function segmentationUpdateRecencyCriteriaByObj(array $recencyCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['recencyCriteria'] = $recencyCriteria;

		// make the call
		return $this->doCall('segmentationUpdateRecencyCriteriaByObj', $parameters);
	}


	/**
	 * Updates DataMart criteria to a segment.
	 *
	 * @return	bool					true on success, false otherwise.
	 * @param	array $dataMartCriteria	The criteria object.
	 */
	public function segmentationUpdateDataMartCriteriaByObj(array $dataMartCriteria)
	{
		// build parameters
		$parameters = array();
		$parameters['dataMartCriteria'] = $dataMartCriteria;

		// make the call
		return $this->doCall('segmentationUpdateDataMartCriteriaByObj', $parameters);
	}


	/**
	 * Counts the total number of members in a segment (including duplicated members).
	 *
	 * @return	int			The number of members.
	 * @param	string $id	The ID of the segment.
	 */
	public function segmentationCount($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('segmentationCount', $parameters);
	}


	/**
	 * Counts the total number of distinct members in a segment (duplicate members are removed).
	 *
	 * @return	int			The number of members.
	 * @param	string $id	The ID of the segment.
	 */
	public function segmentationDistinctCount($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('segmentationDistinctCount', $parameters);
	}


// campaign methods
	/**
	 * Create a campaign.
	 *
	 * @return	string								The ID of the campaign.
	 * @param	string $name						Name of the campaign.
	 * @param	int $sendDate						Date for the campaign to be scheduled.
	 * @param	string $messageId					Id of the message to send.
	 * @param	string $mailingListId				Id of the mailing list to be send the campaign to.
	 * @param	string[optional] $description		The description.
	 * @param	bool[optional] $notifProgress		Should you be notified of the progress of the campaign by email.
	 * @param	bool[optional] $postClickTracking	Use post click tracking?
	 * @param	bool[optional] $emaildedupfig		Deduplicate the mailing list?
	 */
	public function createCampaign($name, $sendDate, $messageId, $mailingListId, $description = null, $notifProgress = false, $postClickTracking = false, $emaildedupfig = false)
	{
		// build parameters
		$parameters = array();
		$parameters['name'] = (string) $name;
		if($description !== null) $parameters['desc'] = (string) $description;
		$parameters['sendDate'] = date('Y-m-d H:i:s', (int) $sendingDate);
		$parameters['messageId'] = (string) $messageId;
		$parameters['mailingListId'] = (string) $mailingListId;
		$parameters['notifProgress'] = (bool) $notifProgress;
		$parameters['postClickTracking'] = (bool) $postClickTracking;
		$parameters['emaildedupflg'] = (bool) $emaildedupfig;

		// make the call
		return $this->doCall('createCampaign', $parameters);
	}


	/**
	 * Create a campaign with analytics activated.
	 *
	 * @return	string								The ID of the campaign.
	 * @param	string $name						Name of the campaign.
	 * @param	int $sendDate						Date for the campaign to be scheduled.
	 * @param	string $messageId					Id of the message to send.
	 * @param	string $mailingListId				Id of the mailing list to be send the campaign to.
	 * @param	string[optional] $description		The description.
	 * @param	bool[optional] $notifProgress		Should you be notified of the progress of the campaign by email.
	 * @param	bool[optional] $postClickTracking	Use post click tracking?
	 * @param	bool[optional] $emaildedupfig		Deduplicate the mailing list?
	 */
	public function createCampaignWithAnalytics($name, $sendDate, $messageId, $mailingListId, $description = null, $notifProgress = false, $postClickTracking = false, $emaildedupfig = false)
	{
		// build parameters
		$parameters = array();
		$parameters['name'] = (string) $name;
		if($description !== null) $parameters['desc'] = (string) $description;
		$parameters['sendDate'] = date('Y-m-d H:i:s', (int) $sendingDate);
		$parameters['messageId'] = (string) $messageId;
		$parameters['mailingListId'] = (string) $mailingListId;
		$parameters['notifProgress'] = (bool) $notifProgress;
		$parameters['postClickTracking'] = (bool) $postClickTracking;
		$parameters['emaildedupflg'] = (bool) $emaildedupfig;

		// make the call
		return $this->doCall('createCampaignWithAnalytics', $parameters);
	}


	/**
	 * Create a campaign.
	 *
	 * @return	string				The ID of the campaign.
	 * @param	array $campaign		The campaign object.
	 */
	public function createCampaignByObj(array $campaign)
	{
		// build parameters
		$parameters = array();
		$parameters['campaign'] = $campaign;

		// make the call
		return $this->doCall('createCampaignByObj', $parameters);
	}


	/**
	 * Delete a campaign.
	 *
	 * @return	bool			true if delete was successful.
	 * @param	string $id		The ID of the campaign.
	 */
	public function deleteCampaign($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('deleteCampaign', $parameters);
	}


	/**
	 * Update a campaign.
	 *
	 * @return	bool			true of update was successful.
	 * @param	string $id		The ID of the campaign.
	 * @param	string $field	Field to update.
	 * @param	mixed $value	Value to set.
	 */
	public function updateCampaign($id, $field, $value)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['field'] = (string) $field;
		$parameters['value'] = $value;

		// make the call
		return $this->doCall('updateCampaign', $parameters);
	}


	/**
	 * Update a campaign.
	 *
	 * @return	bool				true if the update was successful.
	 * @param	array $campaign		The campaign object.
	 */
	public function updateCampaignByObj(array $campaign)
	{
		// build parameters
		$parameters = array();
		$parameters['campaign'] = $campaign;

		// make the call
		return $this->doCall('updateCampaignByObj', $parameters);
	}


	/**
	 *  Post a campaign.
	 *
	 * @return	bool			true if post was successful.
	 * @param	string $id		The ID of the campaign.
	 */
	public function postCampaign($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('postCampaign', $parameters);
	}


	/**
	 * Unpost a campaign.
	 *
	 * @return	bool			true if unpost was successful.
	 * @param	string $id		The ID of the campaign.
	 */
	public function unpostCampaign($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('unpostCampaign', $parameters);
	}


	/**
	 * Get a campign.
	 *
	 * @return	array			The campaign parameters.
	 * @param	string $id		The ID of the campaign.
	 */
	public function getCampaign($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getCampaign', $parameters);
	}


	/**
	 * Get campaigns by field.
	 *
	 * @return	array			List of IDS of campaigns matching the search.
	 * @param	string $field	Field to update.
	 * @param	mixed $value	Value to set in that field.
	 * @param	int $limit		Maximum number of elements to retrieve.
	 */
	public function getCampaignsByField($field, $value, $limit)
	{
		// build parameters
		$parameters = array();
		$parameters['field'] = (string) $field;
		$parameters['value'] = $value;
		$parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('getCampaignsByField', $parameters);
	}


	/**
	 * Retrieves a list of campaign having a specified status
	 *
	 * @return	array			The list of campaign IDs matching the status.
	 * @param	string $status	Status to match, possible values: EDITABLE, QUEUED, RUNNING, PAUSES, COMPLETED, FAILED, KILLED.
	 */
	public static function getCampaignsByStatus($status)
	{
		// @todo	validate
		// build parameters
		$parameters = array();
		$parameters['status'] = (string) $status;

		// make the call
		return $this->doCall('getCampaignsByStatus', $parameters);
	}


	/**
	 * Retrieves a list of campaigns from a specific period.
	 *
	 * @return	array			The list of campaign IDs matching the status.
	 * @param	int $dateBegin	The start date of the period.
	 * @param	int $dateEnd	The end date of the period.
	 */
	public static function getCampaignsByPeriod($dateBegin, $dateEnd)
	{
		// build parameters
		$parameters = array();
		$parameters['dateBegin'] = date('Y-m-d H:i:s', (int) $dateBegin);
		$parameters['dateEnd'] = date('Y-m-d H:i:s', (int) $dateEnd);

		// make the call
		return $this->doCall('getCampaignsByPeriod', $parameters);
	}


	/**
	 * Get the status for a campaign.
	 *
	 * @return	string			Status of the campaign.
	 * @param	string $id		The ID of the campaign.
	 */
	public function getCampaignStatus($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getCampaignStatus', $parameters);
	}


	/**
	 * Get last campaigns
	 *
	 * @return	array
	 * @param	int $limit		Maximum number of campaigns to retrieve.
	 */
	public function getLastCampaigns($limit)
	{
		// build parameters
		$parameters = array();
		$parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('getLastCampaigns', $parameters);
	}


	/**
	 * Sends a test campaign to a group of members.
	 *
	 * @return	bool				true if it was successfull, false otherwise.
	 * @param	string $id			The ID of the campaign.
	 * @param	string $groupId		The ID of the group to whom to send the test.
	 */
	public function testCampaignByGroup($id, $groupId)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['groupId'] = (string) $groupId;

		// make the call
		return $this->doCall('testCampaignByGroup', $parameters);
	}


	/**
	 * Sends a test campaign to a member.
	 *
	 * @return	bool				true if it was successfull, false otherwise.
	 * @param	string $id			The ID of the campaign.
	 * @param	string $memberId	The ID of the member to whom to send the test.
	 */
	public function testCampaignByMember($id, $memberId)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['memberId'] = (string) $memberId;

		// make the call
		return $this->doCall('testCampaignByMember', $parameters);
	}


	/**
	 * Pause a running campaign.
	 *
	 * @return	bool				true if it was successfull, false otherwise.
	 * @param	string $id			The ID of the campaign.
	 */
	public function pauseCampaign($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('pauseCampaign', $parameters);
	}


	/**
	 * Unpauses a paused campaign.
	 *
	 * @return	bool				true if it was successfull, false otherwise.
	 * @param	string $id			The ID of the campaign.
	 */
	public function unpauseCampaign($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('unpauseCampaign', $parameters);
	}


	/**
	 * Retrieves a snapshot report.
	 *
	 * @return	array			The report data.
	 * @param	string $id		The id of the campaign.
	 */
	public function getCampaignSnapshotReport($id)
	{
		// build parameters
		$parameters = array();
		$parameters['campaignId'] = (string) $id;

		// make the call
		return $this->doCall('getCampaignSnapshotReport', $parameters);
	}


// banner methods
	/**
	 * Creates a banner.
	 *
	 * @return	string							The ID of the banner.
	 * @param	string $name					The name of the banner.
	 * @param	string $contentType				The content type of the banner, possible values are: TEXT or HTML.
	 * @param	string[optional] $description	The description.
	 * @param	string[optional] $content		The content of the banner.
	 */
	public function createBanner($name, $contentType, $description = null, $content = null)
	{
		// @todo	validate
		// build parameters
		$parameters = array();
		$parameters['name'] = (string) $name;
		if($description !== null) $parameters['description'] = (string) $description;
		$parameters['contentType'] = (string) $contentType;
		if($content !== null) $parameters['content'] = '<!CDATA[' . $content . ']]>';

		// make the call
		return $this->doCall('createBanner', $parameters);
	}


	/**
	 * Creates a banner.
	 *
	 * @return	string			The ID of the banner.
	 * @param	array $banner	The banner.
	 */
	public function createBannerByObj(array $banner)
	{
		// build parameters
		$parameters = array();
		$parameters['banner'] = $banner;

		// make the call
		return $this->doCall('createBannerByObj', $parameters);
	}


	/**
	 * Deletes a banner
	 *
	 * @return	bool			true on success, false otherwise.
	 * @param	string $id		The ID of the banner.
	 */
	public function deleteBanner($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('deleteBanner', $parameters);
	}


	/**
	 * Updates a banner by field and value.
	 *
	 * @return	bool					true on success, false otherwise.
	 * @param	string $id				The ID of the banner.
	 * @param	string $field			The field
	 * @param	mixed[optional] $value	The new value.
	 */
	public function updateBanner($id, $field, $value = null)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['field'] = (string) $field;
		if($value !== null) $parameters['value'] = $value;

		// make the call
		return $this->doCall('updateBanner', $parameters);
	}


	/**
	 * Updates a banner.
	 *
	 * @return	bool			true on success, false otherwise.
	 * @param	array $banner	The banner.
	 */
	public function updateBannerByObj(array $banner)
	{
		// build parameters
		$parameters = array();
		$parameters['banner'] = $banner;

		// make the call
		return $this->doCall('updateBannerByObj', $parameters);
	}


	/**
	 * Clones a banner.
	 *
	 * @return	string 			The ID of the new banner.
	 * @param	string $id		The ID of the banner.
	 * @param	string $name	The new name of the banner.
	 */
	public function cloneBanner($id, $name)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['newName'] = (string) $name;

		// make the call
		return $this->doCall('cloneBanner', $parameters);
	}


	/**
	 * Displays a preview of a banner.
	 *
	 * @return	string		The formatted preview of a banner.
	 * @param	string $id	The ID of the banner.
	 */
	public function getBannerPreview($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getBannerPreview', $parameters);
	}


	/**
	 * Retrieves a banner.
	 *
	 * @return	array		The banner
 	 * @param	string $id	The ID of the banner.
	 */
	public function getBanner($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getBanner', $parameters);
	}


	/**
	 * Retrieves a list of banners that contain the given value in a field.
	 *
	 * @return	array					The IDs of the banners.
	 * @param	string $field			The field of the banner
	 * @param	mixed[optional] $value	The value.
	 * @param	int $limit				The size of the list (between 1 and 1000).
	 */
	public function getBannersByField($field, $value, $limit)
	{
		// @todo	validate
		// build parameters
		$parameters = array();
		$parameters['field'] = (string) $field;
		if($value !== null) $parameters['value'] = $value;
		$parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('getBannersByField', $parameters);
	}


	/**
	 * Retrieves a list of banners from a given period.
	 *
	 * @return	array			The IDs of the banners.
	 * @param	int $dateStart	The start date of the period.
	 * @param	int $dateEnd	The end date of the period.
	 */
	public function getBannersByPeriod($dateStart, $dateEnd)
	{
		// build parameters
		$parameters = array();
		$parameters['dateBegin'] = date('Y-m-d H:i:s', (int) $dateStart);
		$parameters['dateEnd'] = date('Y-m-d H:i:s', (int) $dateEnd);

		// make the call
		return $this->doCall('getBannersByPeriod', $parameters);
	}


	/**
	 * Retrieves the list of the last banners.
	 *
	 * @return	array		The IDs of the banners.
	 * @param	int $limit	The size of the list (between 1 and 1000).
	 */
	public function getLastBanners($limit)
	{
		// @todo	validate
		// build parameters
		$parameters = array();
		$parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('getLastBanners', $parameters);
	}


	/**
	 * Activates tracking for all untracked banner links and saves the banner.
	 *
	 * @return	int				The last tracked link's order.
	 * @param	string $id		The ID of the banner.
	 */
	public function trackAllBannerLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('trackAllBannerLinks', $parameters);
	}


	/**
	 * untracks all the banner links.
	 *
	 * @return	int				The last tracked link's order.
	 * @param	string $id		The ID of the banner.
	 */
	public function untrackAllBannerLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('untrackAllBannerLinks', $parameters);
	}


	/**
	 * Tracks the banner link through its position
	 *
	 * @return	int				The order number of the url.
	 * @param	string $id		The ID of the banner.
	 * @param	int $position	The position of the link in the banner.
	 */
	public function trackBannerLinkByPosition($id, $position)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['position'] = (int) $position;

		// make the call
		return $this->doCall('trackBannerLinkByPosition', $parameters);
	}


	/**
	 * Untracks a link in the banner by its order.
	 *
	 * @return	bool			true on success, false otherwise.
	 * @param	string $id		The ID od the banner.
	 * @param	int $order		The order number of the url.
	 */
	public function untrackBannerLinkByOrder($id, $order)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;
		$parameters['order'] = (int) $order;

		// make the call
		return $this->doCall('untrackBannerLinkByOrder', $parameters);
	}


	/**
	 * Retrieves a list of all the tracked links in a banner.
	 *
	 * @return	array		List of the tracked links.
	 * @param	string $id	The ID of the banner.
	 */
	public function getAllBannerTrackedLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getAllBannerTrackedLinks', $parameters);
	}


	/**
	 * Retrieves a list of all the unused tracked links in a banner.
	 *
	 * @return	array		List of the unused tracked links.
	 * @param	string $id	The ID of the banner.
	 */
	public function getAllUnusedBannerTrackedLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getAllUnusedBannerTrackedLinks', $parameters);
	}


	/**
	 * Retrieves a list of all the trackable links in a banner.
	 *
	 * @return	array		List of the trackable links.
	 * @param	string $id	The ID of the banner.
	 */
	public function getAllBannerTrackableLinks($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall('getAllBannerTrackableLinks', $parameters);
	}


// banner link management methods
	public function createStandardBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createAndAddStandardBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createUnsubscribeBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createAndAddUnsubscribeBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createPersonalisedBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createAndAddPersonalisedBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createUpdateBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createAndAddUpdateBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createActionBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createAndAddActionBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createMirrorBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function createAndAddMirrorBannerLink()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function updateBannerLinkByField()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


	public function getBannerLinkByOrder()
	{
		throw new CampaignCommanderException('Not implemented', 500);
	}


// test group methods
	/**
	 * Creates a test group of members.
	 *
	 * @return	string			The ID of the newly created test group.
	 * @param	string $name	The name of the test group.
	 */
	public function createTestGroup($name)
	{
		// build parameters
		$parameters = array();
		$parameters['Name'] = (string) $name;

		// make the call
		return $this->doCall('createTestGroup', $parameters);
	}


	/**
	 * Creates a test group.
	 *
	 * @return	string				The ID of the created test group.
	 * @param	array $testGroup	The test group object.
	 */
	public function createTestGroupByObj(array $testGroup)
	{
		// build parameters
		$parameters = array();
		$parameters['testGroup'] = $testGroup;

		// make the call
		return $this->doCall('createTestGroupByObj', $parameters);
	}


	/**
	 * Adds a member to a test group.
	 *
	 * @return	bool				true if it was successfull, false otherwise.
	 * @param	string $memberId	The ID of the member to add.
	 * @param	string $groupId		The ID of the group to which to add the member.
	 */
	public function addTestMember($memberId, $groupId)
	{
		// build parameters
		$parameters = array();
		$parameters['memberId'] = (string) $memberId;
		$parameters['groupId'] = (string) $groupId;

		// make the call
		return $this->doCall('addTestMember', $parameters);
	}


	/**
	 * Removes a member from a test group
	 *
	 * @return	bool				true if it was successfull, false otherwise.
	 * @param	string $memberId	The ID of the member to add.
	 * @param	string $groupId		The ID of the group to which to add the member.
	 */
	public function removeTestMember($memberId, $groupId)
	{
		// build parameters
		$parameters = array();
		$parameters['memberId'] = (string) $memberId;
		$parameters['groupId'] = (string) $groupId;

		// make the call
		return $this->doCall('removeTestMember', $parameters);
	}


	/**
	 * Deletes a test group.
	 *
	 * @return	bool				true if it was successfull, false otherwise.
	 * @param	string $groupId		The ID of the group to which to add the member.
	 */
	public function deleteTestGroup($groupId)
	{
		// build parameters
		$parameters = array();
		$parameters['groupId'] = (string) $groupId;

		// make the call
		return $this->doCall('deleteTestGroup', $parameters);
	}


	/**
	 * Updates a test group.
	 *
	 * @return	bool				true if it was successfull, false otherwise.
	 * @param	array $testGroup	The test group object.
	 */
	public function updateTestGroupByObj(array $testGroup)
	{
		// build parameters
		$parameters = array();
		$parameters['testGroup'] = $testGroup;

		// make the call
		return $this->doCall('updateTestGroupByObj', $parameters);
	}


	/**
	 * Retrieves the list of members in a test group.
	 *
	 * @return	array				The list of member ID's in that group
	 * @param	string $groupId		The ID fo the group.
	 */
	public function getTestGroup($groupId)
	{
		// build parameters
		$parameters = array();
		$parameters['groupId'] = (string) $groupId;

		// make the call
		return $this->doCall('getTestGroup', $parameters);
	}


	/**
	 * Retrieves a list of test groups.
	 *
	 * @return	array	The list of groups IDs.
	 */
	public function getClientTestGroups()
	{
		// make the call
		return $this->doCall('getClientTestGroups');
	}
}


/**
 * Campaign Commander Exception class
 *
 * @author	Tijs Verkoyen <php-campaign-commander-member@verkoyen.eu>
 */
class CampaignCommanderException extends Exception
{
}

?>