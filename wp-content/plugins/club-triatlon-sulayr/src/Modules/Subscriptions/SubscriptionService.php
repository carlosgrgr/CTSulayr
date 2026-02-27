<?php

namespace ClubTriatlonSulayr\Modules\Subscriptions;

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * High-level subscription management: create, update, cancel, get status.
 * Uses StripeService and SubscriptionProducts; stores subscription data in user_meta.
 */
class SubscriptionService {

    const USER_META_CUSTOMER_ID    = 'sulayr_stripe_customer_id';
    const USER_META_SUBSCRIPTION_ID = 'sulayr_stripe_subscription_id';
    const USER_META_STATUS         = 'sulayr_subscription_status';
    const USER_META_PLAN           = 'sulayr_subscription_plan';
    const USER_META_SWIMMING       = 'sulayr_subscription_swimming';
    const USER_META_NEXT_PAYMENT   = 'sulayr_subscription_next_payment';

    /** @var StripeService */
    private $stripe;

    /** @var SubscriptionProducts */
    private $products;

    public function __construct( ?StripeService $stripe = null, ?SubscriptionProducts $products = null ) {
        $this->stripe   = $stripe ?? new StripeService();
        $this->products = $products ?? new SubscriptionProducts();
    }

    /**
     * Whether Stripe is configured and ready for subscriptions.
     */
    public function is_available(): bool {
        return $this->stripe->is_configured();
    }

    /**
     * Get monthly amount in euros for plan + swimming.
     *
     * @param string $plan     basic|premium
     * @param bool   $swimming
     * @return float
     */
    public function getMonthlyAmount( string $plan, bool $swimming ): float {
        $plan_price = $plan === 'premium'
            ? $this->products->get_price_for_type( SubscriptionProducts::MEMBERSHIP_PREMIUM )
            : $this->products->get_price_for_type( SubscriptionProducts::MEMBERSHIP_BASIC );
        $swim_price = $swimming ? $this->products->get_price_for_type( SubscriptionProducts::MEMBERSHIP_SWIMMING ) : 0.0;
        return $plan_price + $swim_price;
    }

    /**
     * Get Stripe Price ID for plan + swimming (find or create).
     *
     * @param string $plan     basic|premium
     * @param bool   $swimming
     * @return string|null
     */
    public function getPriceIdForPlan( string $plan, bool $swimming ): ?string {
        $amount = $this->getMonthlyAmount( $plan, $swimming );
        $cents  = (int) round( $amount * 100 );
        $nick   = $plan . '_' . ( $swimming ? '1' : '0' );
        return $this->stripe->findOrCreatePrice( $cents, $nick );
    }

    /**
     * Get or create Stripe Customer for user; save customer_id in user_meta.
     *
     * @param int $user_id
     * @return string|null Stripe customer ID.
     */
    public function getOrCreateCustomerForUser( int $user_id ): ?string {
        $customer_id = get_user_meta( $user_id, self::USER_META_CUSTOMER_ID, true );
        if ( ! empty( $customer_id ) ) {
            $customer = $this->stripe->getCustomer( $customer_id );
            if ( $customer ) {
                return $customer_id;
            }
        }
        $user = get_userdata( $user_id );
        if ( ! $user || ! $user->user_email ) {
            return null;
        }
        $name = trim( $user->first_name . ' ' . $user->last_name );
        $customer = $this->stripe->createCustomer( $user->user_email, $name, [ 'user_id' => (string) $user_id ] );
        if ( ! $customer ) {
            return null;
        }
        update_user_meta( $user_id, self::USER_META_CUSTOMER_ID, $customer->id );
        return $customer->id;
    }

    /**
     * Create a Checkout Session for the user's current plan and redirect URL.
     * User must have plan (tarifa) set in profile.
     *
     * @param int    $user_id
     * @param string $success_url
     * @param string $cancel_url
     * @return string|null Checkout session URL to redirect to, or null on failure.
     */
    public function createCheckoutSessionForUser( int $user_id, string $success_url, string $cancel_url ): ?string {
        $plan = get_user_meta( $user_id, CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, true );
        $swim = (bool) get_user_meta( $user_id, CustomFields::USER_PROFILE_SWIMMING, true );
        if ( empty( $plan ) || ( $plan !== 'basic' && $plan !== 'premium' ) ) {
            return null;
        }
        $price_id = $this->getPriceIdForPlan( $plan, $swim );
        if ( ! $price_id ) {
            return null;
        }
        $customer_id = $this->getOrCreateCustomerForUser( $user_id );
        $user = get_userdata( $user_id );
        $metadata = [ 'user_id' => (string) $user_id ];
        $session = $this->stripe->createCheckoutSession(
            $price_id,
            $success_url,
            $cancel_url,
            $metadata,
            $customer_id,
            $user ? $user->user_email : null
        );
        if ( ! $session || ! $session->url ) {
            return null;
        }
        return $session->url;
    }

    /**
     * After successful checkout, save subscription and plan from session.
     *
     * @param string $session_id Stripe Checkout Session ID.
     * @return int|null User ID linked to the subscription, or null.
     */
    public function completeCheckoutSession( string $session_id ): ?int {
        $session = $this->stripe->getCheckoutSession( $session_id );
        if ( ! $session || $session->mode !== 'subscription' ) {
            return null;
        }
        $sub_id = $session->subscription ?? ( is_object( $session->subscription ) ? $session->subscription->id : null );
        if ( ! $sub_id ) {
            return null;
        }
        if ( is_object( $sub_id ) ) {
            $sub_id = $sub_id->id;
        }
        $user_id = isset( $session->metadata->user_id ) ? (int) $session->metadata->user_id : null;
        if ( ! $user_id ) {
            return null;
        }
        $subscription = $this->stripe->getSubscription( $sub_id );
        if ( ! $subscription ) {
            return null;
        }
        $this->syncSubscriptionToUser( $user_id, $subscription );
        return $user_id;
    }

    /**
     * Sync Stripe subscription state to user meta.
     *
     * @param int                $user_id
     * @param \Stripe\Subscription $subscription
     */
    public function syncSubscriptionToUser( int $user_id, $subscription ): void {
        update_user_meta( $user_id, self::USER_META_SUBSCRIPTION_ID, $subscription->id );
        update_user_meta( $user_id, self::USER_META_STATUS, $subscription->status );
        if ( ! empty( $subscription->items->data[0]->price->unit_amount ) ) {
            $amount = $subscription->items->data[0]->price->unit_amount / 100;
            $plan = 'basic';
            $swim = false;
            // Derive plan/swimming from amount if needed (22, 33, 47, 58)
            if ( $amount >= 58 ) {
                $plan = 'premium';
                $swim = true;
            } elseif ( $amount >= 47 ) {
                $plan = 'basic';
                $swim = true;
            } elseif ( $amount >= 33 ) {
                $plan = 'premium';
            }
            update_user_meta( $user_id, self::USER_META_PLAN, $plan );
            update_user_meta( $user_id, self::USER_META_SWIMMING, $swim ? '1' : '0' );
        }
        if ( ! empty( $subscription->current_period_end ) ) {
            update_user_meta( $user_id, self::USER_META_NEXT_PAYMENT, date( 'Y-m-d', $subscription->current_period_end ) );
        }
    }

    /**
     * Get active subscription data for user from user_meta (and optionally refresh from Stripe).
     *
     * @param int $user_id
     * @return array{ subscription_id: string|null, status: string, plan: string, swimming: bool, next_payment: string|null }|null
     */
    public function getSubscriptionForUser( int $user_id ): ?array {
        $sub_id = get_user_meta( $user_id, self::USER_META_SUBSCRIPTION_ID, true );
        if ( empty( $sub_id ) ) {
            return null;
        }
        $status = get_user_meta( $user_id, self::USER_META_STATUS, true );
        $plan   = get_user_meta( $user_id, self::USER_META_PLAN, true ) ?: 'basic';
        $swim   = (bool) get_user_meta( $user_id, self::USER_META_SWIMMING, true );
        $next   = get_user_meta( $user_id, self::USER_META_NEXT_PAYMENT, true ) ?: null;
        return [
            'subscription_id' => $sub_id,
            'status'          => $status,
            'plan'            => $plan,
            'swimming'        => $swim,
            'next_payment'    => $next,
        ];
    }

    /**
     * Whether the user has an active Stripe subscription.
     */
    public function hasActiveSubscription( int $user_id ): bool {
        $data = $this->getSubscriptionForUser( $user_id );
        return $data && in_array( $data['status'], [ 'active', 'trialing' ], true );
    }

    /**
     * Update existing subscription to new plan/swimming. Call after validating 5-day rule.
     *
     * @param int    $user_id
     * @param string $new_plan   basic|premium
     * @param bool   $new_swimming
     * @return bool Success.
     */
    public function updateSubscriptionPlan( int $user_id, string $new_plan, bool $new_swimming ): bool {
        $sub_id = get_user_meta( $user_id, self::USER_META_SUBSCRIPTION_ID, true );
        if ( empty( $sub_id ) ) {
            return false;
        }
        $price_id = $this->getPriceIdForPlan( $new_plan, $new_swimming );
        if ( ! $price_id ) {
            return false;
        }
        $sub = $this->stripe->updateSubscriptionPrice( $sub_id, $price_id, false );
        if ( ! $sub ) {
            return false;
        }
        $this->syncSubscriptionToUser( $user_id, $sub );
        update_user_meta( $user_id, CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, $new_plan );
        update_user_meta( $user_id, CustomFields::USER_PROFILE_SWIMMING, $new_swimming ? '1' : '0' );
        return true;
    }

    /**
     * Cancel user's subscription (at period end or immediately).
     *
     * @param int  $user_id
     * @param bool $at_period_end
     * @return bool
     */
    public function cancelSubscription( int $user_id, bool $at_period_end = true ): bool {
        $sub_id = get_user_meta( $user_id, self::USER_META_SUBSCRIPTION_ID, true );
        if ( empty( $sub_id ) ) {
            return false;
        }
        $sub = $this->stripe->cancelSubscription( $sub_id, $at_period_end );
        if ( ! $sub ) {
            return false;
        }
        update_user_meta( $user_id, self::USER_META_STATUS, $sub->status );
        if ( ! $at_period_end ) {
            delete_user_meta( $user_id, self::USER_META_SUBSCRIPTION_ID );
            update_user_meta( $user_id, self::USER_META_NEXT_PAYMENT, '' );
        }
        return true;
    }

    /**
     * Get payment history (invoices) for user from Stripe.
     *
     * @param int $user_id
     * @param int $limit
     * @return array List of { id, amount_paid, status, created, description }.
     */
    public function getPaymentHistory( int $user_id, int $limit = 24 ): array {
        $customer_id = get_user_meta( $user_id, self::USER_META_CUSTOMER_ID, true );
        if ( empty( $customer_id ) ) {
            return [];
        }
        return $this->stripe->listCustomerInvoices( $customer_id, $limit );
    }
}
