<?php
/**
 * Contenido del endpoint Mi monedero (movimientos del deportista).
 * Requiere: $rows (array de [ date, concept, amount, balance_after ]), $total_balance.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sulayr-wallet-content">
    <div class="sulayr-wallet-balance-block">
        <p class="sulayr-wallet-balance-label"><?php esc_html_e( 'Saldo actual', SULAYR_TEXTDOMAIN ); ?></p>
        <p class="sulayr-wallet-balance-value <?php echo $total_balance >= 0 ? 'sulayr-wallet-positive' : 'sulayr-wallet-negative'; ?>">
            <?php echo esc_html( number_format_i18n( $total_balance, 2 ) . ' €' ); ?>
        </p>
    </div>

    <?php if ( empty( $rows ) ) : ?>
        <p class="sulayr-wallet-empty"><?php esc_html_e( 'No tienes ningún movimiento en tu monedero.', SULAYR_TEXTDOMAIN ); ?></p>
    <?php else : ?>
        <div class="sulayr-wallet-table-wrap">
            <table class="sulayr-wallet-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Fecha', SULAYR_TEXTDOMAIN ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Concepto', SULAYR_TEXTDOMAIN ); ?></th>
                        <th scope="col" class="sulayr-wallet-col-amount"><?php esc_html_e( 'Importe', SULAYR_TEXTDOMAIN ); ?></th>
                        <th scope="col" class="sulayr-wallet-col-balance"><?php esc_html_e( 'Saldo', SULAYR_TEXTDOMAIN ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['date'] ? date_i18n( get_option( 'date_format' ), strtotime( $row['date'] ) ) : '—' ); ?></td>
                            <td><?php echo esc_html( $row['concept'] ?: '—' ); ?></td>
                            <td class="sulayr-wallet-col-amount <?php echo (float) $row['amount'] >= 0 ? 'sulayr-wallet-positive' : 'sulayr-wallet-negative'; ?>">
                                <?php echo esc_html( number_format_i18n( $row['amount'], 2 ) . ' €' ); ?>
                            </td>
                            <td class="sulayr-wallet-col-balance <?php echo (float) $row['balance_after'] >= 0 ? 'sulayr-wallet-positive' : 'sulayr-wallet-negative'; ?>">
                                <?php echo esc_html( number_format_i18n( $row['balance_after'], 2 ) . ' €' ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
