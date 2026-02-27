<?php

namespace ClubTriatlonSulayr\Modules\Subscriptions;

use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles Stripe webhooks: verifies signature and dispatches events.
 */
class StripeWebhookHandler {

    const OPTION_WEBHOOK_SECRET = 'sulayr_stripe_webhook_secret';
    const REST_NAMESPACE        = 'sulayr/v1';
    const REST_ROUTE            = 'stripe-webhook';

    /** @var StripeService */
    private $stripe_service;

    /** @var SubscriptionService */
    private $subscription_service;

    public function __construct( ?StripeService $stripe_service = null, ?SubscriptionService $subscription_service = null ) {
        $this->stripe_service       = $stripe_service ?? new StripeService();
        $this->subscription_service = $subscription_service ?? new SubscriptionService();
    }

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_route' ] );
    }

    public function register_route(): void {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * REST callback: read raw body, verify signature, dispatch event.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_webhook( \WP_REST_Request $request ) {
        $payload = $request->get_body();
        if ( empty( $payload ) ) {
            return new \WP_REST_Response( [ 'error' => 'Missing payload' ], 400 );
        }

        $sig_header = $request->get_header( 'Stripe-Signature' );
        if ( empty( $sig_header ) ) {
            return new \WP_REST_Response( [ 'error' => 'Missing signature' ], 400 );
        }

        $secret = get_option( self::OPTION_WEBHOOK_SECRET, '' );
        if ( empty( $secret ) ) {
            return new \WP_REST_Response( [ 'error' => 'Webhook not configured' ], 500 );
        }

        try {
            $event = Webhook::constructEvent( $payload, $sig_header, $secret );
        } catch ( SignatureVerificationException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Sulayr Stripe webhook signature error: ' . $e->getMessage() );
            }
            return new \WP_REST_Response( [ 'error' => 'Invalid signature' ], 400 );
        } catch ( \UnexpectedValueException $e ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid payload' ], 400 );
        }

        $handled = $this->dispatch_event( $event );
        return new \WP_REST_Response( [ 'received' => true, 'handled' => $handled ], 200 );
    }

    /**
     * Dispatch Stripe event to handler methods.
     *
     * @param \Stripe\Event $event
     * @return bool Whether the event was handled.
     */
    private function dispatch_event( $event ): bool {
        switch ( $event->type ) {
            case 'checkout.session.completed':
                return $this->handle_checkout_session_completed( $event->data->object );
            case 'invoice.payment_succeeded':
                return $this->handle_invoice_payment_succeeded( $event->data->object );
            case 'invoice.payment_failed':
                return $this->handle_invoice_payment_failed( $event->data->object );
            case 'customer.subscription.updated':
                return $this->handle_subscription_updated( $event->data->object );
            case 'customer.subscription.deleted':
                return $this->handle_subscription_deleted( $event->data->object );
            default:
                return false;
        }
    }

    private function handle_checkout_session_completed( $session ): bool {
        if ( ( $session->mode ?? '' ) !== 'subscription' ) {
            return false;
        }
        $session_id = $session->id ?? null;
        if ( ! $session_id ) {
            return false;
        }
        $user_id = $this->subscription_service->completeCheckoutSession( $session_id );
        return $user_id !== null;
    }

    private function handle_invoice_payment_succeeded( $invoice ): bool {
        $customer_id = $invoice->customer ?? null;
        if ( ! $customer_id ) {
            return false;
        }
        $user_id = $this->get_user_id_by_customer_id( $customer_id );
        if ( ! $user_id ) {
            return false;
        }
        $sub_id = $invoice->subscription ?? null;
        if ( $sub_id ) {
            $sub = $this->stripe_service->getSubscription( $sub_id );
            if ( $sub ) {
                $this->subscription_service->syncSubscriptionToUser( $user_id, $sub );
            }
        }
        return true;
    }

    private function handle_invoice_payment_failed( $invoice ): bool {
        $customer_id = $invoice->customer ?? null;
        if ( ! $customer_id ) {
            return false;
        }
        $user_id = $this->get_user_id_by_customer_id( $customer_id );
        if ( $user_id ) {
            do_action( 'sulayr_subscription_payment_failed', $user_id, $invoice );
        }
        return true;
    }

    private function handle_subscription_updated( $subscription ): bool {
        $customer_id = $subscription->customer ?? null;
        if ( ! $customer_id ) {
            return false;
        }
        $user_id = $this->get_user_id_by_customer_id( $customer_id );
        if ( ! $user_id ) {
            return false;
        }
        $this->subscription_service->syncSubscriptionToUser( $user_id, $subscription );
        return true;
    }

    private function handle_subscription_deleted( $subscription ): bool {
        $customer_id = $subscription->customer ?? null;
        if ( ! $customer_id ) {
            return false;
        }
        $user_id = $this->get_user_id_by_customer_id( $customer_id );
        if ( ! $user_id ) {
            return false;
        }
        update_user_meta( $user_id, SubscriptionService::USER_META_STATUS, $subscription->status );
        update_user_meta( $user_id, SubscriptionService::USER_META_NEXT_PAYMENT, '' );
        return true;
    }

    /**
     * Find WordPress user ID by Stripe customer ID (stored in user meta).
     *
     * @param string $customer_id Stripe cus_xxx.
     * @return int|null
     */
    private function get_user_id_by_customer_id( string $customer_id ): ?int {
        $users = get_users( [
            'meta_key'   => SubscriptionService::USER_META_CUSTOMER_ID,
            'meta_value' => $customer_id,
            'number'     => 1,
            'fields'     => 'ID',
        ] );
        if ( empty( $users ) ) {
            return null;
        }
        return (int) $users[0];
    }
}
