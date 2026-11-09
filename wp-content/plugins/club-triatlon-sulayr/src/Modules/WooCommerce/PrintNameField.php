<?php

namespace ClubTriatlonSulayr\Modules\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Nombre a estampar en prendas personalizables (tritrajes).
 *
 * Casilla en el producto, campo opcional en la ficha, validación, línea del
 * carrito, línea del pedido y «Pedir de nuevo». Ver docs/club/SPEC-TIENDA-ONLINE.md.
 */
class PrintNameField {

    /** Meta del producto que activa el campo ('yes'). */
    public const PRODUCT_META = '_sulayr_print_name_enabled';

    /** Clave en la línea del carrito y en la meta de la línea de pedido. */
    public const ITEM_KEY = 'sulayr_print_name';

    /** Nombre del campo del formulario de la ficha. */
    private const FIELD_NAME = 'sulayr_print_name';

    public const MAX_LENGTH = 12;

    /** Letra + tilde combinada → letra compuesta, para cuando no hay intl. */
    private const DECOMPOSED = [
        "A\u{0301}" => 'Á',
        "a\u{0301}" => 'á',
        "E\u{0301}" => 'É',
        "e\u{0301}" => 'é',
        "I\u{0301}" => 'Í',
        "i\u{0301}" => 'í',
        "O\u{0301}" => 'Ó',
        "o\u{0301}" => 'ó',
        "U\u{0301}" => 'Ú',
        "u\u{0301}" => 'ú',
        "U\u{0308}" => 'Ü',
        "u\u{0308}" => 'ü',
        "N\u{0303}" => 'Ñ',
        "n\u{0303}" => 'ñ',
    ];

    /** Letras A–Z, vocales con tilde, Ü, Ñ, números y espacio (D3, D8, D9). */
    private const ALLOWED_PATTERN = '/^[A-Z0-9ÁÉÍÓÚÜÑ ]+$/u';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_product_option' ] );
        add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_product_option' ] );

        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_field' ] );
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 10, 2 );
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 2 );
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_data' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 3 );
        add_filter( 'woocommerce_order_item_display_meta_key', [ $this, 'display_meta_key' ], 10, 2 );
        add_filter( 'woocommerce_order_again_cart_item_data', [ $this, 'order_again_cart_item_data' ], 10, 2 );
    }

    /**
     * Normaliza el texto: recorta, une espacios repetidos y pasa a mayúsculas.
     *
     * @param   string $value Texto escrito por el cliente.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function normalize( string $value ): string {
        // UTF-8 no válido: se devuelve tal cual para que la validación lo rechace.
        if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
            return $value;
        }
        if ( class_exists( '\Normalizer' ) ) {
            $normalized = \Normalizer::normalize( $value, \Normalizer::FORM_C );
            if ( false !== $normalized ) {
                $value = $normalized;
            }
        }

        // Sin la extensión intl: recompone al menos las letras del español
        // escritas como letra + tilde combinada (algunos teclados de Mac e iOS).
        $value = strtr( $value, self::DECOMPOSED );

        $value = preg_replace( '/\s+/u', ' ', $value );
        if ( null === $value ) {
            return '';
        }

        return mb_strtoupper( trim( $value ), 'UTF-8' );
    }

    /**
     * Devuelve el mensaje de error del texto ya normalizado, o '' si es válido.
     * Un texto vacío es válido: el nombre es opcional (D2).
     *
     * @param   string $value Texto normalizado.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function validation_error( string $value ): string {
        if ( '' === $value ) {
            return '';
        }
        if ( 1 !== preg_match( self::ALLOWED_PATTERN, $value ) ) {
            return __( 'El nombre solo puede llevar letras, números y espacios.', SULAYR_TEXTDOMAIN );
        }
        if ( mb_strlen( $value, 'UTF-8' ) > self::MAX_LENGTH ) {
            return sprintf(
                /* translators: %d: número máximo de caracteres */
                __( 'El nombre puede tener como máximo %d caracteres.', SULAYR_TEXTDOMAIN ),
                self::MAX_LENGTH
            );
        }

        return '';
    }

    /**
     * Indica si el producto (o el padre de una variación) admite nombre.
     *
     * @param   int $product_id Producto o variación.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_enabled( int $product_id ): bool {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return false;
        }
        if ( $product->is_type( 'variation' ) ) {
            $product = wc_get_product( $product->get_parent_id() );
            if ( ! $product ) {
                return false;
            }
        }

        return 'yes' === $product->get_meta( self::PRODUCT_META );
    }

    /**
     * Casilla «Permitir nombre a estampar» en la pestaña General del producto.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_product_option(): void {
        echo '<div class="options_group">';
        woocommerce_wp_checkbox(
            [
                'id'          => self::PRODUCT_META,
                'label'       => __( 'Permitir nombre a estampar', SULAYR_TEXTDOMAIN ),
                'description' => __( 'Muestra en la ficha una caja opcional para el nombre a estampar en la prenda.', SULAYR_TEXTDOMAIN ),
            ]
        );
        echo '</div>';
    }

    /**
     * Guarda la casilla. WooCommerce ya ha comprobado el nonce y los permisos.
     *
     * @param   \WC_Product $product Producto que se guarda.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function save_product_option( $product ): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST[ self::PRODUCT_META ] ) ) {
            $product->update_meta_data( self::PRODUCT_META, 'yes' );
        } else {
            $product->delete_meta_data( self::PRODUCT_META );
        }
    }

    /**
     * Campo en la ficha, encima del botón «Añadir al carrito».
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_field(): void {
        global $product;
        if ( ! $product instanceof \WC_Product || ! self::is_enabled( $product->get_id() ) ) {
            return;
        }

        // Tras un error de validación se conserva lo escrito.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $value = isset( $_POST[ self::FIELD_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD_NAME ] ) ) : '';
        ?>
        <div class="sulayr-print-name" style="margin-bottom:1em;">
            <label for="sulayr-print-name" style="display:block;font-weight:600;margin-bottom:.3em;">
                <?php esc_html_e( 'Nombre a estampar (opcional)', SULAYR_TEXTDOMAIN ); ?>
            </label>
            <input type="text" id="sulayr-print-name" name="<?php echo esc_attr( self::FIELD_NAME ); ?>"
                value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo esc_attr( (string) self::MAX_LENGTH ); ?>"
                autocomplete="off" aria-describedby="sulayr-print-name-help"
                style="text-transform:uppercase;max-width:100%;width:16em;">
            <small id="sulayr-print-name-help" style="display:block;margin-top:.3em;">
                <?php
                printf(
                    /* translators: %d: número máximo de caracteres */
                    esc_html__( 'Hasta %d caracteres: letras, números y espacios. Se estampará en mayúsculas.', SULAYR_TEXTDOMAIN ),
                    (int) self::MAX_LENGTH
                );
                ?>
            </small>
        </div>
        <?php
    }

    /**
     * Valida el nombre al añadir al carrito.
     *
     * @param   bool $passed     Resultado de validaciones anteriores.
     * @param   int  $product_id Producto padre.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function validate_add_to_cart( $passed, $product_id ) {
        $raw = $this->posted_value();
        if ( null === $raw || ! self::is_enabled( (int) $product_id ) ) {
            return $passed;
        }

        $error = self::validation_error( self::normalize( $raw ) );
        if ( '' !== $error ) {
            wc_add_notice( $error, 'error' );
            return false;
        }

        return $passed;
    }

    /**
     * Guarda el nombre normalizado en la línea del carrito. Un nombre distinto
     * da otra línea; el mismo nombre suma cantidad.
     *
     * @param   array $cart_item_data Datos de la línea.
     * @param   int   $product_id     Producto padre.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_cart_item_data( $cart_item_data, $product_id ) {
        $raw = $this->posted_value();
        // Sin envío del formulario (p. ej. «Pedir de nuevo») se respeta lo que llegue.
        if ( null === $raw ) {
            return $cart_item_data;
        }

        unset( $cart_item_data[ self::ITEM_KEY ] );
        if ( ! self::is_enabled( (int) $product_id ) ) {
            return $cart_item_data;
        }

        $value = self::normalize( $raw );
        if ( '' !== $value && '' === self::validation_error( $value ) ) {
            $cart_item_data[ self::ITEM_KEY ] = $value;
        }

        return $cart_item_data;
    }

    /**
     * Muestra el nombre en el carrito y en la página de compra.
     *
     * @param   array $item_data Datos que se muestran bajo el producto.
     * @param   array $cart_item Línea del carrito.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( ! empty( $cart_item[ self::ITEM_KEY ] ) ) {
            $item_data[] = [
                'key'     => __( 'Nombre a estampar', SULAYR_TEXTDOMAIN ),
                'value'   => $cart_item[ self::ITEM_KEY ],
                'display' => esc_html( $cart_item[ self::ITEM_KEY ] ),
            ];
        }

        return $item_data;
    }

    /**
     * Copia el nombre a la línea del pedido.
     *
     * @param   \WC_Order_Item_Product $item          Línea del pedido.
     * @param   string                 $cart_item_key Clave de la línea del carrito.
     * @param   array                  $values        Línea del carrito.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_order_item_meta( $item, $cart_item_key, $values ): void {
        if ( ! empty( $values[ self::ITEM_KEY ] ) ) {
            $item->add_meta_data( self::ITEM_KEY, $values[ self::ITEM_KEY ], true );
        }
    }

    /**
     * Etiqueta visible de la meta en pedidos, correos y Mi cuenta.
     *
     * @param   string        $display_key Etiqueta actual.
     * @param   \WC_Meta_Data $meta        Meta de la línea.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function display_meta_key( $display_key, $meta ) {
        if ( isset( $meta->key ) && self::ITEM_KEY === $meta->key ) {
            return __( 'Nombre a estampar', SULAYR_TEXTDOMAIN );
        }

        return $display_key;
    }

    /**
     * «Pedir de nuevo» conserva el nombre si el producto sigue admitiéndolo.
     *
     * @param   array                  $cart_item_data Datos de la nueva línea.
     * @param   \WC_Order_Item_Product $item           Línea del pedido original.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function order_again_cart_item_data( $cart_item_data, $item ) {
        $value = self::normalize( (string) $item->get_meta( self::ITEM_KEY ) );
        if ( '' !== $value && '' === self::validation_error( $value ) && self::is_enabled( (int) $item->get_product_id() ) ) {
            $cart_item_data[ self::ITEM_KEY ] = $value;
        }

        return $cart_item_data;
    }

    /**
     * Valor enviado en el formulario de la ficha, o null si no se envió.
     *
     * @return 	string|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function posted_value(): ?string {
        // WooCommerce no usa nonce al añadir al carrito; el valor solo se guarda tras validarlo.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST[ self::FIELD_NAME ] ) || ! is_string( $_POST[ self::FIELD_NAME ] ) ) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidInputSanitization.InputNotSanitized
        return wp_unslash( $_POST[ self::FIELD_NAME ] );
    }
}
