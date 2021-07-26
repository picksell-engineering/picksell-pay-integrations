<?php

class Prestashop_picksell_payWebhookModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE); //convert JSON into array
        $headers = apache_request_headers();
        var_dump($_SERVER['HTTP_SECRET']);
    }
}