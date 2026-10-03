<?php

namespace ClubTriatlonSulayr\Modules\Subscriptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Validates that subscription/plan changes are requested with at least 5 days
 * before the start of the next month.
 */
class SubscriptionChangeValidator {

    const MIN_DAYS_BEFORE_MONTH = 5;

    /**
     * Check if a change is allowed today (at least 5 days before the 1st of next month).
     *
     * @param \DateTimeInterface|null $from_date Date to check from (default: now).
     * @return bool
     */
    public function canChangeNow( ?\DateTimeInterface $from_date = null ): bool {
        $from = $from_date ? \DateTime::createFromInterface( $from_date ) : new \DateTime( 'now', wp_timezone() );
        $first_of_next = ( clone $from )->modify( 'first day of next month' )->setTime( 0, 0, 0 );
        $diff = $from->diff( $first_of_next );
        $days_until = (int) $diff->format( '%a' );
        return $days_until >= self::MIN_DAYS_BEFORE_MONTH;
    }

    /**
     * Get the effective date of the change (first day of next month).
     *
     * @param \DateTimeInterface|null $from_date
     * @return string Date in Y-m-d format.
     */
    public function getEffectiveDate( ?\DateTimeInterface $from_date = null ): string {
        $from = $from_date ? \DateTime::createFromInterface( $from_date ) : new \DateTime( 'now', wp_timezone() );
        $first = ( clone $from )->modify( 'first day of next month' )->setTime( 0, 0, 0 );
        return $first->format( 'Y-m-d' );
    }

    /**
     * Get number of days until the first of next month.
     *
     * @param \DateTimeInterface|null $from_date
     * @return int
     */
    public function getDaysUntilNextMonth( ?\DateTimeInterface $from_date = null ): int {
        $from = $from_date ? \DateTime::createFromInterface( $from_date ) : new \DateTime( 'now', wp_timezone() );
        $first_of_next = ( clone $from )->modify( 'first day of next month' )->setTime( 0, 0, 0 );
        $diff = $from->diff( $first_of_next );
        return (int) $diff->format( '%a' );
    }

    /**
     * Get user-facing message when change is not allowed.
     *
     * @return string
     */
    public function getNotAllowedMessage(): string {
        return __( 'Los cambios deben realizarse con al menos 5 días de antelación al inicio del mes.', SULAYR_TEXTDOMAIN );
    }
}
