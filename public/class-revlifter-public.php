<?php
 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://revlifter.com
 * @since      1.0.0
 *
 * @package    RevLifter
 * @subpackage Revlifter/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    RevLifter
 * @subpackage RevLifter/public
 * @author     RevLifter
 */
class Revlifter_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       RevLifter
	 * @param      string    $version    1.0.0
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action( 'woocommerce_after_single_product_summary' , [$this,'revlifter_product_details']);
		add_action( 'woocommerce_thankyou', [$this,'revlifter_sale'] );
		add_action( 'wp_enqueue_scripts', [$this,'basket_enqueue_script'] );
		add_action( 'wp_ajax_basket_update_cart_total', [$this,'basket_update_cart_total_callback'] );
		add_action( 'wp_ajax_nopriv_basket_update_cart_total', [$this,'basket_update_cart_total_callback'] );
		
		


	}

	public function basket_enqueue_script() {
		wp_enqueue_script( 'basket-script', plugin_dir_url( __FILE__ ) . 'js/basket-script.js', array( 'jquery', 'wp-ajax' ), '1.0', true );
		wp_localize_script('basket-script', 'customAjax', array(
			'ajaxUrl' => admin_url('admin-ajax.php')
		));
	}

	public function basket_update_cart_total_callback() {
		
		$cart = WC()->cart;
		$products=array();
		$products["schema"]="1.1";
		$products["type"]="basket";
		$products['items']=[];
		if(!$cart->is_empty()){
			// Loop over $cart items
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$product_arr=array();
				$product = $cart_item['data'];
				// Get Product ID
				$product_arr['id']=(string) $product->get_id();
				$product_arr['sku']=$product->get_sku();
				$product_arr['name']=$product->get_name();
				$product_arr['price']=round((float) number_format($product->get_price(), 2, '.', ''),2);
				$product_arr['quantity']=(int) $cart_item['quantity'];
				$product_arr['brand']=$this->get_brand_name($product);	
				$terms =  get_the_terms ($product->get_id(), 'product_cat' );
				$categories=array();
				foreach($terms as $item){
				$categories[]=$item->name;
				}
				$product_arr['category']=$categories[0];
				$product_arr['categories']=$categories;
	
				$product_details = $product->get_data();
				$product_arr["detail"]=wp_strip_all_tags($product_details['description']);
				
				$image_url=wp_get_attachment_image_src( get_post_thumbnail_id($product->get_id()), 'single-post-thumbnail' );
				$product_arr["imageURL"]=$image_url[0];
				$product_arr['stock']=$this->getstockdetails($product);
				
				$product_arr['promotion']=$this->get_product_promotion_details($product);
				
				$products['items'][]=$product_arr;
			}
		}
		global  $woocommerce;
	    $products['currency']=get_woocommerce_currency();		
		$tax_rate=0;
		$taxes = $cart->get_taxes();
		// Loop through the taxes to find the applied tax rate
		if(!empty($taxes)){
			foreach ($taxes as $tax) {
				// Get the tax rate for each tax item
				$tax_rate = $tax_rate+$tax['rate_percent'];
			}
		}
		$products["taxRate"]=(float) $tax_rate;
	    //$products["taxRate"]=(float) number_format($cart->get_taxes_total(), 2, '.', '');
	    $products['shipping'] = (float) number_format($cart->get_shipping_total(), 2, '.', '');
	    $products['basePrice'] = (float) number_format($cart->subtotal_ex_tax, 2, '.', '');
		$voucherDiscount = 0.0;
		if ($cart->get_cart_contents_total() > 0) {
			$appliedCoupons = $cart->get_applied_coupons();
			if (!empty($appliedCoupons) && $cart->get_discount_total() > 0) {
				$voucherDiscount = (float) number_format($cart->get_discount_total(), 2, '.', '');
			}
		}
		$products['voucherDiscount'] = $voucherDiscount;
	    //$products['voucherDiscount'] = (float) number_format($cart->get_discount_total(), 2, '.', '');
	    $products['cartTotal'] = (float) $products['basePrice']+$products['shipping']-$products['voucherDiscount'];
	    //$products['voucherCode'] = implode(',', $cart->get_coupons());
	    $products['voucherCode'] = implode(',', $this->get_applied_coupons_on_cart());
		$products['priceWithTax'] = (float) number_format(($products['cartTotal'] + $cart->get_taxes_total()), 2, '.', '');

		// Get shipping method
		$shipping_method='';
		$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
		if(!empty($chosen_shipping_methods)){
			if($chosen_shipping_methods[0]){
				$shipping_method=$chosen_shipping_methods[0];
				$shipping_method_parts = explode(':', $shipping_method);
				if(!empty($shipping_method_parts)){
					if(isset($shipping_method_parts[0])){
						$shipping_method_name=$this->get_shipping_method_name($shipping_method_parts[0]);
						if($shipping_method_name){
							$shipping_method=$shipping_method_name;
						}
					}
				}
			}
		}
		$products['shippingMethod'] = $shipping_method;
		// Return the response
		wp_send_json_success( array( '_rl_basket' => $products) );
	}

	function get_shipping_method_name($shipping_method_id){
		$shipping_method_name='';
		// Step 1: Get the shipping method instance by ID.
		$shipping_methods = WC()->shipping()->get_shipping_methods();
		if (isset($shipping_methods[$shipping_method_id])) {
			$shipping_method_instance = $shipping_methods[$shipping_method_id];
			
			// Step 2: Get the shipping method name.
			$shipping_method_name = $shipping_method_instance->method_title;
			
			// Output the shipping method name.
			
		} 
		return $shipping_method_name;
	}

	function get_applied_coupons_on_cart() {
		$applied_coupons = array();
	
		// Check if WooCommerce is active
		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			// Get the cart instance
			$cart = WC()->cart;
	
			// Get the applied coupons from the cart
			$applied_coupons = $cart->get_applied_coupons();
		}
	
		return $applied_coupons;
	}

	public function get_product_promotion_details($product) {
		// Get promotion details
		$promotion_arr = [
			'isOnPromotion' => false
		];
	
		$regular_price = (float) $product->get_regular_price();
		$sale_price = (float) $product->get_sale_price();
		$is_on_promotion = $regular_price > $sale_price && $sale_price;
		$promotion_end_date = get_post_meta($product->get_id(), '_sale_price_dates_to', true);
	
		if ($is_on_promotion && !empty($promotion_end_date)) {
			$promotion_end_timestamp = $promotion_end_date;
			$current_timestamp = current_time('timestamp');
	
			if ($promotion_end_timestamp > $current_timestamp) {
				$promotion_arr = [
					'isOnPromotion' => true,
					'promotionPrice' => (float) number_format($sale_price, 2, '.', ''),
					'originalPrice' => (float) number_format($regular_price, 2, '.', ''),
					'promotionName' => get_post_meta($product->get_id(), 'promotion_name', true),
				];
			}else{
				$promotion_arr = [
					'isOnPromotion' => false
				];
			}
		}else if($is_on_promotion && empty($promotion_end_date)){
			$promotion_arr = [
				'isOnPromotion' => true,
				'promotionPrice' => (float) number_format($sale_price, 2, '.', ''),
				'originalPrice' => (float) number_format($regular_price, 2, '.', ''),
				'promotionName' => get_post_meta($product->get_id(), 'promotion_name', true),
			];
		}else{
			$promotion_arr = [
				'isOnPromotion' => false
			];
		}
	
		return $promotion_arr;
	}
	



	public function get_option( $option, $default = false ) {
		global $wpdb;

		if ( is_scalar( $option ) ) {
			$option = trim( $option );
		}

		if ( empty( $option ) ) {
			return false;
		}

		/*
		 * Until a proper _deprecated_option() function can be introduced,
		 * redirect requests to deprecated keys to the new, correct ones.
		 */
		$deprecated_keys = array(
			'blacklist_keys'    => 'disallowed_keys',
			'comment_whitelist' => 'comment_previously_approved',
		);

		if ( isset( $deprecated_keys[ $option ] ) && ! wp_installing() ) {
			_deprecated_argument(
				__FUNCTION__,
				'5.5.0',
				sprintf(
					/* translators: 1: Deprecated option key, 2: New option key. */
					__( 'The "%1$s" option key has been renamed to "%2$s".' ),
					$option,
					$deprecated_keys[ $option ]
				)
			);
			return get_option( $deprecated_keys[ $option ], $default );
		}

		/**
		 * Filters the value of an existing option before it is retrieved.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * Returning a value other than false from the filter will short-circuit retrieval
		 * and return that value instead.
		 *
		 * @since 1.5.0
		 * @since 4.4.0 The `$option` parameter was added.
		 * @since 4.9.0 The `$default` parameter was added.
		 *
		 * @param mixed  $pre_option The value to return instead of the option value. This differs
		 *                           from `$default`, which is used as the fallback value in the event
		 *                           the option doesn't exist elsewhere in get_option().
		 *                           Default false (to skip past the short-circuit).
		 * @param string $option     Option name.
		 * @param mixed  $default    The fallback value to return if the option does not exist.
		 *                           Default false.
		 */
		$pre = apply_filters( "pre_option_{$option}", false, $option, $default );

		/**
		 * Filters the value of all existing options before it is retrieved.
		 *
		 * Returning a truthy value from the filter will effectively short-circuit retrieval
		 * and return the passed value instead.
		 *
		 * @since 6.1.0
		 *
		 * @param mixed  $pre_option  The value to return instead of the option value. This differs
		 *                            from `$default`, which is used as the fallback value in the event
		 *                            the option doesn't exist elsewhere in get_option().
		 *                            Default false (to skip past the short-circuit).
		 * @param string $option      Name of the option.
		 * @param mixed  $default     The fallback value to return if the option does not exist.
		 *                            Default false.
		 */
		$pre = apply_filters( 'pre_option', $pre, $option, $default );

		if ( false !== $pre ) {
			return $pre;
		}

		if ( defined( 'WP_SETUP_CONFIG' ) ) {
			return false;
		}

		// Distinguish between `false` as a default, and not passing one.
		$passed_default = func_num_args() > 1;

		if ( ! wp_installing() ) {
			// Prevent non-existent options from triggering multiple queries.
			$notoptions = wp_cache_get( 'notoptions', 'options' );

			// Prevent non-existent `notoptions` key from triggering multiple key lookups.
			if ( ! is_array( $notoptions ) ) {
				$notoptions = array();
				wp_cache_set( 'notoptions', $notoptions, 'options' );
			}

			if ( isset( $notoptions[ $option ] ) ) {
				/**
				 * Filters the default value for an option.
				 *
				 * The dynamic portion of the hook name, `$option`, refers to the option name.
				 *
				 * @since 3.4.0
				 * @since 4.4.0 The `$option` parameter was added.
				 * @since 4.7.0 The `$passed_default` parameter was added to distinguish between a `false` value and the default parameter value.
				 *
				 * @param mixed  $default The default value to return if the option does not exist
				 *                        in the database.
				 * @param string $option  Option name.
				 * @param bool   $passed_default Was `get_option()` passed a default value?
				 */
				return apply_filters( "default_option_{$option}", $default, $option, $passed_default );
			}

			$alloptions = wp_load_alloptions();

			if ( isset( $alloptions[ $option ] ) ) {
				$value = $alloptions[ $option ];
			} else {
				$value = wp_cache_get( $option, 'options' );

				if ( false === $value ) {
					$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );

					// Has to be get_row() instead of get_var() because of funkiness with 0, false, null values.
					if ( is_object( $row ) ) {
						$value = $row->option_value;
						wp_cache_add( $option, $value, 'options' );
					} else { // Option does not exist, so we must cache its non-existence.
						if ( ! is_array( $notoptions ) ) {
							$notoptions = array();
						}

						$notoptions[ $option ] = true;
						wp_cache_set( 'notoptions', $notoptions, 'options' );

						/** This filter is documented in wp-includes/option.php */
						return apply_filters( "default_option_{$option}", $default, $option, $passed_default );
					}
				}
			}
		} else {
			$suppress = $wpdb->suppress_errors();
			$row      = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
			$wpdb->suppress_errors( $suppress );

			if ( is_object( $row ) ) {
				$value = $row->option_value;
			} else {
				/** This filter is documented in wp-includes/option.php */
				return apply_filters( "default_option_{$option}", $default, $option, $passed_default );
			}
		}

		// If home is not set, use siteurl.
		if ( 'home' === $option && '' === $value ) {
			return get_option( 'siteurl' );
		}

		if ( in_array( $option, array( 'siteurl', 'home', 'category_base', 'tag_base' ), true ) ) {
			$value = untrailingslashit( $value );
		}

		/**
		 * Filters the value of an existing option.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * @since 1.5.0 As 'option_' . $setting
		 * @since 3.0.0
		 * @since 4.4.0 The `$option` parameter was added.
		 *
		 * @param mixed  $value  Value of the option. If stored serialized, it will be
		 *                       unserialized prior to being returned.
		 * @param string $option Option name.
		 */
		return apply_filters( "option_{$option}", maybe_unserialize( $value ), $option );
	}


	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/revlifter-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/revlifter-public.js', array( 'jquery' ), $this->version, false);
		
		wp_register_script( 'revlifter_script', 'https://assets.revlifter.io/'.get_option('revlifter_uuid').".js", null,null,true);
		wp_localize_script( $this->plugin_name, 'customAjax', array(
			'ajaxUrl' => admin_url('admin-ajax.php')
		));
		if(get_option('revlifter_uuid')){
			wp_enqueue_script( 'revlifter_script');
		}
	  	
	  	// after that set this filter
		
	  	add_filter( 'script_loader_tag', function ( $tag, $handle ) {
			if ( 'revlifter_script' !== $handle ) {
				return $tag;
			}
			return str_replace( ' id', ' async id', $tag ); // async the script
		}, 10, 2 );
	}

	public function get_brand_name($product){
		$brand='';
		$terms = get_the_terms($product->get_id(), 'product_brand' );
		if ($terms && !is_wp_error($terms)) {
			foreach ($terms as $term) {
				if ($term->parent == 0) {
					$brand = $term->name;
					// Check if there is a child brand
					$child_terms = get_terms(array(
						'taxonomy' => 'product_brand',
						'parent' => $term->term_id,
					));
		
					if (!empty($child_terms) && !is_wp_error($child_terms)) {
						// Get the first child brand
						$brand = $child_terms[0]->name;
					}
					break;
				}else{
					$brand = $term->name;
					if($brand){
						break;
					}
						
				}
			}
		}

		if (empty($brand)) {
			$brand_list = array();
			foreach ($terms as $term) {
				if ($term->parent != 0) {
					$brand_list[] = $term->name;
				}
			}
			if (!empty($brand_list)) {
				$brand = $brand_list[0];
			}
			
		}
		
		return $brand;
	}

	public function revlifter_product_details() {
	  global $product;

	  // Check if $product is an instance of WC_Product
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		//$product_arr=array();
		
		$product_arr = [
			'schema'=> "1.1",
			'type'	=>	"prod",
			'id' => (string) $product->get_id(),
			'sku' => $product->get_sku(),
			'name' => $product->get_name(),
			'price' => (float) number_format($product->get_price(), 2, '.', ''),
			'brand' => '',
			'category' => '',
			'categories' => [],
			'detail' => '',
			'imageURL' => '',
			'stock' => [
				'inStock' => false
			],
			'promotion' => [
				'isOnPromotion' => false
			],
		];

		$product_arr['brand']=$this->get_brand_name($product);
		$product_arr['currency']=get_woocommerce_currency();
		

		// Get categories
		$categories = wp_get_post_terms($product->get_id(), 'product_cat');
		if (!empty($categories)) {
			$product_arr['category'] = $categories[0]->name;
			$product_arr['categories'] = array_map(function ($category) {
				return $category->name;
			}, $categories);
		}

		// Get product details
		$product_details = $product->get_data();
		$product_arr['detail'] = wp_strip_all_tags( $product_details['description'] );

		// Get image URL
		$image_url = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'single-post-thumbnail');
		$product_arr['imageURL'] = !empty($image_url) ? $image_url[0] : '';
		$product_arr['stock']=$this->getstockdetails($product);
		// Get promotion details
		$product_arr['promotion']=$this->get_product_promotion_details($product);
		// Encode $product_arr as JSON
	?>
		<script>
			_rl_q.push(<?php echo wp_json_encode($product_arr); ?>);
		</script>
	  
	<?php	
	}


	public function revlifter_sale() {
		$order_id = get_query_var('order-received'); // Get the ID of the current order page
   		$order = wc_get_order($order_id);
   		
		$products = [];
		$products["schema"]="1.1";
		$products["type"]="sale";
   		
   		foreach ($order->get_items() as $item_id => $item) {
			$product_arr = [];
			$product = wc_get_product($item->get_product_id());
		    
			// Use the retrieved product information for enhanced ecommerce tracking
		    $product_arr['id']=(string) $item->get_product_id();
	      	$product_arr['sku']=$product->get_sku();
	      	$product_arr['name']=$item->get_name();
	      	$product_arr['price']= (float) number_format($product->get_price(),2);
	      	$product_arr['quantity']=(int) $item->get_quantity();
	      	$product_details = $product->get_data();
			$product_arr['detail'] = wp_strip_all_tags( $product_details['description'] );
			$product_arr['brand']=$this->get_brand_name($product);		

			$categories = [];
			$terms = get_the_terms($product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $categories[] = $term->name;
                }
            }
            $product_arr['category'] = !empty($categories) ? $categories[0] : '';
            $product_arr['categories'] = $categories;
			
			$image_url=wp_get_attachment_image_src( get_post_thumbnail_id($product->get_id()), 'single-post-thumbnail' );
	      	$product_arr["imageURL"]=$image_url[0];
			$product_arr['stock']=$this->getstockdetails($product);
	      
			$product_arr['promotion']=$this->get_product_promotion_details($product);
	      
	      	$products['items'][]=$product_arr;


		}

		global  $woocommerce;
	    $products['currency']=get_woocommerce_currency();
	    $products['orderID']=(string) $order_id;
		
		$tax_rate=0;
		$taxes = $order->get_taxes();
		// Loop through the taxes to find the applied tax rate
		if(!empty($taxes)){
			foreach ($taxes as $tax) {
				// Get the tax rate for each tax item
				$tax_rate = $tax_rate+$tax['rate_percent'];
			}
		}
		
		$products['taxRate'] = (float) $tax_rate;
		$products['shipping'] = (float) $order->get_shipping_total();
	    $products['basePrice'] = (float) $order->get_subtotal();
	    $products['voucherDiscount'] = (float) $order->get_discount_total();
	    $products['cartTotal'] = $products['basePrice'] + $products['shipping'] - $products['voucherDiscount'];
	    $coupon_codes = array();
		foreach ( $order->get_coupons() as $coupon ) {
			$coupon_codes[] = $coupon->get_code();
		}

		$voucher_codes = implode( ',', $coupon_codes );
		$products['voucherCode']=$voucher_codes;
		//$products['voucherCode'] = implode(',', $order->get_coupons());
		$products['priceWithTax'] = (float) number_format(($products['cartTotal'] + $order->get_total_tax()), 2, '.', '');
	    
		$shipping_method = '';
		$shipping_methods = $order->get_shipping_methods();
		if (!empty($shipping_methods)) {
			$shipping_method_instance = reset($shipping_methods);
			$shipping_method = $shipping_method_instance->get_name();
		}
		$products['shippingMethod'] = $shipping_method;
	    
		?>
		<script>
			_rl_q.push(<?php echo wp_json_encode($products); ?>);
		</script>
	<?php        

	}
	


	function getstockdetails($product){
		// Get stock status and quantity
		$stock_status = $product->get_stock_status();
		$stock_arr=[];
		$hide_stock=0;
		$hide_stock=(int) get_option('revlifter_hide_stock');
		$low_stock=(int) $product->get_low_stock_amount();
		$stock_quantity=(int) $product->get_stock_quantity();
		$stock_arr = [
			'inStock' => false
		];
		
		switch($stock_status){
			case 'instock':
				$stock_status='In Stock';
				break;
			case 'outofstock':
				$stock_status='Out of Stock';
				break;
			case 'onbackorder':
				$stock_status='On Backorder';
				break;
			default:
				$stock_status=$stock_status;
		}

		if($hide_stock){
			if($stock_quantity <= $low_stock){
				$stock_status='Low Stock';
			}else{
				$stock_status='In Stock';
			}

			$stock_arr = [
				'inStock' => true,
				'stockLevel' => $stock_status
			];	
			
		}else{
			if($stock_status){
				if($stock_quantity>0){
					$stock_arr = [
						'inStock' => true,
						'stockLevel' => $stock_status,
						'stockAmount' => (int) $product->get_stock_quantity(),
					];
				}else{
					$stock_arr = [
						'inStock' => true,
						'stockLevel' => $stock_status
					];
	
				}	
			}
		}

		

		return $stock_arr;
	}


}
