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
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }

    /**
     * @Route("/test", name="payment_test")
     */
    public function testAction(Request $request)
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
        
        $total = 7.49;
        $routeName = "payment_callback";
        $shippingPrice = 0;
        $taxes = 1.50;
        $subTotal = 5.99;
        
        $paymentRoute = $this->get('service_container')->get('paypal_interface')->createExpressPayment($items,$total,$routeName,$shippingPrice,$taxes,$subTotal);
        
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
            $this->get('service_container')->get('paypal_interface')->paymentExecution($request->query->get('paymentId'),$request->query->get('PayerID'));
        }
        else
        {
            return $this->render(':payments:result.html.twig',array('result'=>false));
        }

        return $this->render(':payments:result.html.twig',array('result'=>true));
    }
}
