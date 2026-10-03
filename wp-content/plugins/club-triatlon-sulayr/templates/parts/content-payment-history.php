<?php
/**
 * Contenido del endpoint Mis pagos (historial de facturas de suscripción).
 * Requiere: $rows (array de [ date, concept, amount, status ]).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sulayr-payment-history-content">
    <?php if ( empty( $rows ) ) : ?>
        <p class="sulayr-payment-empty"><?php esc_html_e( 'No tienes ningún pago de suscripción registrado.', SULAYR_TEXTDOMAIN ); ?></p>
    <?php else : ?>
        <div class="sulayr-payment-table-wrap">
            <table class="sulayr-payment-table sulayr-wallet-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Fecha', SULAYR_TEXTDOMAIN ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Concepto', SULAYR_TEXTDOMAIN ); ?></th>
                        <th scope="col" class="sulayr-wallet-col-amount"><?php esc_html_e( 'Importe', SULAYR_TEXTDOMAIN ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Estado', SULAYR_TEXTDOMAIN ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['date'] ?: '—' ); ?></td>
                            <td><?php echo esc_html( $row['concept'] ?: '—' ); ?></td>
                            <td class="sulayr-wallet-col-amount sulayr-wallet-positive">
                                <?php echo esc_html( number_format_i18n( $row['amount'], 2 ) . ' €' ); ?>
                            </td>
                            <td>
                                <?php
                                $status = $row['status'] ?? 'paid';
                                if ( $status === 'paid' ) {
                                    esc_html_e( 'Pagado', SULAYR_TEXTDOMAIN );
                                } elseif ( $status === 'open' || $status === 'draft' ) {
                                    esc_html_e( 'Pendiente', SULAYR_TEXTDOMAIN );
                                } else {
                                    echo esc_html( $status );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
