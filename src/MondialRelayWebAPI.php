<?php

namespace Ernadoo\MondialRelay;

use Ernadoo\MondialRelay\dto\RegisteredShipmentData;
use Ernadoo\MondialRelay\helpers\{ApiHelper, ParcelShopHelper};
use Symfony\Component\Stopwatch\{Stopwatch, StopwatchEvent};

/**
 * API Mondial Relay
 */
class MondialRelayWebAPI
{
	/**
	 * Mondial relay Customer ID (Brand ID)
	 * @var string
	 * @access private
	 */
	public $_APINumericBrandId;

	/**
	 * Nusoap Soap client isntance
	 * @var \SoapClient
	 * @access private
	 */
	private $_SoapClient ;

	/**
	 * Mondial Relay Customer Extranet Root Url
	 * @var string
	 * @access private
	 */
	private $_MRConnectUrl = "http://connect.mondialrelay.com";

	/**
	 * Mondial Relay Stickers Root URL
	 * @var string
	 * @access private
	 */
	private $_MRStickersUrl = "http://www.mondialrelay.com";


	/**
	 * API login for API V1
	 * @var string
	 * @access public
	 */
	private $_Api_CustomerCode 	= "";

	/**
	 * API password for API V1
	 * @var string
	 * @access public
	 */
	private $_Api_SecretKey= "";

	/**
	 * API user for API V2
	 * @var string
	 * @access public
	 */
	private $_Api_User 	= "";

	/**
	 * API password for API V2
	 * @var string
	 * @access public
	 */
	private $_Api_Password = "";


	/**
	 * @var array $profiles Profiled data
	 */
	protected $profiles = array();

	/**
	 * @var Stopwatch $stopwatch Symfony profiler Stopwatch service
	 */
	protected $stopwatch;

	/**
	 * @var integer
	 */
	protected $counter = 1;

	/**
	 * Debug mode enabled or not
	 * @var boolean
	 * @access private
	 */
	public $_Debug = false;

	/**
	* constructor
	*
	* @param	string $ApiEndPointUrl Mondial Relay API EndPoint
	* @param	string $ApiLogin Mondial Relay API Login (provided by your technical contact)
	* @param	string $ApiPassword Mondial Relay API Password (provided by your technical contact)
	* @param	string $ApiBrandId Mondial Relay API Numeric Brand ID (2 digits) (provided by your technical contact)
	* @access   public
	*/
	public function __construct(Stopwatch $stopwatch, $wsdl, $customerCode, $secretKey, $BrandId = 11)
	{
		$this->stopwatch			= $stopwatch;
		$this->_SoapClient			= new \SoapClient($wsdl, ['trace' => true]);
		$this->_Api_CustomerCode	= $customerCode;
		$this->_Api_SecretKey		= $secretKey;
		$this->_APINumericBrandId	= $BrandId;
	}

	public function __destruct()
	{
	}

	public function getProfiles()
	{
		return $this->profiles;
	}

	/**
	* Search parcel Shop Arround a postCode according to filters
	*
	* @param	string $CountryCode Country Code (ISO) of the post code
	* @param	string $PostCode Post Code arround which you want to find parcel shops
	* @param	string $DeliveryMode Optionnal - Delivery Mode Code Filter (3 Letter code, 24R, DRI). Will restrict the results to parcelshops available with this delivery Mode
	* @param	int $ParcelWeight Optionnal - Will restrict results to parcelshops compatible with the parcel Weight in gramms specified
	* @param	int $ParcelShopActivityCode Optionnal - Will restrict results to parcelshops regarding to their actity code
	* @param	int $SearchDistance Optionnal - Will restrict results to parcelshops in the perimeter specified in km
	* @param	int $SearchOpenningDelay Optionnal - If you intend to give us the parcel in more than one day, you can specified a delay in order to filter ParcelShops according to their oppening periods
	* @access   public
	* @return   Array of parcelShop
	*/
	public function SearchParcelShop($params)
	{
		try
		{
			$result = $this->CallWebApi('WSI4_PointRelais_Recherche', $this->AddSecurityCode($params));

			if (!property_exists($result, 'PointsRelais') || !property_exists($result->PointsRelais, 'PointRelais_Details'))
			{
				return [];
			}

			if (is_object($result->PointsRelais->PointRelais_Details))
			{
				return ParcelShopHelper::ParcelShopResultToDTO($result);;
			}

			foreach($result->PointsRelais->PointRelais_Details as $val)
			{
				$parcelShopArray[] = ParcelShopHelper::ParcelShopResultToDTO($val);
			}

			return $parcelShopArray;
		}
		catch (\SoapFault $e)
		{
			throw new \Exception($e->getMessage(), $e->getCode());
		}
	}

	/**
	* get the parcel shop datas (adress, openning, geodata, picture url, ...)
	*
	* @param	string $CountryCode Country Code (ISO) of the post code
	* @param	string $ParcelShopId parcel Shop ID
	* @access   public
	* @return   ParcelShop
	*/
	public function GetParcelShopDetails($CountryCode, $ParcelShopId)
	{
		$params = array(
			'Pays'				=> $CountryCode,
			'NumPointRelais'	=> $ParcelShopId
		);

		$result = $this->CallWebApi("WSI3_PointRelais_Recherche", $this->AddSecurityCode($params));

		//transformation en dto
		$parcelShopArray = ParcelShopHelper::ParcelShopResultToDTO($result->PointsRelais->PointRelais_Details);

		return $parcelShopArray;

	}

	/**
	* register a shipment in our system
	*
	* @param	string $ShipmentDetails Shipment datas
	* @param	string $ReturnStickers (optionnal) default is TRUE, will return a stickers url id true
	* @access   public
	* @return   shipmentResult
	* @todo : better result output
	*/
	public function CreateShipment($ShipmentDetails)
	{
		//calcul du poids total
		$ReturnStickers = true;
		$WeightInGr =0;

		foreach($ShipmentDetails['Parcels'] as $parcel)
		{
			$WeightInGr += $parcel['WeightInGr'];
		}

		$params = array(
			 'ModeCol'		=> $ShipmentDetails['CollectMode']['Mode']  ?? '',
			 'ModeLiv'		=> $ShipmentDetails['DeliveryMode']['Mode'] ?? '',
			 'NDossier'		=> $ShipmentDetails['InternalOrderReference'] ?? '',
			 'NClient'		=> $ShipmentDetails['InternalCustomerReference'] ?? '',
			 'Expe_Langage'	=> $ShipmentDetails['Sender']['Language'] ?? '',
			 'Expe_Ad1'		=> $ShipmentDetails['Sender']['Adress1'] ?? '',
			 'Expe_Ad2'		=> $ShipmentDetails['Sender']['Adress2'] ?? '',
			 'Expe_Ad3'		=> $ShipmentDetails['Sender']['Adress3'] ?? '',
			 'Expe_Ad4'		=> $ShipmentDetails['Sender']['Adress4'] ?? '',
			 'Expe_Ville'	=> $ShipmentDetails['Sender']['City'] ?? '',
			 'Expe_CP'		=> $ShipmentDetails['Sender']['PostCode'] ?? '',
			 'Expe_Pays'	=> $ShipmentDetails['Sender']['CountryCode'] ?? '',
			 'Expe_Tel1'	=> $ShipmentDetails['Sender']['PhoneNumber'] ?? '',
			 'Expe_Tel2'	=> $ShipmentDetails['Sender']['PhoneNumber2'] ?? '',
			 'Expe_Mail'	=> $ShipmentDetails['Sender']['Email'] ?? '',

			 'Dest_Langage'	=> $ShipmentDetails['Recipient']['Language'] ?? '',
			 'Dest_Ad1'		=> $ShipmentDetails['Recipient']['Adress1'] ?? '',
			 'Dest_Ad2'		=> $ShipmentDetails['Recipient']['Adress2'] ?? '',
			 'Dest_Ad3'		=> $ShipmentDetails['Recipient']['Adress3'] ?? '',
			 'Dest_Ad4'		=> $ShipmentDetails['Recipient']['Adress4'] ?? '',
			 'Dest_Ville'	=> $ShipmentDetails['Recipient']['City'] ?? '',
			 'Dest_CP'		=> $ShipmentDetails['Recipient']['PostCode'] ?? '',
			 'Dest_Pays'	=> $ShipmentDetails['Recipient']['CountryCode'] ?? '',
			 'Dest_Tel1'	=> $ShipmentDetails['Recipient']['PhoneNumber'] ?? '',
			 'Dest_Tel2'	=> $ShipmentDetails['Recipient']['PhoneNumber2'] ?? '',
			 'Dest_Mail'	=> $ShipmentDetails['Recipient']['Email'] ?? '',


			 'Poids'		=> $WeightInGr,
			 'Longueur'		=> "",
			 'Taille'		=> "",
			 'NbColis'		=> count($ShipmentDetails['Parcels']),
			 'CRT_Valeur'	=> $ShipmentDetails['CostOnDelivery'] ?? '',
			 'CRT_Devise'	=> $ShipmentDetails['CostOnDeliveryCurrency'] ?? '',
			 'Exp_Valeur'	=> $ShipmentDetails['Value'] ?? '',
			 'Exp_Devise'	=> $ShipmentDetails['ValueCurrency'] ?? '',

			 'COL_Rel_Pays'	=> $ShipmentDetails['CollectMode']['ParcelShopContryCode'] ?? '',
			 'COL_Rel'		=> $ShipmentDetails['CollectMode']['ParcelShopId'] ?? '',

			 'LIV_Rel_Pays'	=> $ShipmentDetails['DeliveryMode']['ParcelShopContryCode'] ?? '',
			 'LIV_Rel'		=> $ShipmentDetails['DeliveryMode']['ParcelShopId'] ?? '',


			 'Assurance'	=> $ShipmentDetails['InsuranceLevel'] ?? '',
			 'Instructions'	=> $ShipmentDetails['DeliveryInstruction'] ?? ''
			);

		$params = $this->AddSecurityCode($params);

		$result = $this->CallWebApi('WSI2_CreationEtiquette', $params);

		$output = new RegisteredShipmentData();

		$output->BrandCode = substr($this->_Api_CustomerCode,0,2);
		$output->Success = true;
		$output->Messages = "";
		$output->ShipmentNumber = $result->ExpeditionNum;
		$output->TrackingLink = $this->GetShipmentPermalinkTracingLink($output->ShipmentNumber);
		$output->LabelLink  =$this->BuildStickersLink($result);

		return $output;
	}

	/**
	* get a parcel status
	*
	* @param	int $ShipmentNumber Shipment number(8 digits)
	* @access   public
	* @return   shipmentStatus
	*/
	public function GetShipmentStatus($ShipmentNumber)
	{
		die ("Not implemented yet !");
	}


	/**
	* get a secure link to the professional parcel informations mondial relay extranet
	*
	* @param	int $ShipmentNumber Shipment number(8 digits)
	* @param	string $UserLogin Login to connect to the system
	* @access   public
	* @return   string
	*/
	public function GetShipmentConnectTracingLink($ShipmentNumber, $UserLogin){
		$Tracing_url = "/".trim(strtoupper($this->_Api_CustomerCode))."/Expedition/Afficher?numeroExpedition=".$ShipmentNumber;
		return $this->_MRConnectUrl.$this->AddConnectSecurityParameters($Tracing_url,$UserLogin);
	}

	/**
	* get a secure link to the professional parcel informations mondial relay extranet
	*
	* @param	int $ShipmentNumber Shipment number(8 digits)
	* @param	string $UserLogin Login to connect to the system
	* @access   public
	* @return   string
	*/
	public function GetShipmentPermalinkTracingLink($ShipmentNumber, $language="fr", $country="fr")
	{

		$Tracing_url = "http://www.mondialrelay.fr/public/permanent/tracking.aspx?ens=".$this->_Api_CustomerCode.$this->_APINumericBrandId."&exp=".$ShipmentNumber."&pays=".$country."&language=".$language;
		$Tracing_url .= $this->AddPermalinkSecurityParameters($ShipmentNumber);
		if($this->_Debug){
			echo "<br/>Permalink pour expé <b>".$this->_APILogin."/".$ShipmentNumber."</b> langue <b>".$language."</b>, pays <b>".$country."</b> : ".$Tracing_url ."<hr/>";
		}

		return $Tracing_url;
	}

	/**
	* add the security signature to the extranet url request
	*
	* @param	string $UrlToSecure Url
	* @param	string $UserLogin Login to connect to the system
	* @access   private
	* @return   string
	*/
	private function AddConnectSecurityParameters($UrlToSecure, $UserLogin)
	{
		$UrlToSecure = $UrlToSecure."&login=".$UserLogin."&ts=".time();
		$UrlToEncode = $this->_APIPassword."_".$UrlToSecure ;

		return $UrlToSecure."&crc=".strtoupper(md5($UrlToEncode));
	}

	/**
	* add the security signature to the permalink url request
	*
	* @param	string $UrlToSecure Url
	* @param	string $UserLogin Login to connect to the system
	* @access   private
	* @return   string
	*/
	private function AddPermalinkSecurityParameters($Chaine)
	{
		$UrlToSecure = "<".$this->_Api_CustomerCode.$this->_APINumericBrandId.">".$Chaine."<".$this->_Api_SecretKey.">";
		if($this->_Debug){
			echo "<br/>Chaine à encode : ".htmlentities($UrlToSecure) . " - MD5 Calculé : ".strtoupper(md5($UrlToSecure));
		}
		return "&crc=".strtoupper(md5($UrlToSecure));
	}

	/**
	* add the security signature to the soap request
	*
	* @param	string $ParameterArray Soap Parameters Request to secure
	* @param	boolean $ReturnArray Optionnal, False if you just want to output the security string
	* @access   private
	* @return   string
	*/
	private function AddSecurityCode($ParameterArray, $ReturnArray = true){

		$ParameterArray = array_merge(['Enseigne' => $this->_Api_CustomerCode], $ParameterArray);
		$secString = "";
		foreach($ParameterArray as $prm){
			$secString .= $prm;
		}

		if($ReturnArray){
			$ParameterArray['Security'] = strtoupper(md5(utf8_decode($secString.$this->_Api_SecretKey)));
			return $ParameterArray;
		}else{
			return strtoupper(md5($secString.$this->_Api_SecretKey));
		}
	}

	/**
	* perform a call to the mondial relay API
	*
	* @param	string $methodName Soap Method to call
	* @param	$ParameterArray Soap parameters array
	* @access   private
	*/
	private function CallWebApi($methodName, $ParameterArray)
	{

		$event = $this->startProfiling($ParameterArray);

		$result = $this->_SoapClient->{$methodName}($ParameterArray);

		// Display the request and response
		if($this->_Debug){
			echo '<div style="border:solid 1px #ddd;font-family:verdana;padding:5px">';
			echo '<h1>Method '.$methodName.'</h1>';
			echo '<div>'. ApiHelper::GetStatusCode($result) .'</div>';
			echo '<h2>Request</h2>';
			echo '<pre>';
			print_r($ParameterArray);
			echo '</pre>';
			echo '<pre>' . htmlspecialchars($this->_SoapClient->__getLastRequest(), ENT_QUOTES) . '</pre>';
			echo '<h2>Response</h2>';
			echo '<pre>';
			print_r($result);
			echo '</pre>';
			echo '<pre>' . htmlspecialchars($this->_SoapClient->getHTTPContentType(), ENT_QUOTES) . '</pre>';

			echo '</div>';

		}

		$ApiResult = $result->{$methodName.'Result'};

		if ($ApiResult->STAT != '0')
		{
			throw new \SoapFault($ApiResult->STAT, ApiHelper::GetStatusCode($result));
			$this->stopProfiling($event, $ApiResult);
		}

		$this->stopProfiling($event, $ApiResult);

		return $result->{$methodName.'Result'};
	}

	/**
	* Build a link to download the stickers
	* from a web service call result
	*
	* @param	service result $StickersResult
	* @access   public
	*/
	public function BuildStickersLink($StickersResult)
	{
		return $this->_MRStickersUrl . $StickersResult->URL_Etiquette;
	}

	/**
	 * Starts profiling
	 *
	 * @param string $query Query text
	 *
	 * @return StopwatchEvent
	 */
	protected function startProfiling($query)
	{
		if ($this->stopwatch instanceof Stopwatch) {
			$this->profiles[$this->counter] = array(
				'query'        => $query,
				'duration'     => null,
				'memory_start' => memory_get_usage(true),
				'memory_end'   => null,
				'memory_peak'  => null,
				'result'		=> null,
			);

			return $this->stopwatch->start('mr');
		}
	}

	/**
	 * Stops the profiling
	 *
	 * @param StopwatchEvent $event A stopwatchEvent instance
	 */
	protected function stopProfiling(StopwatchEvent $event = null, $result)
	{
		if ($this->stopwatch instanceof Stopwatch) {
			$event->stop();

			$values = array(
				'duration'    => $event->getDuration(),
				'memory_end'  => memory_get_usage(true),
				'memory_peak' => memory_get_peak_usage(true),
				'result'	=> $result,
				'resultCount' => 1,
			);

			$this->profiles[$this->counter] = array_merge($this->profiles[$this->counter], $values);

			$this->counter++;
		}
	}
}
