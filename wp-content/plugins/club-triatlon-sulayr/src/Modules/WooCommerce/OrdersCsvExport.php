<?php

namespace ClubTriatlonSulayr\Modules\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Acción en lote «Exportar equipación a CSV» en la lista de pedidos.
 *
 * Exporta las prendas (líneas no virtuales) de los pedidos seleccionados y
 * deja una nota privada en cada pedido exportado. Funciona en la pantalla HPOS
 * y en la clásica. Ver docs/club/SPEC-TIENDA-ONLINE.md, apartado 3.5.
 */
class OrdersCsvExport {

    public const ACTION = 'sulayr_export_kit_csv';

    private const CAPABILITY = 'edit_shop_orders';

    /** Pantallas de pedidos: HPOS y clásica. */
    private const SCREENS = [ 'woocommerce_page_wc-orders', 'edit-shop_order' ];

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        foreach ( self::SCREENS as $screen ) {
            add_filter( 'bulk_actions-' . $screen, [ $this, 'add_bulk_action' ] );
            add_filter( 'handle_bulk_actions-' . $screen, [ $this, 'handle_bulk_action' ], 10, 3 );
        }
    }

    /**
     * @param   array $actions Acciones en lote.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_bulk_action( $actions ) {
        if ( current_user_can( self::CAPABILITY ) ) {
            $actions[ self::ACTION ] = __( 'Exportar equipación a CSV', SULAYR_TEXTDOMAIN );
        }

        return $actions;
    }

    /**
     * Genera y descarga el CSV. El nonce de la acción en lote ya lo comprueba
     * WordPress (pantalla clásica) o WooCommerce (HPOS) antes de este filtro.
     *
     * @param   string $redirect_to URL de vuelta.
     * @param   string $action      Acción elegida.
     * @param   array  $ids         Pedidos seleccionados.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_bulk_action( $redirect_to, $action, $ids ) {
        if ( self::ACTION !== $action ) {
            return $redirect_to;
        }
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'No tienes permiso para exportar pedidos.', SULAYR_TEXTDOMAIN ), '', [ 'response' => 403 ] );
        }

        $orders = self::load_orders( (array) $ids );
        $rows   = self::build_rows( $orders );
        self::add_export_notes( $orders, $rows );

        $this->send_csv( $rows );
        exit;
    }

    /**
     * Pedidos válidos ordenados por ID ascendente.
     *
     * @param   array $ids IDs recibidos.
     * @return 	\WC_Order[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function load_orders( array $ids ): array {
        $ids = array_unique( array_filter( array_map( 'absint', $ids ) ) );
        sort( $ids );

        $orders = [];
        foreach ( $ids as $id ) {
            $order = wc_get_order( $id );
            if ( $order instanceof \WC_Order ) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /**
     * Cabecera del CSV.
     *
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function header(): array {
        return [
            __( 'Pedido', SULAYR_TEXTDOMAIN ),
            __( 'Fecha', SULAYR_TEXTDOMAIN ),
            __( 'Estado', SULAYR_TEXTDOMAIN ),
            __( 'Cliente', SULAYR_TEXTDOMAIN ),
            __( 'Email', SULAYR_TEXTDOMAIN ),
            __( 'Teléfono', SULAYR_TEXTDOMAIN ),
            __( 'Producto', SULAYR_TEXTDOMAIN ),
            __( 'Talla', SULAYR_TEXTDOMAIN ),
            __( 'Cantidad', SULAYR_TEXTDOMAIN ),
            __( 'Nombre a estampar', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * Una fila por prenda (línea no virtual) de cada pedido (D10).
     *
     * @param   \WC_Order[] $orders Pedidos.
     * @return 	array<int, array{order_id: int, cells: string[]}>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function build_rows( array $orders ): array {
        $rows = [];
        foreach ( $orders as $order ) {
            $created = $order->get_date_created();
            $common  = [
                (string) $order->get_order_number(),
                $created ? $created->date_i18n( 'd/m/Y' ) : '',
                wc_get_order_status_name( $order->get_status() ),
                $order->get_formatted_billing_full_name(),
                $order->get_billing_email(),
                $order->get_billing_phone(),
            ];

            foreach ( $order->get_items( 'line_item' ) as $item ) {
                $product = $item->get_product();
                if ( $product && $product->is_virtual() ) {
                    continue;
                }

                $rows[] = [
                    'order_id' => $order->get_id(),
                    'cells'    => array_merge(
                        $common,
                        [
                            self::product_name( $item ),
                            self::attributes( $item, $product ),
                            (string) $item->get_quantity(),
                            (string) $item->get_meta( PrintNameField::ITEM_KEY ),
                        ]
                    ),
                ];
            }
        }

        return $rows;
    }

    /**
     * Nota privada en cada pedido con alguna fila exportada (D11).
     *
     * @param   \WC_Order[] $orders Pedidos.
     * @param   array       $rows   Filas de build_rows().
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function add_export_notes( array $orders, array $rows ): void {
        $exported = array_flip( array_column( $rows, 'order_id' ) );
        $stamp    = wp_date( 'd/m/Y H:i' );
        foreach ( $orders as $order ) {
            if ( isset( $exported[ $order->get_id() ] ) ) {
                $order->add_order_note(
                    sprintf(
                        /* translators: %s: fecha y hora */
                        __( 'Incluido en la exportación de equipación del %s.', SULAYR_TEXTDOMAIN ),
                        $stamp
                    )
                );
            }
        }
    }

    /**
     * Escribe el CSV (UTF-8 con BOM, separado por «;») en un recurso.
     *
     * @param   resource $handle Destino.
     * @param   array    $rows   Filas de build_rows().
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function write_csv( $handle, array $rows ): void {
        fwrite( $handle, "\xEF\xBB\xBF" );
        fputcsv( $handle, self::header(), ';', '"', '' );
        foreach ( $rows as $row ) {
            fputcsv( $handle, array_map( [ self::class, 'safe_cell' ], $row['cells'] ), ';', '"', '' );
        }
    }

    /**
     * Evita que Excel interprete una celda como fórmula. Los teléfonos
     * («+34 600 000 000») no se tocan.
     *
     * @param   string $value Valor de la celda.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function safe_cell( $value ): string {
        $value = (string) $value;
        if ( '' === $value ) {
            return $value;
        }
        if ( 1 === preg_match( '/^[=@\t\r]/', $value ) ) {
            return "'" . $value;
        }
        if ( 1 === preg_match( '/^[+\-]/', $value ) && 1 !== preg_match( '/^[+\-]?[0-9 ().]+$/', $value ) ) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * @param   \WC_Order_Item_Product $item Línea.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function product_name( $item ): string {
        $parent = wc_get_product( $item->get_product_id() );

        return $parent ? $parent->get_name() : $item->get_name();
    }

    /**
     * Valores de los atributos de la variación, separados por « / »
     * (p. ej. «M / Con cremallera»).
     *
     * @param   \WC_Order_Item_Product $item    Línea.
     * @param   \WC_Product|false|null $product Producto de la línea.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function attributes( $item, $product ): string {
        $values = [];

        if ( $product && $product->is_type( 'variation' ) ) {
            foreach ( array_keys( $product->get_attributes() ) as $key ) {
                $value = (string) $item->get_meta( $key );
                if ( '' === $value ) {
                    continue;
                }
                if ( taxonomy_exists( $key ) ) {
                    $term  = get_term_by( 'slug', $value, $key );
                    $value = $term ? $term->name : $value;
                }
                $values[] = $value;
            }
            return implode( ' / ', $values );
        }

        // Producto borrado: se usan los metadatos visibles de la línea.
        foreach ( $item->get_formatted_meta_data( '' ) as $meta ) {
            if ( PrintNameField::ITEM_KEY !== $meta->key ) {
                $values[] = trim( wp_strip_all_tags( $meta->display_value ) );
            }
        }

        return implode( ' / ', $values );
    }

    /**
     * Envía el CSV como descarga.
     *
     * @param   array $rows Filas de build_rows().
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function send_csv( array $rows ): void {
        $filename = 'equipacion-pedidos-' . wp_date( 'Ymd-Hi' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'X-Content-Type-Options: nosniff' );

        $out = fopen( 'php://output', 'w' );
        self::write_csv( $out, $rows );
        fclose( $out );
    }
}
