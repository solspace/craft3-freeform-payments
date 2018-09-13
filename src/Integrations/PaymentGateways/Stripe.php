<?php

namespace Solspace\FreeformPayments\Integrations\PaymentGateways;

use Solspace\Freeform\Library\DataObjects\PlanDetails;
use Solspace\FreeformPayments\Fields\CreditCardDetailsField;
use Stripe as StripeAPI;
use GuzzleHttp\Exception\RequestException;
use Solspace\Freeform\Library\Composer\Components\Properties\PaymentProperties;
use Solspace\Freeform\Library\DataObjects\PaymentDetails;
use Solspace\Freeform\Library\DataObjects\SubscriptionDetails;
use Solspace\Freeform\Library\Integrations\DataObjects\FieldObject;
use Solspace\Freeform\Library\Integrations\PaymentGateways\DataObjects\PlanObject;
use Solspace\Freeform\Library\Exceptions\Integrations\IntegrationException;
use Solspace\Freeform\Library\Integrations\IntegrationStorageInterface;
use Solspace\Freeform\Library\Integrations\PaymentGateways\AbstractPaymentGatewayIntegration;
use Solspace\Freeform\Library\Integrations\SettingBlueprint;
use function strtolower;
use Solspace\Freeform\Library\DataObjects\CustomerDetails;
use Solspace\Freeform\Library\Logging\CraftLogger;
use Psr\Log\Test\LoggerInterfaceTest;
use Solspace\Freeform\Library\DataObjects\AddressDetails;
use Solspace\FreeformPayments\FreeformPayments;
use Solspace\FreeformPayments\Models\SubscriptionPlanModel;
use Solspace\FreeformPayments\Services\SubscriptionPlansService;
use Solspace\FreeformPayments\Services\PaymentsService;
use Solspace\FreeformPayments\Services\SubscriptionsService;
use Solspace\FreeformPayments\Models\SubscriptionModel;
use Solspace\FreeformPayments\Models\PaymentModel;
use Solspace\FreeformPayments\Records\PaymentRecord;
use Solspace\Freeform\Library\Payments\PaymentInterface;

class Stripe extends AbstractPaymentGatewayIntegration
{
    const SETTING_PUBLIC_KEY  = 'public_key';
    const SETTING_SECRET_KEY  = 'secret_key';
    const SETTING_WEBHOOK_KEY = 'webhook_key';

    const TITLE        = 'Stripe';
    const LOG_CATEGORY = 'Stripe';

    const PRODUCT_TYPE_SERVICE = 'service';
    const PRODUCT_TYPE_GOOD    = 'good';

    const ZERO_DECIMAL_CURRENCIES = array(
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'
    );

    const PLAN_INTERVAL_CONVERSION = array(
        PaymentProperties::PLAN_INTERVAL_DAILY    => array('interval' => 'day', 'count' => 1),
        PaymentProperties::PLAN_INTERVAL_WEEKLY   => array('interval' => 'week', 'count' => 1),
        PaymentProperties::PLAN_INTERVAL_BIWEEKLY => array('interval' => 'week', 'count' => 2),
        PaymentProperties::PLAN_INTERVAL_MONTHLY  => array('interval' => 'month', 'count' => 1),
        PaymentProperties::PLAN_INTERVAL_ANNUALLY => array('interval' => 'year', 'count' => 1),
    );

    /** @var \Exception */
    protected $lastError = null;

    public static function toStripeAmount($amount, $currency)
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES)) {
            return $amount;
        }

        return floor($amount * 100);
    }

    public static function fromStripeAmount($amount, $currency)
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES)) {
            return $amount;
        }

        return $amount * 0.01;
    }

    public static function fromStripeInterval($interval, $intervalCount)
    {
        $stripeInterval = array('interval' => $interval, 'count' => $intervalCount);
        return array_search($stripeInterval, self::PLAN_INTERVAL_CONVERSION);
    }

    /**
     * Returns a list of additional settings for this integration
     * Could be used for anything, like - AccessTokens
     *
     * @return SettingBlueprint[]
     */
    public static function getSettingBlueprints(): array
    {
        return [
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_PUBLIC_KEY,
                'Public Key',
                'Enter your Stripe public key here.',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_SECRET_KEY,
                'Secret Key',
                'Enter your Stripe secret key here.',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_WEBHOOK_KEY,
                'Webhook Secret',
                'Enter your Stripe webhook secret here.',
                false
            ),
        ];
    }

    public function getWebhookUrl(): string
    {
        if (!$this->getId()) {
            return '';
        }

        return \Craft::getAlias('@web/freeform/payment-webhooks/stripe?id=' . $this->getId());
    }

    /**
     * Check if it's possible to connect to the API
     *
     * @return bool
     */
    public function checkConnection(): bool
    {
        $this->prepareApi();

        try {
            $charges = StripeAPI\Charge::all(['limit' => 1]);
        } catch (\Exception $e) {
            throw new IntegrationException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return $charges instanceof StripeAPI\Collection;
    }

    /**
     * Authorizes the application
     * Returns the access_token
     *
     * @return string
     * @throws IntegrationException
     */
    public function fetchAccessToken(): string
    {
        return $this->getSetting(self::SETTING_SECRET_KEY);
    }

    /**
     * A method that initiates the authentication
     */
    public function initiateAuthentication()
    {
    }

    public function fetchFields(): array
    {
        return include __DIR__ . "/../fields/stripe.php";
    }

    /**
     * Creates payment plan
     *
     * @param PlanDetails $plan
     *
     * @return string|false
     */
    public function createPlan(PlanDetails $plan)
    {
        $this->prepareApi();

        $interval  = self::PLAN_INTERVAL_CONVERSION[strtolower($plan->getInterval())];
        $hash      = $plan->getFormHash();
        $productId = 'freeform' . ($hash ? '_' . $hash : '');

        $product = $this->fetchProduct($productId);
        if ($product === false) {
            return false;
        }

        if ($product) {
            $product = $productId;
        } else {
            //TODO: allow for customization
            $product = array(
                'name' => 'Freeform' . ($plan->getFormName() ? ': ' . $plan->getFormName() : ' Plans'),
                'id' => $productId,
            );
        }

        $params = array(
            'id'             => $plan->getId(),
            'nickname'       => $plan->getName(),
            'amount'         => self::toStripeAmount($plan->getAmount(), $plan->getCurrency()),
            'currency'       => strtolower($plan->getCurrency()),
            'interval'       => $interval['interval'],
            'interval_count' => $interval['count'],
            'product'        => $product,
        );

        try {
            $data = StripeAPI\Plan::create($params);

            $planHandler = $this->getPlanHandler();
            $model       = $planHandler->getByResourceId($data['id'], $this->getId());

            if ($model == null) {
                $model = new SubscriptionPlanModel();
                $model->integrationId = $this->getId();
                $model->resourceId    = $data['id'];
            }
            $model->name = $data['nickname'];

            $planHandler->save($model);
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        return $data['id'];
    }

    public function fetchProduct($id)
    {
        $product = null;
        $this->prepareApi();
        try {
            $product = StripeAPI\Product::retrieve($id);
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        return $product;
    }

    /**
     * @param PaymentDetails $paymentDetails
     * @param PaymentProperties $paymentProperties
     */
    public function processPayment(PaymentDetails $paymentDetails, PaymentProperties $paymentProperties)
    {
        $submissionId = $paymentDetails->getSubmissionId();

        $this->updateSourceOwner($paymentDetails->getToken(), $paymentDetails->getCustomer());

        $params = array(
            'amount' => self::toStripeAmount($paymentDetails->getAmount(), $paymentDetails->getCurrency()),
            'currency' => strtolower($paymentDetails->getCurrency()),
            'source' => $paymentDetails->getToken(),
            'metadata' => array(
                'submission' => $submissionId,
            ),
        );

        $data = $this->charge($params);

        if ($data === false) {
            $this->savePayment(array(), $submissionId);

            return false;
        }

        $data['amount'] = self::fromStripeAmount($data['amount'], $data['currency']);

        return $this->savePayment($data, $submissionId);
    }

    public function processSubscription(SubscriptionDetails $subscriptionDetails, PaymentProperties $paymentProperties)
    {
        $this->prepareApi();

        $source          = $subscriptionDetails->getToken();
        $submissionId    = $subscriptionDetails->getSubmissionId();
        $customerDetails = $subscriptionDetails->getCustomer();
        $planResourceId  = $subscriptionDetails->getPlan();
        $address         = $customerDetails->getAddress() ? $this->convertAddress($customerDetails->getAddress()): null;
        $shipping        = array(
            'name' => $customerDetails->getName(),
            'address' => $address,
        );

        $this->updateSourceOwner($source, $customerDetails);

        try {
            $customer = StripeAPI\Customer::create(array(
                'source' => $source,
                'email' => $customerDetails->getEmail(),
                'description' => $customerDetails->getName(),
                'shipping' => $address ? $shipping : null,
            ));
        } catch (\Exception $e) {
            $this->processError($e);
            $customer = false;
        }

        $data = false;
        if ($customer !== false) {
            $data = $customer->subscriptions->create(array(
                'plan' => $planResourceId,
                'metadata' => array(
                    'submission' => $submissionId,
                ),
            ));

            if ($data === false) {
                $this->saveSubscription(array(), $submissionId, $planResourceId);

                return false;
            }

            $plan = $data['plan'];
            $data['plan']['amount'] = self::fromStripeAmount($plan['amount'], $plan['currency']);
            $data['plan']['interval'] = self::fromStripeInterval($plan['interval'], $plan['interval_count']);

            //TODO: log if this fails
            //we need to save it immediately or we risk hitting webhooks without available record in DB
            $model = $this->saveSubscription($data, $submissionId, $planResourceId);

            try {
                $handler = $this->getSubscriptionHandler();
                $source  = StripeAPI\Source::update($source, array('metadata' => array('subscription' => $data['id'])));

                $model->last4 = $source['card']['last4'];
                $model = $handler->save($model);
            } catch (\Exception $e) {
                //TODO: log error
            }

            return $model;
        }

        return false;
    }

    public function cancelSubscription($resourceId, $atPeriodEnd = true)
    {
        $this->prepareApi();
        try {
            $subscription = StripeAPI\Subscription::retrieve($resourceId);
            $subscription->cancel(['at_period_end' => $atPeriodEnd]);
        } catch (\Exception $e) {
            $this->processError($e);

            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function fetchPlans(): array
    {
        $planHandler = $this->getPlanHandler();

        if (!$this->isForceUpdate()) {
            $plans = $planHandler->getByIntegrationId($this->getId());

            if ($plans) {
                return $plans;
            }
        }

        $plans = array();
        $this->prepareApi();

        try {
            $response = StripeAPI\Plan::all();

            foreach ($response->autoPagingIterator() as $data) {
                $plans[] = new SubscriptionPlanModel(array(
                    'integrationId' => $this->getId(),
                    'resourceId'    => $data['id'],
                    'name'          => $data['nickname'],
                ));
            }
        } catch (\Exception $e) {
            $this->processError($e);

            return $plans;
        }

        $planHandler->updateIntegrationPlans($this->getId(), $plans);

        return $plans;
    }

    /**
     * @inheritdoc
     */
    public function fetchPlan(string $id)
    {
        $planHandler = $this->getPlanHandler();

        //TODO: this function might be unnecessary
        $this->prepareApi();
        try {
            $data = StripeAPI\Plan::retrieve($id);
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        $plan = $planHandler->getByResourceId($data['id'], $this->getId());
        if ($plan === null) {
            $plan = new SubscriptionPlanModel();
        }

        $plan->integrationId = $this->getId();
        $plan->resourceId    = $id;
        $plan->name          = $data['nickname'];

        $planHandler->save($plan);

        return $plan;
    }

    /**
     * Return Stripe details for specific payment
     * If token is provided and no payment was found in DB it tries to recover payment data from gateway
     *
     * @param string $submissionId
     * @param string $token
     *
     * @return array|false
     */
    public function getPaymentDetails(int $submissionId, string $token = '')
    {
        $subscriptionHandler = $this->getSubscriptionHandler();
        $subscription        = $subscriptionHandler->getBySubmissionId($submissionId);
        if ($subscription !== null) {
            return $subscription;
        }

        $paymentHandler = $this->getPaymentHandler();
        $payment        = $paymentHandler->getBySubmissionId($submissionId);
        if ($payment !== null) {
            return $payment;
        }

        if (!$token) {
            return false;
        }

        //TODO: in theory we never should get here
        //TODO: but if we get here we could tie up submission and these charges/subscriptions

        //TODO: from linking subscriptions with wrong submissions

        $this->prepareApi();
        try {
            $source = StripeAPI\Source::retrieve($token);
        } catch (\Exception $e) {
            return $this->processError($e);
        }
        $metadata = $source['metadata'];
        if (isset($metadata['charge'])) {
            $data = $this->getChargeDetails($metadata['charge']);

            return $this->savePayment($data, $submissionId);
        }
        if (isset($metadata['subscription'])) {
            $data = $this->getSubscriptionDetails($metadata['subscription']);
            if ($data === false) {
                return false;
            }
            $data['source'] = $source;

            return $this->saveSubscription($data, $submissionId, $data['plan']['id']);
        }

        return false;
    }

    public function getChargeDetails($id)
    {
        try {
            $charge = StripeAPI\Charge::retrieve($id);
        } catch (\Exception $e) {
            return $this->processError($e);
        }
        $charge = $charge->__toArray();
        //TODO: constants?
        $charge['type'] = 'charge';
        $charge['amount'] = self::fromStripeAmount($charge['amount'], $charge['currency']);

        return $charge;
    }

    public function getSubscriptionDetails($id)
    {
        try {
            $subscription = StripeAPI\Subscription::retrieve($id);
        } catch (\Exception $e) {
            return $this->processError($e);
        }
        $subscription = $subscription->__toArray();
        $subscription['type'] = 'subscription';
        $plan = $subscription['plan'];
        $subscription['plan']['amount'] = self::fromStripeAmount($plan['amount'], $plan['currency']);
        $subscription['plan']['interval'] = self::fromStripeInterval($plan['interval'], $plan['interval_count']);

        return $subscription;
    }

    /**
     * Perform anything necessary before this integration is saved
     *
     * @param IntegrationStorageInterface $model
     */
    public function onBeforeSave(IntegrationStorageInterface $model)
    {
        $model->updateAccessToken($this->getSetting(self::SETTING_SECRET_KEY));
    }

    /**
     * Returns last error happened during Stripe API calls
     *
     * @return \Exception|null
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Returns link to stripe dashboard for selected resource
     *
     * @param string $resourceId stripe resource id
     * @param string $type resource type
     *
     * @return string
     */
    public function getExternalDashboardLink(string $resourceId, string $type): string
    {
        switch($type) {
            case PaymentInterface::TYPE_SINGLE:
                return "https://dashboard.stripe.com/payments/$resourceId";
            case PaymentInterface::TYPE_SUBSCRIPTION:
                return "https://dashboard.stripe.com/subscriptions/$resourceId";
            default:
                return '';
        }
    }

    protected function charge($params)
    {
        $this->prepareApi();

        try {
            $data = StripeAPI\Charge::create($params);

            StripeAPI\Source::update(
                $params['source'],
                array('metadata' => array('charge' => $data['id']))
            );
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        return $data;
    }

    protected function subscribe($params)
    {
        $this->prepareApi();

        //TODO: return something sane
        try {
            $data = StripeAPI\Subscription::create($params);
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        return $data;
    }

    /**
     * Updates source's owner field
     *
     * @param string $id
     * @param CustomerDetails $customer
     *
     * @return void
     */
    protected function updateSourceOwner(string $id, CustomerDetails $customer)
    {
        $this->prepareApi();

        $params = array(
            'owner' => array(
                'name'    => $customer->getName(),
                'email'   => $customer->getEmail(),
                'phone'   => $customer->getPhone(),
            ),
        );

        $address = $customer->getAddress();
        if ($address) {
            $params['owner']['address'] = $this->convertAddress($address);
        }

        try {
            $source = StripeAPI\Source::update($id, $params);
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        return $source;
    }

    /**
     * @return string
     */
    protected function getApiRootUrl(): string
    {
        return 'https://api.stripe.com/';
    }

    protected function prepareApi()
    {
        StripeAPI\Stripe::setApiKey($this->getAccessToken());
        \Stripe\Stripe::setAppInfo("solspace/craft3-freeform-payments", "v1", "https://solspace.com/craft/freeform");

        $this->lastError = null;
    }

    protected function convertAddress(AddressDetails $address)
    {
        return array(
            'line1'       => $address->getLine1(),
            'line2'       => $address->getLine2(),
            'city'        => $address->getCity(),
            'postal_code' => $address->getPostalCode(),
            'state'       => $address->getState(),
            'country'     => $address->getCountry(),
        );
    }

    protected function getPlanHandler(): SubscriptionPlansService
    {
        return FreeformPayments::getInstance()->subscriptionPlans;
    }

    protected function getSubscriptionHandler(): SubscriptionsService
    {
        return FreeformPayments::getInstance()->subscriptions;
    }

    protected function getPaymentHandler(): PaymentsService
    {
        return FreeformPayments::getInstance()->payments;
    }

    /**
     * Saves payment data to db
     *
     * @param array|\Stripe\ApiResource $data
     * @param integer $submissionId
     *
     * @return PaymentModel|false
     */
    protected function savePayment($data, int $submissionId)
    {
        $handler  = $this->getPaymentHandler();

        $model = new PaymentModel(array(
            'integrationId' => $this->getId(),
            'submissionId' => $submissionId,
        ));

        $error = $this->getLastError();
        if ($error) {
            //TODO: we can request charge and get details, but we can end up with failure loop
            //TODO: validate that we have these?

            if ($error->getPrevious() instanceof StripeAPI\Error\Base) {
                $error = $error->getPrevious();
            }

            if ($error instanceof StripeAPI\Error\Base) {
                $data = $error->jsonBody['error'];
                $model->resourceId = isset($data['charge']) ? $data['charge'] : null;
            }

            $model->errorCode    = $error->getCode();
            $model->errorMessage = $error->getMessage();
            $model->status       = PaymentRecord::STATUS_FAILED;
        } else {
            $model->resourceId = $data['id'];
            $model->amount     = $data['amount'];
            $model->currency   = $data['currency'];
            $model->last4      = $data['source']['card']['last4'];
            $model->status     = $data['paid'] ? PaymentRecord::STATUS_PAID : PaymentRecord::STATUS_FAILED;
        }

        $handler->save($model);

        return $model;
    }

    /**
     * Saves submission data to DB
     *
     * @param array|\Stripe\ApiResource $data
     * @param integer $submissionId
     * @param string $planResourceId
     * @return SubscriptionModel|false
     */
    protected function saveSubscription($data, int $submissionId, string $planResourceId)
    {
        $handler     = $this->getSubscriptionHandler();
        $planHandler = $this->getPlanHandler();
        $plan        = $planHandler->getByResourceId($planResourceId, $this->getId());

        $model = new SubscriptionModel(array(
            'integrationId' => $this->getId(),
            'submissionId' => $submissionId,
            'planId' => $plan->getId(),
        ));

        $error = $this->getLastError();
        if ($error) {
            //TODO: we can request charge and get details, but we can end up with failure loop
            //TODO: validate that we have these?

            if ($error->getPrevious() instanceof StripeAPI\Error\Base) {
                $error = $error->getPrevious();
            }

            if ($error instanceof StripeAPI\Error\Base) {
                $data = $error->jsonBody['error'];
                $model->resourceId = isset($data['subscription']) ? $data['subscription'] : null;
            }

            $model->errorCode    = $error->getCode();
            $model->errorMessage = $error->getMessage();
            $model->status       = PaymentRecord::STATUS_FAILED;
        } else {
            $model->resourceId = $data['id'];
            $model->amount = $data['plan']['amount'];
            $model->currency = $data['plan']['currency'];
            $model->interval = $data['plan']['interval'];
            if (isset($data['source'])) {
                $model->last4 = $data['source']['card']['last4'];
            }
            $model->status = $data['status'];
        }

        $handler->save($model);

        return $model;
    }

    /**
     * Catches and logs all Stripe errors, you can get saved error with getLastError()
     *
     * @param \Exception $exception
     * @return bool returns false
     */
    protected function processError($exception)
    {
        $this->lastError = $exception;

        $logger = new CraftLogger();
        $logger->log(CraftLogger::LEVEL_ERROR, $exception);

        switch(get_class($exception)) {
            case 'Stripe\Error\Card':
                break;

            case 'Stripe\Error\InvalidRequest':
                //Resource not found
                if ($exception->getHttpStatus() == 404) {
                    return null;
                }
                // intentional fall through
            case 'Stripe\Error\Authentication':
            case 'Stripe\Error\RateLimit':
            case 'Stripe\Error\ApiConnection':
            case 'Stripe\Error\Permission':
            case 'Stripe\Error\Api':
            case 'Stripe\Error\Idempotency':
            case 'Stripe\Error\Base':
                $message = 'Error while processing your payment, please try later.';
                $this->lastError = new \Exception($message, 0, $exception);
                break;

            default:
                throw $exception;
        }

        return false;
    }
}
