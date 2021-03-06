<?php

namespace PayPalBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }

    /**
     * @Route("/test", name="payment_test")
     */
    public function testAction()
    {
        $items = array(
            array(
                "name" => 'abo mensuel',
                "currency" => 'EUR',
                "quantity" => 1,
                "ref" => 'Aohdoer8',
                "price" => 5.99
            )
        );
        
        $routeName = "payment_callback";
        $shippingPrice = 0;

        $paymentRoute = $this->get('service_container')->get('paypal_interface')->createExpressPaymentCurl($items,$routeName,$shippingPrice);
        
        return $this->redirect($paymentRoute);
    }

    /**
     * @Route("/paymentCallback/{result}", name="payment_callback" )
     */
    public function paymentCallback($result)
    {
        $request = Request::createFromGlobals();

        if($result == "ok")
        {
            $this->get('service_container')->get('paypal_interface')->paymentExecutionCurl($request->query->get('paymentId'),$request->query->get('PayerID'));
        }
        else
        {
            return $this->render(':payments:result.html.twig',array('result'=>false));
        }

        return $this->render(':payments:result.html.twig',array('result'=>true));
    }
}
