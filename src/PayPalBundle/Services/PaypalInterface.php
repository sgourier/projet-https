<?php

/**
 * Created by PhpStorm.
 * User: Sylvain Gourier
 * Date: 02/05/2016
 * Time: 11:39
 */

namespace PayPalBundle\Services;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\Tests\Compiler\H;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\PaymentExecution;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

class PaypalInterface
{
	private $apiContext;
	private $clientId;
	private $clientSecret;
	private $router;
	private $mode;
	private $taxesPercent;

	/**
	 * PaypalInterface constructor.
	 *
	 */
	public function __construct($clientId,$clientSecret,$mode,$taxePercent,Router $router)
	{
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->mode = $mode;
		$this->router = $router;
		$this->taxesPercent = $taxePercent;
		$this->apiContext = $this->getApiContext();
	}

	public function getApiContext()
	{
		$apiContext = new ApiContext(
			new OAuthTokenCredential(
				$this->clientId,
				$this->clientSecret
			)
		);
		// Comment this line out and uncomment the PP_CONFIG_PATH
		// 'define' block if you want to use static file
		// based configuration
		$apiContext->setConfig(
			array(
				'mode' => $this->mode,
				'log.LogEnabled' => true,
				'log.FileName' => '../PayPal.log',
				'log.LogLevel' => 'DEBUG', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
				'cache.enabled' => true,
				// 'http.CURLOPT_CONNECTTIMEOUT' => 30
				// 'http.headers.PayPal-Partner-Attribution-Id' => '123123123'
				//'log.AdapterFactory' => '\PayPal\Log\DefaultLogFactory' // Factory class implementing \PayPal\Log\PayPalLogFactory
			)
		);

		return $apiContext;
	}

	public function createExpressPayment($items,$routeCallbackName,$shippingPrice,$currency = "EUR",$description = "Payment description",$sale = "sale")
	{
		$payer = new Payer();
		$payer->setPaymentMethod("paypal");

		$itemArray = array();
		
		$subTotal = 0;

		foreach ($items as $item)
		{
			$itemTmp = new Item();
			$itemTmp->setName($item['name'])
			      ->setCurrency($item['currency'])
			      ->setQuantity($item['quantity'])
			      ->setSku($item['ref']) // Similar to `item_number` in Classic API
			      ->setPrice($item['price']);

			$itemArray[] = $itemTmp;

			$subTotal += floatval($item['price']);
		}
		
		$taxes = ($subTotal * floatval($this->taxesPercent))/100;
		$total = $subTotal + $taxes;

		$itemList = new ItemList();
		$itemList->setItems($itemArray);

		$details = new Details();
		$details->setShipping($shippingPrice)
		        ->setTax($taxes)
		        ->setSubtotal($subTotal);

		$amount = new Amount();
		$amount->setCurrency($currency)
		       ->setTotal($total)
		       ->setDetails($details);

		$transaction = new Transaction();
		$transaction->setAmount($amount)
		            ->setItemList($itemList)
		            ->setDescription($description)
		            ->setInvoiceNumber(uniqid());

		$redirectUrls = new RedirectUrls();
		$redirectUrls->setReturnUrl($this->router->generate($routeCallbackName,array('result'=>"ok"),UrlGeneratorInterface::ABSOLUTE_URL))
		             ->setCancelUrl($this->router->generate($routeCallbackName,array('result'=>"nok"),UrlGeneratorInterface::ABSOLUTE_URL));

		$payment = new Payment();
		$payment->setIntent($sale)
		        ->setPayer($payer)
		        ->setRedirectUrls($redirectUrls)
		        ->setTransactions(array($transaction));

		try
		{
			$payment->create($this->apiContext);
		} catch (Exception $ex) {
			throw new BadRequestHttpException("Payment failed with message : ". $ex->getMessage());
		}
		var_dump(3);
		return $payment->getApprovalLink();
	}

	public function paymentExecution($paymentId,$payerId)
	{
		$payment = Payment::get($paymentId, $this->apiContext);

		$execution = new PaymentExecution();
		$execution->setPayerId($payerId);

		try
		{
			$result = $payment->execute($execution, $this->apiContext);
            return array(
	            "result" => true,
	            "paymentId" => $payment->getId(),
            );
        }
		catch (Exception $ex)
		{
			throw new BadRequestHttpException("Payment failed with message : ". $ex->getMessage());
        }
	}

	public function getApiContextCurl()
	{
		$fields = array(
			'grant_type' => "client_credentials"
		);

		$fields_string ="";

		//url-ify the data for the POST
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string, '&');

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL,"https://api.sandbox.paypal.com/v1/oauth2/token");
		curl_setopt($ch,CURLOPT_HTTPHEADER,array("Accept: application/json","Accept-Language: fr_FR"));
		curl_setopt($ch,CURLOPT_USERPWD,$this->clientId . ":" . $this->clientSecret);
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

		$result = curl_exec($ch);
		curl_close($ch);

		return $result[''];
	}

	public function createExpressPaymentCurl()
	{
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_URL, 'https://api-3t.sandbox.paypal.com/nvp');
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(array(
			'USER' => 'usuario_da_api',
			'PWD' => '123123123123',
			'SIGNATURE' => 'assinatura.da.api',

			'METHOD' => 'SetExpressCheckout',
			'VERSION' => '108',
			'LOCALECODE' => 'pt_BR',

			'PAYMENTREQUEST_0_AMT' => 100,
			'PAYMENTREQUEST_0_CURRENCYCODE' => 'BRL',
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'PAYMENTREQUEST_0_ITEMAMT' => 100,

			'L_PAYMENTREQUEST_0_NAME0' => 'Exemplo',
			'L_PAYMENTREQUEST_0_DESC0' => 'Assinatura de exemplo',
			'L_PAYMENTREQUEST_0_QTY0' => 1,
			'L_PAYMENTREQUEST_0_AMT0' => 100,
			'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Digital',

			'L_BILLINGTYPE0' => 'RecurringPayments',
			'L_BILLINGAGREEMENTDESCRIPTION0' => 'Exemplo',

			'CANCELURL' => 'http://localhost/cancel.html',
			'RETURNURL' => 'http://localhost/sucesso.html'
		)));

		$response =    curl_exec($curl);

		curl_close($curl);

		$nvp = array();

		if (preg_match_all('/(?<name>[^\=]+)\=(?<value>[^&]+)&?/', $response, $matches)) {
			foreach ($matches['name'] as $offset => $name) {
				$nvp[$name] = urldecode($matches['value'][$offset]);
			}
		}
		if (isset($nvp['ACK']) && $nvp['ACK'] == 'Success') {
			$query = array(
				'cmd'    => '_express-checkout',
				'token'  => $nvp['TOKEN']
			);
		}
		
		return "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=".$query['cmd']."&token=".$query['token'];
	}

	public function paymentExecutionCurl()
	{

	}
}