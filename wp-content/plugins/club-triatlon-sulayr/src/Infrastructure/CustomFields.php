<?php

namespace ClubTriatlonSulayr\Infrastructure;

/**
 * Custom Fields names
 */
class CustomFields
{
    const USER_PROFILE_FIELDS_GROUP = 'user_profile_fields';
    const USER_PROFILE_PHOTO = 'sulayr_user_photo';
    const USER_PROFILE_PHONE = 'sulayr_user_phone';
    const USER_PROFILE_BIRTH = 'sulayr_user_birth_date';
    const USER_PROFILE_JOIN_DATE = 'sulayr_user_join_date';
    const USER_PROFILE_DNI = 'sulayr_user_dni';
    const USER_PROFILE_MEMBERSHIP_PLAN = 'sulayr_user_membership_plan';
    const USER_PROFILE_TRAINING_TYPE = 'sulayr_user_training_type';
    const USER_PROFILE_SWIMMING = 'sulayr_user_swimming';
    const USER_PROFILE_ATHLETE_STATUS = 'sulayr_user_athlete_status';
    /** Miembro de la directiva: solo paga suscripción natación directiva (29,50 €). Solo editable por administrador. */
    const USER_PROFILE_IS_DIRECTIVE = 'sulayr_user_is_directive';

    /** Monedero (wallet) CPT */
    const WALLET_FIELDS_GROUP = 'wallet_fields';
    const WALLET_ATHLETE = 'sulayr_wallet_athlete';
    const WALLET_AMOUNT = 'sulayr_wallet_amount';
    const WALLET_CONCEPT = 'sulayr_wallet_concept';
    const WALLET_DATE = 'sulayr_wallet_date';
    const WALLET_CREATED_BY = 'sulayr_wallet_created_by';

    /** Subscription changes (histórico de cambios de tarifas) CPT */
    const SUBSCRIPTION_CHANGES_FIELDS_GROUP = 'subscription_changes_fields';
    const SUB_CHANGE_USER = 'sulayr_change_user';
    const SUB_CHANGE_MADE_BY = 'sulayr_change_made_by';
    const SUB_CHANGE_PLAN_BEFORE = 'sulayr_change_plan_before';
    const SUB_CHANGE_PLAN_AFTER = 'sulayr_change_plan_after';
    const SUB_CHANGE_SWIMMING_BEFORE = 'sulayr_change_swimming_before';
    const SUB_CHANGE_SWIMMING_AFTER = 'sulayr_change_swimming_after';
    const SUB_CHANGE_DATE = 'sulayr_change_date';
    const SUB_CHANGE_EFFECTIVE_DATE = 'sulayr_change_effective_date';
    const SUB_CHANGE_REASON = 'sulayr_change_reason';
    const SUB_CHANGE_STRIPE_SUBSCRIPTION_ID = 'sulayr_stripe_subscription_id';
}
