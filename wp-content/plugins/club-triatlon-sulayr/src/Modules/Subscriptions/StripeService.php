<?php

namespace ClubTriatlonSulayr\Modules\Subscriptions;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Product;
use Stripe\Price;
use Stripe\Subscription;
use Stripe\Checkout\Session;
use Stripe\Invoice;
use Stripe\Exception\ApiErrorException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Low-level wrapper for Stripe API: customers, prices, subscriptions, checkout.
 */
class StripeService {

    const OPTION_SECRET_KEY   = 'sulayr_stripe_secret_key';
    const CURRENCY            = 'eur';
    const SUBSCRIPTION_PRODUCT_NAME = 'Suscripción Club Triatlón Sulayr';

    /** @var string|null */
    private $secret_key;

    /** @var string|null Cached Stripe Product ID for subscription prices */
    private $product_id;

    public function __construct() {
        $this->secret_key = get_option( self::OPTION_SECRET_KEY, '' );
    }

    /**
     * Whether Stripe is configured (secret key set).
     */
    public function is_configured(): bool {
        return ! empty( $this->secret_key ) && strpos( $this->secret_key, 'sk_' ) === 0;
    }

    /**
     * Set API key and ensure Stripe is initialized.
     */
    private function setup(): void {
        if ( ! empty( $this->secret_key ) ) {
            Stripe::setApiKey( $this->secret_key );
        }
    }

    /**
     * Create or retrieve a Stripe Customer for a user.
     *
     * @param string $email Customer email.
     * @param string $name  Optional display name.
     * @param array  $metadata Optional metadata (e.g. user_id).
     * @return \Stripe\Customer|null
     */
    public function createCustomer( string $email, string $name = '', array $metadata = [] ) {
        $this->setup();
        try {
            $params = [
                'email' => $email,
                'metadata' => $metadata,
            ];
            if ( $name !== '' ) {
                $params['name'] = $name;
            }
            return Customer::create( $params );
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe createCustomer: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Retrieve a Stripe Customer by ID.
     *
     * @param string $customer_id Stripe customer ID (cus_xxx).
     * @return \Stripe\Customer|null
     */
    public function getCustomer( string $customer_id ) {
        $this->setup();
        try {
            return Customer::retrieve( $customer_id );
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe getCustomer: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Get or create the single Stripe Product used for subscription prices.
     *
     * @return string|null Stripe Product ID.
     */
    public function getOrCreateSubscriptionProduct(): ?string {
        if ( $this->product_id ) {
            return $this->product_id;
        }
        $this->setup();
        try {
            $products = Product::all( [
                'active' => true,
                'limit'  => 100,
            ] );
            foreach ( $products->data as $product ) {
                if ( $product->name === self::SUBSCRIPTION_PRODUCT_NAME ) {
                    $this->product_id = $product->id;
                    return $this->product_id;
                }
            }
            $product = Product::create( [
                'name' => self::SUBSCRIPTION_PRODUCT_NAME,
                'metadata' => [ 'sulayr' => '1' ],
            ] );
            $this->product_id = $product->id;
            return $this->product_id;
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe getOrCreateSubscriptionProduct: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Create a recurring Price (monthly) for the given amount.
     *
     * @param int    $amount_cents Amount in cents.
     * @param string $nickname     Optional nickname for the price (e.g. "basic_0").
     * @return string|null Stripe Price ID.
     */
    public function createPrice( int $amount_cents, string $nickname = '' ): ?string {
        $product_id = $this->getOrCreateSubscriptionProduct();
        if ( ! $product_id ) {
            return null;
        }
        $this->setup();
        try {
            $params = [
                'currency'    => self::CURRENCY,
                'unit_amount' => $amount_cents,
                'recurring'   => [ 'interval' => 'month' ],
                'product'     => $product_id,
            ];
            if ( $nickname !== '' ) {
                $params['nickname'] = $nickname;
            }
            $price = Price::create( $params );
            return $price->id;
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe createPrice: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Find an existing Price for the given amount (monthly EUR) or create one.
     *
     * @param int $amount_cents Amount in cents.
     * @param string $nickname Optional nickname to create with.
     * @return string|null Stripe Price ID.
     */
    public function findOrCreatePrice( int $amount_cents, string $nickname = '' ): ?string {
        $product_id = $this->getOrCreateSubscriptionProduct();
        if ( ! $product_id ) {
            return null;
        }
        $this->setup();
        try {
            $prices = Price::all( [
                'active'  => true,
                'product' => $product_id,
                'type'    => 'recurring',
                'limit'   => 100,
            ] );
            foreach ( $prices->data as $price ) {
                if ( (int) $price->unit_amount === $amount_cents && $price->currency === self::CURRENCY ) {
                    return $price->id;
                }
            }
            return $this->createPrice( $amount_cents, $nickname );
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe findOrCreatePrice: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Create a Checkout Session for subscription (redirects to Stripe Hosted Checkout).
     *
     * @param string $customer_id Stripe customer ID (optional; if not set, customer_email is used).
     * @param string $price_id   Stripe Price ID for the subscription.
     * @param string $success_url Full URL to redirect after success.
     * @param string $cancel_url  Full URL to redirect if cancelled.
     * @param array  $metadata   Optional metadata (e.g. user_id).
     * @param string $customer_email Used when customer_id is not provided.
     * @return \Stripe\Checkout\Session|null
     */
    public function createCheckoutSession(
        string $price_id,
        string $success_url,
        string $cancel_url,
        array $metadata = [],
        ?string $customer_id = null,
        ?string $customer_email = null
    ) {
        $this->setup();
        try {
            $params = [
                'mode'       => 'subscription',
                'line_items' => [
                    [
                        'price'    => $price_id,
                        'quantity' => 1,
                    ],
                ],
                'success_url' => $success_url . ( strpos( $success_url, '?' ) !== false ? '&' : '?' ) . 'session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => $cancel_url,
                'metadata'    => $metadata,
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
            ];
            if ( $customer_id ) {
                $params['customer'] = $customer_id;
            } elseif ( $customer_email ) {
                $params['customer_email'] = $customer_email;
            }
            return Session::create( $params );
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe createCheckoutSession: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Retrieve a Checkout Session by ID.
     *
     * @param string $session_id
     * @return \Stripe\Checkout\Session|null
     */
    public function getCheckoutSession( string $session_id ) {
        $this->setup();
        try {
            return Session::retrieve( [
                'id'     => $session_id,
                'expand' => [ 'subscription' ],
            ] );
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe getCheckoutSession: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Retrieve a Subscription by ID.
     *
     * @param string $subscription_id
     * @return \Stripe\Subscription|null
     */
    public function getSubscription( string $subscription_id ) {
        $this->setup();
        try {
            return Subscription::retrieve( $subscription_id );
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe getSubscription: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Update subscription to use a new price (effective next billing cycle or prorate).
     *
     * @param string $subscription_id Stripe subscription ID.
     * @param string $new_price_id    New Stripe Price ID.
     * @param bool   $prorate         Whether to prorate (default true).
     * @return \Stripe\Subscription|null
     */
    public function updateSubscriptionPrice( string $subscription_id, string $new_price_id, bool $prorate = true ) {
        $this->setup();
        try {
            $sub = Subscription::retrieve( $subscription_id );
            $item_id = $sub->items->data[0]->id ?? null;
            if ( ! $item_id ) {
                return null;
            }
            return Subscription::update( $subscription_id, [
                'items' => [
                    [
                        'id'   => $item_id,
                        'price' => $new_price_id,
                    ],
                ],
                'proration_behavior' => $prorate ? 'create_prorations' : 'none',
            ] );
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe updateSubscriptionPrice: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Cancel a subscription at period end or immediately.
     *
     * @param string $subscription_id
     * @param bool   $at_period_end If true, cancel at end of billing period.
     * @return \Stripe\Subscription|null
     */
    public function cancelSubscription( string $subscription_id, bool $at_period_end = false ) {
        $this->setup();
        try {
            if ( $at_period_end ) {
                return Subscription::update( $subscription_id, [ 'cancel_at_period_end' => true ] );
            }
            return Subscription::cancel( $subscription_id );
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe cancelSubscription: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * List invoices for a Stripe Customer.
     *
     * @param string $customer_id Stripe customer ID.
     * @param int    $limit       Max number of invoices (default 24).
     * @return array List of invoice objects (id, amount_paid, status, created, description/line items).
     */
    public function listCustomerInvoices( string $customer_id, int $limit = 24 ): array {
        $this->setup();
        try {
            $invoices = Invoice::all( [
                'customer' => $customer_id,
                'limit'    => $limit,
            ] );
            $out = [];
            foreach ( $invoices->data as $inv ) {
                $desc = '';
                if ( ! empty( $inv->lines->data ) ) {
                    $first = $inv->lines->data[0];
                    $desc = $first->description ?? ( $first->plan->nickname ?? '' );
                }
                $out[] = [
                    'id'           => $inv->id,
                    'amount_paid'  => (int) $inv->amount_paid,
                    'status'       => $inv->status,
                    'created'      => $inv->created,
                    'description'  => $desc,
                ];
            }
            return $out;
        } catch ( ApiErrorException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe listCustomerInvoices: ' . $e->getMessage() );
            }
            return [];
        }
    }
}
