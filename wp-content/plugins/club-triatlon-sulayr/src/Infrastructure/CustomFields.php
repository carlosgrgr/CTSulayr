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

    /** Ficha del socio (SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO §4.1). */
    const MEMBER_NUMBER = 'sulayr_member_number';
    const MEMBER_TYPE = 'sulayr_member_type';
    /** Fecha de baja (Y-m-d) o MEMBER_LEAVE_DATE_UNKNOWN. */
    const MEMBER_LEAVE_DATE = 'sulayr_member_leave_date';
    const MEMBER_LEAVE_DATE_UNKNOWN = 'desconocida';
    const MEMBER_TSHIRT_SIZE = 'sulayr_member_tshirt_size';
    const MEMBER_GUARDIAN = 'sulayr_member_guardian';
    const MEMBER_BOARD_PAYMENT = 'sulayr_member_board_payment';

    /** Valores de sulayr_user_athlete_status. */
    const STATUS_PENDING = 'pendiente';
    const STATUS_ACTIVE = 'active';
    const STATUS_FROZEN = 'congelado';
    const STATUS_LEFT = 'baja';

    /** Monedero (wallet) CPT */
    const WALLET_FIELDS_GROUP = 'wallet_fields';
    const WALLET_ATHLETE = 'sulayr_wallet_athlete';
    const WALLET_AMOUNT = 'sulayr_wallet_amount';
    const WALLET_CONCEPT = 'sulayr_wallet_concept';
    const WALLET_DATE = 'sulayr_wallet_date';
    const WALLET_CREATED_BY = 'sulayr_wallet_created_by';

    /** Servicios del catálogo (CPT sulayr_service). */
    const SERVICE_FIELDS_GROUP = 'service_fields';
    const SERVICE_IS_BASE = 'sulayr_service_is_base';
    const SERVICE_PRICES = 'sulayr_service_prices';
    const SERVICE_PRICE_MEMBER_TYPE = 'member_type';
    const SERVICE_PRICE_AMOUNT = 'amount';
    const SERVICE_PRICE_VALID_FROM = 'valid_from';
    const SERVICE_PRORATED = 'sulayr_service_prorated';
    const SERVICE_CANCEL_ON_FREEZE = 'sulayr_service_cancel_on_freeze';
    const SERVICE_PUBLIC = 'sulayr_service_public';
    const SERVICE_OPTIONS = 'sulayr_service_options';
    const SERVICE_OPTION_KEY = 'key';
    const SERVICE_OPTION_LABEL = 'label';
    const SERVICE_INCOMPATIBLE = 'sulayr_service_incompatible';
    const SERVICE_GRANTS_SWIMMING = 'sulayr_service_grants_swimming';
    const SERVICE_COACH_LIST = 'sulayr_service_coach_list';
    const SERVICE_WELCOME_TEXT = 'sulayr_service_welcome_text';
    const SERVICE_STEPS = 'sulayr_service_steps';
    const SERVICE_STEP_LABEL = 'label';

    /** Documentos CPT (sulayr_document) */
    const DOCUMENT_FIELDS_GROUP = 'document_fields';
    const DOCUMENT_DESCRIPTION = 'sulayr_document_description';
    const DOCUMENT_FILE = 'sulayr_document_file';
    /** Campo ACF que escribe la taxonomía sulayr_doc_category. */
    const DOCUMENT_CATEGORY = 'sulayr_document_category';
    /** Visible para: 'all' (todos los deportistas activos) o 'users' (deportistas concretos). */
    const DOCUMENT_AUDIENCE = 'sulayr_document_audience';
    /** Campo ACF de edición de los destinatarios. */
    const DOCUMENT_USERS = 'sulayr_document_users';
    /** Una fila de postmeta por destinatario, para consultar. */
    const DOCUMENT_USER_INDEX = '_sulayr_document_user';
    /** Fecha del último cambio de archivo. */
    const DOCUMENT_FILE_UPDATED = '_sulayr_document_file_updated';
    /** Marca del adjunto guardado en el directorio privado. */
    const DOCUMENT_PRIVATE_ATTACHMENT = '_sulayr_private_document';
}
