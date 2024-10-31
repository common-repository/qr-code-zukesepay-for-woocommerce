<?php
/**
 * Open ZukesePay template
 *
 * @package Woo_ZukesePay/Templates
 */
if(!defined('ABSPATH')) {
	exit;
}
?>

<div class="woocommerce">
    <div class="woocommerce-order">
        <section class="woocommerce-order-details">
            <h2 class="woocommerce-order-details__title">Detalhes do pedido</h2>
        </section>
        <div class="woocommerce-message">
                <span>
                    Obrigado. Seu pedido foi recebido. Aguarde a confirmação dos dados da compra em seu email
                </span>
        </div>
        <ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
            <li class="woocommerce-order-overview__order order">
                Número do pedido: <strong><?php echo $order_id;?></strong>
            </li>
            <li class="woocommerce-order-overview__date date">
                Data: <strong><?php echo $order_date; ?></strong>
            </li>
            <li class="woocommerce-order-overview__email email">
                E-mail:	<strong><?php echo $order_billing_email; ?></strong>
            </li>
            <li class="woocommerce-order-overview__total total">
                Total: <strong><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo $order_currency; ?> </span><?php echo $order_total; ?></span></strong>
            </li>
            <li class="woocommerce-order-overview__payment-method method">
                Método de pagamento: <strong><a class="alt" href="<?php echo esc_url($payment_url); ?>" target="_blank"><?php echo __('Zukese Pay', 'woo-zukesepay'); ?></a></strong>
            </li>
        </ul>
    </div>
</div>
