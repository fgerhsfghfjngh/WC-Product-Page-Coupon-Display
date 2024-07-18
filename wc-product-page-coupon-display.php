<?php
/*
Plugin Name: WC Product Page Coupon Display
Description: Displays coupons below the add to cart button on WooCommerce product pages.
Version: 1.0
Author: Pawan Sharma
Author URI: https://airsoftinfotech.com/
*/

// Enqueue custom styles and scripts
function enqueue_custom_styles_and_scripts() {
    // Slick Slider CSS
    wp_enqueue_style('slick-slider-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css');
    wp_enqueue_style('slick-slider-theme-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css');

    // Slick Slider JS
    wp_enqueue_script('slick-slider-js', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js', array('jquery'), '', true);

    // Custom JS to initialize the slider and copy functionality
    wp_add_inline_script('slick-slider-js', '
        jQuery(document).ready(function($) {
            $(".coupon-slider").slick({
                slidesToShow: 3,
                slidesToScroll: 1,
                arrows: false,
                dots: true,
                autoplay: true,
                autoplaySpeed: 2000,
                responsive: [
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1.5,
                            slidesToScroll: 1
                        }
                    }
                ]
            });

            $(".copy-code").on("click", function(event) {
                event.preventDefault();
                var $button = $(this);
                var couponCode = $button.data("code");
                navigator.clipboard.writeText(couponCode).then(function() {
                    showTooltip($button, "Coupon code copied!");
                }, function(err) {
                    console.error("Could not copy text: ", err);
                });
            });

            function showTooltip(element, message) {
                var $tooltip = $("<span class=\'tooltip\'>" + message + "</span>");
                $("body").append($tooltip);
                var offset = element.offset();
                $tooltip.css({
                    top: offset.top - $tooltip.outerHeight() - 10,
                    left: offset.left + (element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                }).fadeIn();
                setTimeout(function() {
                    $tooltip.fadeOut(function() {
                        $(this).remove();
                    });
                }, 2000);
            }
        });
    ');
}
add_action('wp_enqueue_scripts', 'enqueue_custom_styles_and_scripts');

// Display coupons below add to cart button
add_action('woocommerce_after_add_to_cart_button', 'display_dynamic_coupon_code_section', 10);

function display_dynamic_coupon_code_section() {
    global $post;

    $product = wc_get_product($post->ID);

    if (!$product) {
        return;
    }

    // Determine the price based on product type
    if ($product->is_type('variable')) {
        // For variable products, get the minimum variation price
        $price = floatval($product->get_variation_sale_price('min', true));
        if ($price == 0) {
            $price = floatval($product->get_variation_regular_price('min', true));
        }
    } else {
        // For simple products
        $regular_price = floatval($product->get_regular_price());
        $sale_price = floatval($product->get_sale_price());
        $price = $sale_price ? $sale_price : $regular_price;
    }

    // Get all published coupons
    $args = array(
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'post_type' => 'shop_coupon'
    );
    $coupons = get_posts($args);

    // Check if there are any coupons
    if (!$coupons) {
        echo '<!-- No coupons found -->';
        return;
    }

    // Find the best coupon
    $best_coupon = null;
    $best_discount = 0;

    foreach ($coupons as $coupon_post) {
        $coupon = new WC_Coupon($coupon_post->ID);
        $discount_type = $coupon->get_discount_type();
        $coupon_amount = floatval($coupon->get_amount());

        if ($discount_type === 'percent') {
            $discount = ($price * $coupon_amount) / 100;
        } else {
            $discount = $coupon_amount;
        }

        if ($discount > $best_discount) {
            $best_discount = $discount;
            $best_coupon = $coupon;
        }
    }

    // Calculate the best price
    $best_price = $price - $best_discount;

    // Display the best price and best coupon
    if ($best_coupon) {
        $best_coupon_code = $best_coupon->get_code();
        echo '<div class="coupon-code-section">';
        echo '<p class="best-price-text">BEST PRICE: ₹' . number_format($best_price, 2) . '</p>';
        echo '<p>Get ' . esc_html($best_coupon->get_description()) . ' (Apply on checkout)</p>';
        echo '<button class="copy-code" data-code="' . esc_html($best_coupon_code) . '">' . strtoupper(esc_html($best_coupon_code)) . ' <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M7.5 3.75V7.5H3.75V8.25V20.25H16.5V16.5H20.25V3.75H7.5ZM9 5.25H18.75V15H16.5V7.5H9V5.25ZM5.25 9H15V18.75H5.25V9Z" fill="black"></path></svg></button>';
        echo '</div>';
    }

    // Display more offers
    echo '<div class="coupon-code-section">';
    echo '<h3 class="offers-title">More Offers</h3>';
    echo '<div class="coupon-slider">';
    foreach ($coupons as $coupon_post) {
        $coupon = new WC_Coupon($coupon_post->ID);
        $description = $coupon->get_description();
        $code = $coupon->get_code();
        echo '<div class="coupon-offer">';
        echo '<p><strong>' . esc_html($description) . '</strong></p>';
        echo '<p>Use code <code>' . esc_html($code) . '</code> <button class="copy-code" data-code="' . esc_html($code) . '"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M7.5 3.75V7.5H3.75V8.25V20.25H16.5V16.5H20.25V3.75H7.5ZM9 5.25H18.75V15H16.5V7.5H9V5.25ZM5.25 9H15V18.75H5.25V9Z" fill="black"></path></svg></button></p>';
        echo '</div>';
    }
    echo '</div>'; // Close coupon-slider
    echo '</div>'; // Close coupon-code-section
}

// Add custom styles
function add_custom_styles() {
    echo '<style>
    .coupon-code-section {
        border: 1px solid #e0e0e0;
        padding: 10px;
        margin-top: 20px;
        background-color: #f9f9f9;
    }

    .coupon-code-section h3,
    .coupon-code-section .best-price-text {
        font-family: Arial, sans-serif;
        font-size: 18px;
        font-weight: bold;
        color: #333;
        margin-bottom: 10px;
        margin-top: 0;
    }

    .coupon-slider {
        display: flex;
        overflow: hidden;
    }

    .coupon-offer {
        border: 1px dashed green;
        padding: 10px;
        margin-bottom: 10px;
        background-color: #f8fafc;
        margin-right: 10px;
        flex: 0 0 30%;
    }

    .coupon-offer p {
        margin: 0;
    }

    .coupon-offer code {
        background-color: #d1e7dd;
        padding: 2px 4px;
        border-radius: 3px;
    }

    .best-price {
        font-size: 20px;
        font-weight: bold;
        color: green;
    }

    .best-coupon-code {
        border: 1px solid #e0e0e0;
        padding: 5px 10px;
        margin-top: 10px;
        background-color: #f9f9f9;
        cursor: pointer;
    }

    .tooltip {
        position: absolute;
        background-color: #333;
        color: #fff;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        display: none;
        z-index: 1000;
        white-space: nowrap;
    }

    svg {
        margin-left: 5px;
        vertical-align: middle;
    }

    @media (max-width: 768px) {
        .coupon-offer {
            flex: 0 0 66.666%;
        }
    }
    </style>';
}
add_action('wp_head', 'add_custom_styles');
?>
