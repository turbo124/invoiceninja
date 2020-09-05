<?php

namespace Tests\Integration\PaymentDrivers;

use App\Factory\PaymentFactory;
use App\Models\CompanyGateway;
use App\PaymentDrivers\AuthorizePaymentDriver;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\contract\v1\CreateCustomerPaymentProfileRequest;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\CreditCardType;
use net\authorize\api\contract\v1\CustomerAddressType;
use net\authorize\api\contract\v1\CustomerPaymentProfileType;
use net\authorize\api\contract\v1\CustomerProfilePaymentType;
use net\authorize\api\contract\v1\CustomerProfileType;
use net\authorize\api\contract\v1\GetCustomerProfileIdsRequest;
use net\authorize\api\contract\v1\GetCustomerProfileRequest;
use net\authorize\api\contract\v1\GetMerchantDetailsRequest;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\contract\v1\PaymentProfileType;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\controller\CreateCustomerPaymentProfileController;
use net\authorize\api\controller\CreateCustomerProfileController;
use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\controller\GetCustomerProfileController;
use net\authorize\api\controller\GetCustomerProfileIdsController;
use net\authorize\api\controller\GetMerchantDetailsController;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class AuthorizeTest extends TestCase
{
    use MockAccountData;

    public $customer_profile_id = 1512373273;

    public $customer_payment_profile = 1512424103;

    public function setUp() :void
    {
        parent::setUp();

        if (! config('ninja.testvars.authorize')) {
            $this->markTestSkipped('authorize.net not configured');
        }

        $this->makeTestData();
    }

    public function testUnpackingVars()
    {
        $vars = json_decode(config('ninja.testvars.authorize'));

        $this->assertTrue(property_exists($vars, 'apiLoginId'));
    }

    public function testCreatePublicClientKey()
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $vars = json_decode(config('ninja.testvars.authorize'));

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($vars->apiLoginId);
        $merchantAuthentication->setTransactionKey($vars->transactionKey);

        $request = new AnetAPI\GetMerchantDetailsRequest();
        $request->setMerchantAuthentication($merchantAuthentication);

        $controller = new GetMerchantDetailsController($request);

        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);

        $this->assertNotNull($response->getPublicClientKey());
    }

    public function testProfileIdList()
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $vars = json_decode(config('ninja.testvars.authorize'));

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($vars->apiLoginId);
        $merchantAuthentication->setTransactionKey($vars->transactionKey);

        // Set the transaction's refId
        $refId = 'ref'.time();

        // Get all existing customer profile ID's
        $request = new GetCustomerProfileIdsRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $controller = new GetCustomerProfileIdsController($request);
        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            info("GetCustomerProfileId's SUCCESS: "."\n");
            info(print_r($response->getIds(), 1));
        } else {
            info("GetCustomerProfileId's ERROR :  Invalid response\n");
            $errorMessages = $response->getMessages()->getMessage();
            info('Response : '.$errorMessages[0]->getCode().'  '.$errorMessages[0]->getText()."\n");
        }

        $this->assertNotNull($response);
    }

    public function testCreateProfile()
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $vars = json_decode(config('ninja.testvars.authorize'));

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($vars->apiLoginId);
        $merchantAuthentication->setTransactionKey($vars->transactionKey);

        // Create the Bill To info for new payment type
        $billTo = new CustomerAddressType();
        $billTo->setFirstName('Ellen');
        $billTo->setLastName('Johnson');
        $billTo->setCompany('Souveniropolis');
        $billTo->setAddress('14 Main Street');
        $billTo->setCity('Pecan Springs');
        $billTo->setState('TX');
        $billTo->setZip('44628');
        $billTo->setCountry('USA');
        $billTo->setPhoneNumber('888-888-8888');
        $billTo->setfaxNumber('999-999-9999');

        // Create a customer shipping address
        $customerShippingAddress = new CustomerAddressType();
        $customerShippingAddress->setFirstName('James');
        $customerShippingAddress->setLastName('White');
        $customerShippingAddress->setCompany('Addresses R Us');
        $customerShippingAddress->setAddress(rand().' North Spring Street');
        $customerShippingAddress->setCity('Toms River');
        $customerShippingAddress->setState('NJ');
        $customerShippingAddress->setZip('08753');
        $customerShippingAddress->setCountry('USA');
        $customerShippingAddress->setPhoneNumber('888-888-8888');
        $customerShippingAddress->setFaxNumber('999-999-9999');

        // Create an array of any shipping addresses
        $shippingProfiles[] = $customerShippingAddress;
        $refId = 'ref'.time();
        $email = 'test12@gmail.com';

        // Create a new CustomerProfileType and add the payment profile object
        $customerProfile = new CustomerProfileType();
        $customerProfile->setDescription('Customer 2 Test PHP');
        $customerProfile->setMerchantCustomerId('M_'.time());
        $customerProfile->setEmail($email);
        //$customerProfile->setpaymentProfiles($paymentProfiles);
        $customerProfile->setShipToList($shippingProfiles);

        // Assemble the complete transaction request
        $request = new AnetAPI\CreateCustomerProfileRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setProfile($customerProfile);

        // Create the controller and get the response
        $controller = new CreateCustomerProfileController($request);
        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);

        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            info('Succesfully created customer profile : '.$response->getCustomerProfileId()."\n");
            $paymentProfiles = $response->getCustomerPaymentProfileIdList();
            info(print_r($paymentProfiles, 1));
        } else {
            info("ERROR :  Invalid response\n");
            $errorMessages = $response->getMessages()->getMessage();
            info('Response : '.$errorMessages[0]->getCode().'  '.$errorMessages[0]->getText()."\n");
        }

        info('the new customer profile id = '.$response->getCustomerProfileId());

        $this->assertNotNull($response);
    }

    public function testGetCustomerProfileId()
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $vars = json_decode(config('ninja.testvars.authorize'));

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($vars->apiLoginId);
        $merchantAuthentication->setTransactionKey($vars->transactionKey);

        $request = new GetCustomerProfileRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setCustomerProfileId($this->customer_profile_id);
        $controller = new GetCustomerProfileController($request);
        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);

        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            info('got profile');
            info(print_r($response->getProfile(), 1));
        } else {
            info("ERROR :  Invalid response\n");
        }

        $this->assertNotNull($response);
    }

    public function testCreateCustomerPaymentProfile()
    {
        info('test create customer payment profile');

        error_reporting(E_ALL & ~E_DEPRECATED);

        $vars = json_decode(config('ninja.testvars.authorize'));

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($vars->apiLoginId);
        $merchantAuthentication->setTransactionKey($vars->transactionKey);

        // Set the transaction's refId
        $refId = 'ref'.time();

        // Set credit card information for payment profile
        $creditCard = new CreditCardType();
        $creditCard->setCardNumber('4111111111111111');
        $creditCard->setExpirationDate('2024-01');
        $creditCard->setCardCode('100');
        $paymentCreditCard = new PaymentType();
        $paymentCreditCard->setCreditCard($creditCard);

        // Create the Bill To info for new payment type
        $billto = new CustomerAddressType();
        $billto->setFirstName('Elas');
        $billto->setLastName('Joson');
        $billto->setCompany('Souveniropolis');
        $billto->setAddress('14 Main Street');
        $billto->setCity('Pecan Springs');
        $billto->setState('TX');
        $billto->setZip('44628');
        $billto->setCountry('USA');
        $billto->setPhoneNumber('999-999-9999');
        $billto->setfaxNumber('999-999-9999');

        // Create a new Customer Payment Profile object
        $paymentprofile = new CustomerPaymentProfileType();
        $paymentprofile->setCustomerType('individual');
        $paymentprofile->setBillTo($billto);
        $paymentprofile->setPayment($paymentCreditCard);
        $paymentprofile->setDefaultPaymentProfile(true);

        $paymentprofiles[] = $paymentprofile;

        // Assemble the complete transaction request
        $paymentprofilerequest = new CreateCustomerPaymentProfileRequest();
        $paymentprofilerequest->setMerchantAuthentication($merchantAuthentication);

        // Add an existing profile id to the request
        $paymentprofilerequest->setCustomerProfileId($this->customer_profile_id);
        $paymentprofilerequest->setPaymentProfile($paymentprofile);
        $paymentprofilerequest->setValidationMode('liveMode');

        // Create the controller and get the response
        $controller = new CreateCustomerPaymentProfileController($paymentprofilerequest);
        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);

        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            info('Create Customer Payment Profile SUCCESS: '.$response->getCustomerPaymentProfileId()."\n");
        } else {
            info("Create Customer Payment Profile: ERROR Invalid response\n");
            $errorMessages = $response->getMessages()->getMessage();
            info('Response : '.$errorMessages[0]->getCode().'  '.$errorMessages[0]->getText()."\n");
        }

        $this->assertNotNull($response);
    }

    public function testChargeCustomerProfile()
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $vars = json_decode(config('ninja.testvars.authorize'));

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($vars->apiLoginId);
        $merchantAuthentication->setTransactionKey($vars->transactionKey);

        // Set the transaction's refId
        $refId = 'ref'.time();

        $profileToCharge = new CustomerProfilePaymentType();
        $profileToCharge->setCustomerProfileId($this->customer_profile_id);
        $paymentProfile = new PaymentProfileType();
        $paymentProfile->setPaymentProfileId($this->customer_payment_profile);
        $profileToCharge->setPaymentProfile($paymentProfile);

        $transactionRequestType = new TransactionRequestType();
        $transactionRequestType->setTransactionType('authCaptureTransaction');
        $transactionRequestType->setAmount(350);
        $transactionRequestType->setProfile($profileToCharge);

        $request = new CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);
        $controller = new CreateTransactionController($request);
        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);

        if ($response != null) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    info(' Transaction Response code : '.$tresponse->getResponseCode()."\n");
                    info('Charge Customer Profile APPROVED  :'."\n");
                    info(' Charge Customer Profile AUTH CODE : '.$tresponse->getAuthCode()."\n");
                    info(' Charge Customer Profile TRANS ID  : '.$tresponse->getTransId()."\n");
                    info(' Code : '.$tresponse->getMessages()[0]->getCode()."\n");
                    info(' Description : '.$tresponse->getMessages()[0]->getDescription()."\n");
                } else {
                    info("Transaction Failed \n");
                    if ($tresponse->getErrors() != null) {
                        info(' Error code  : '.$tresponse->getErrors()[0]->getErrorCode()."\n");
                        info(' Error message : '.$tresponse->getErrors()[0]->getErrorText()."\n");
                    }
                }
            } else {
                info("Transaction Failed \n");
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    info(' Error code  : '.$tresponse->getErrors()[0]->getErrorCode()."\n");
                    info(' Error message : '.$tresponse->getErrors()[0]->getErrorText()."\n");
                } else {
                    info(' Error code  : '.$response->getMessages()->getMessage()[0]->getCode()."\n");
                    info(' Error message : '.$response->getMessages()->getMessage()[0]->getText()."\n");
                }
            }
        } else {
            info("No response returned \n");
        }

        $this->assertNotNull($response);

        $this->assertNotNull($tresponse);

        /* Testing refunds - need to research more as payments are in a pending state so cannot be 'refunded'*/

        // info("transaction reference = " . $tresponse->getTransId());

        // $payment = PaymentFactory::create($this->company->id, $this->user->id);
        // $payment->amount = 400;
        // $payment->client_id = $this->client->id;
        // $payment->date = now();
        // $payment->transaction_reference = $tresponse->getTransId();
        // $payment->company_gateway_id = 1;

        // $payment->save();

        // $company_gateway = CompanyGateway::where('gateway_key', '3b6621f970ab18887c4f6dca78d3f8bb')->first();

        // $authorize_payment_driver = new AuthorizePaymentDriver($company_gateway, $this->client);
        // $response = $authorize_payment_driver->refund($payment, 350);

        // info(print_r($response,1));

        // $this->assertTrue(is_array($response));
    }
}
