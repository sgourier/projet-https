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
	private $accessToken;
	private $expirationDate;

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
		$resultCurlAuth = $this->getApiContextCurl();
		$this->accessToken = $resultCurlAuth['access_token'];
		$this->expirationDate = $resultCurlAuth['expires_in'];
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

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL,"https://api.sandbox.paypal.com/v1/oauth2/token");
		curl_setopt($ch,CURLOPT_HTTPHEADER,array("Accept: application/json","Accept-Language: fr_FR"));
		curl_setopt($ch,CURLOPT_USERPWD,$this->clientId . ":" . $this->clientSecret);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch,CURLOPT_POST, true);
		curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($fields));

		$result = curl_exec($ch);
		curl_close($ch);

		$result = json_decode( $result );

		return array($result);
	}

	public function createExpressPaymentCurl($items,$routeCallbackName,$currency = "EUR",$description = "Payment description",$sale = "sale")
	{
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

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL,"https://api.sandbox.paypal.com/v1/payments/payment");
		curl_setopt($ch,CURLOPT_HTTPHEADER,array("Accept: application/json","Accept-Language: fr_FR","Authorization: ".$this->accessToken));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
			'intent' => 'sale',
			'redirect_urls' => array(
				"return_url" => $this->router->generate($routeCallbackName,array('result'=>"ok"),UrlGeneratorInterface::ABSOLUTE_URL),
				"cancel_url" => $this->router->generate($routeCallbackName,array('result'=>"nok"),UrlGeneratorInterface::ABSOLUTE_URL)
			),
			'payer' => array(
				"payment_method" => "paypal"
			),

			'transactions' => array(
				"amount" => array(
					"total" => $total,
					"currency" => $currency
				),
			    "description" => $description
			)
		)));

		$result = curl_exec($ch);

		curl_close($ch);

		$result = json_decode( $result );

		if($result['state'] == "created")
		{
			foreach ($result['links'] as $link)
			{
				if($link['rel'] == 'approval_url')
				{
					return $link['href'];
				}
			}
		}

		throw new BadRequestHttpException("Payment failed, please try again");

	}

	public function paymentExecutionCurl($paymentId,$payerId)
	{

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL,"https://api.sandbox.paypal.com/v1/payments/payment/".$paymentId."/execute/");
		curl_setopt($ch,CURLOPT_HTTPHEADER,array("Accept: application/json","Accept-Language: fr_FR","Authorization: ".$this->accessToken));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
			'payer_id' => $payerId,
		)));

		$result = curl_exec($ch);
		curl_close($ch);
		
		return $result;
	}
}