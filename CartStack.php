<?php
/*
Plugin Name: CartStack for WooCommerce
Plugin URI: 
Description: Brings the power of CartStack to WooCommerce
Version: 1.1.4
Author: CartStack
Author URI: http://cartstack.com
 License: GNU General Public License v3.0
 License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
class WooCartStack {

	/*--------------------------------------------*
	 * Constants
	 *--------------------------------------------*/
	const name = 'CartStack';
	const slug = 'cartstack';
	var $cartscript;
        var $thankyouscript; 
        var $capturescript; 
        var $settings; 
        var $siteid; 
        var $apikey;
        var $captureonly; 
        var $cartitems; 
        var $serverside; 
        var $endpoint; 
	/**
	 * Constructor
	 */
	function __construct() {
		//register an activation hook for the plugin
		register_activation_hook( __FILE__, array( $this, 'install_cartstack' ) );
add_action( 'admin_notices', array($this,'admin_notice'));
		//Hook up to the init action
		add_action( 'init', array( $this, 'init_cartstack' ) );
	}
  
	/**
	 * Runs when the plugin is activated
	 */  
	function install_cartstack() {
		// do not generate any output here
              
	}
  
	/**
	 * Runs when the plugin is initialized
	 */
	function init_cartstack() {
		// Setup localization
		load_plugin_textdomain( self::slug, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		// Load JavaScript and stylesheets
		$this->register_scripts_and_styles();
                $this->settings = get_option('cartstack_settings');
                $this->siteid = isset($this->settings['cartstack_siteid'])?$this->settings['cartstack_siteid']:'';
             $this->apikey = isset($this->settings['cartstack_apikey'])?$this->settings['cartstack_apikey']:'';
             $this->captureonly = isset($this->settings['cartstack_captureonly'])?$this->settings['cartstack_captureonly']:false;
             $this->cartitems = isset($this->settings['cartstack_cartitems'])?$this->settings['cartstack_cartitems']:false;
             $this->serverside = isset($this->settings['cartstack_server_side'])?$this->settings['cartstack_server_side']:false;
		$this->cartscript = $this->siteid!=''?"<script src=\"https://api.cartstack.com/js/cs.js\" type=\"text/javascript\"></script>"
                        . "<script type = \"text/javascript\">\r\n"
                        . "var _cartstack = _cartstack || []; \r\n"
                        . "_cartstack.push(['setSiteID','" . $this->siteid . "']);\r\n"
                        . " _cartstack.push(['setAPI','tracking']); \r\n"
                        . "_cartstack.push(['setCartTotal','<carttotal>']);"  :'';
                
                $this->thankyouscript = $this->siteid!=''?"<script type=\"text/javascript\">\r\n"
                        . "var _cartstack = _cartstack || []; _cartstack.push(['setSiteID', '". $this->siteid . "']);\r\n"
                        . " _cartstack.push(['setAPI','confirmation']);</script>\r\n"
                        . "<script src=\"https://api.cartstack.com/js/cartstack.js\" type=\"text/javascript\"></script>":'';
                
                $this->capturescript = $this->siteid!=''?"<script src=\"https://api.cartstack.com/js/cs.js\" type=\"text/javascript\"></script>\r\n"
                        . "<script type=\"text/javascript\">\r\n"
                        . "var _cartstack = _cartstack || []; \r\n"
                        . "_cartstack.push(['setSiteID', '" . $this->siteid . "']); \r\n"
                        . "_cartstack.push(['setAPI', 'capture']);\r\n"
                        . "</script>":'';
                $this->endpoint = $this->siteid!='' && $this->apikey!=''?'https://api.cartstack.com/ss/v1/?key='.$this->apikey.'&siteid='.$this->siteid.'&email=[EMAIL]&total=[TOTAL]':'';
                
		if ( is_admin() ) {
			//this will run when in the WordPress admin
		} else {
			//this will run when on the frontend
		}

		/*
		 * TODO: Define custom functionality for your plugin here
		 *
		 * For more information: 
		 * http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		add_action( 'admin_menu', array( $this, 'cartstack_add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'cartstack_settings_init' ) );
		add_action( 'woocommerce_after_cart', array( $this, 'before_cart' ) );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'before_cart' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'before_thankyou' ) );
		add_action( 'wp_head', array( $this, 'capture' ) );
                $plugin = plugin_basename(__FILE__); 

		add_filter( "plugin_action_links_$plugin", array( $this, 'settings_link' ) );
                add_action( 'woocommerce_thankyou', array($this,'order_complete'));
                
	
	}
       function admin_notice(){
           if($this->siteid=='')
            echo "<div class='updated'><p><a href='".admin_url('admin.php?page=cartstack')."'>Configure CartStack Settings</a></p></div>";
       }
        function settings_link($links){
            $settings_link = '<a href="'.admin_url('admin.php?page=cartstack').'">CartStack Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
        }
        function order_complete($order_id){
            $ordertotal = get_post_meta($order_id, '_order_total', true);
            $ordertax = get_post_meta($order_id, '_order_tax', true);
            $ordershipping = get_post_meta($order_id, '_order_shipping', true);
            $email = get_post_meta($order_id, '_billing_email', true);
            wp_remote_get($this->getEndpoint($email, $ordertotal - $ordertax - $ordershipping));
        }
        
        function getEndpoint($email, $total){
          return str_replace('[TOTAL]',$total,  str_replace('[EMAIL]',$email, $this->endpoint));
        }
        
        function capture(){
            if($this->captureonly){
                echo $this->capturescript;
            }
        }
        
        function getCartScript($cartTotal){
            
            return str_replace('<carttotal>', $cartTotal, $this->cartscript);
        }
function before_cart(){
//    if(class_exists('WC')){
    $cart = WC()->cart;
if($cart->subtotal == ''){
    return;
}
    $script =  $this->getCartScript($cart->get_cart_total());
    
    if($this->cartitems){
     $items = $cart->get_cart();
    foreach($items as $item){
        $image = wp_get_attachment_image_src( get_post_thumbnail_id($item['data']->id), 'shop_thumbnail' );
        //$prodDesc = str_replace("'","'",$item['data']->post->post_excerpt!=''?$item['data']->post->post_excerpt:$item['data']->post->post_content);
        $prodDesc = " ";
        $prodId = $item['variation_id']!=0?$item['variation_id']:$item['product_id'];
        $item_js="\r\n _cartstack.push(['setCartItem', {\r\n";
        $item_js.= " 'quantity':". $item['quantity']. ",\r\n";
        $item_js.= " 'productID':" . $prodId.",\r\n";
        $item_js.= "'productName':'".str_replace("'","\'",$item['data']->post->post_title)."',\r\n";
        // $item_js.= "'productDescription':'".$prodDesc. "',\r\n";
        $item_js.= " 'productURL':'". get_permalink($item['data']->id)."', \r\n";
        $item_js.= "'productImageURL':'". $image[0]."',\r\n";
        $item_js.= "'productPrice':'". $item['line_subtotal']. "'\r\n";
        $item_js.= "}]);\r\n";
        $script .= $item_js;
    }
    }
        $script .="</script>\r\n";
    
    
    echo $script;
//    }
}

function before_thankyou(){
    echo $this->thankyouscript;
}


function cartstack_add_admin_menu(  ) { 

	add_submenu_page('woocommerce', 'CartStack', 'CartStack', 'manage_options', 'cartstack', array($this,'cartstack_options_page' ));

}


function cartstack_settings_init(  ) { 

	register_setting( 'cartstack_pluginPage', 'cartstack_settings' );

	add_settings_section(
		'cartstack_cartstack_pluginPage_section', 
		__( 'Cartstack Configuration Settings', 'cartstack' ), 
		array($this,'cartstack_settings_section_callback'), 
		'cartstack_pluginPage'
	);

	add_settings_field( 
		'cartstack_siteid', 
		__( 'Site Id', 'cartstack' ).' <a href="#" class="hasTooltip"><img class="help_tip" src="' . plugins_url('/img/help.png',__FILE__).'" height="16" width="16"></a>
<div class="tooltiptext">This is your unique site ID.  You can get this value on the "Code" page of the admin dashboard (<a href="http://admin.cartstack.com/setup/" target="_BLANK">http://admin.cartstack.com/setup/</a>).  Just copy the setSiteID value (see screenshot - <a target="_BLANK" href="http://d.pr/i/14nay">http://d.pr/i/14nay</a>).</div>', 
		array($this,'cartstack_siteid_render'), 
		'cartstack_pluginPage', 
		'cartstack_cartstack_pluginPage_section' 
	);

	add_settings_field( 
		'cartstack_captureonly', 
		__( 'Include Capture Only Code', 'cartstack' ).' <a href="#" class="hasTooltip"><img class="help_tip" src="' . plugins_url('/img/help.png',__FILE__).'" height="16" width="16"></a>
<div class="tooltiptext"> This will include the capture only snippet on every page of your site so CartStack can capture user\'s email addresses outside of the checkout process (eg: newsletter signup, contact form, etc).  This makes your integration more powerful.  We recommend enabling this feature.</div>', 
		array($this,'cartstack_captureonly_render'), 
		'cartstack_pluginPage', 
		'cartstack_cartstack_pluginPage_section' 
	);

	add_settings_field( 
		'cartstack_cartitems', 
		__( 'Include Cart Items', 'cartstack' ). ' <a href="#" class="hasTooltip"><img class="help_tip" src="' . plugins_url('/img/help.png',__FILE__).'" height="16" width="16"></a>
<div class="tooltiptext">This will allow you to include the user\'s shopping cart items in the email they receive.     After enabling this, simply insert this variable - %%cartitems%% - into your email template.  This will dynamically display the user\'s cart items.  You can manage your emails here -    <a target="_BLANK" href="http://admin.cartstack.com/campaigns/cart-recovery/">http://admin.cartstack.com/campaigns/cart-recovery/</a>.</div>', 
		array($this,'cartstack_cartitems_render'), 
		'cartstack_pluginPage', 
		'cartstack_cartstack_pluginPage_section' 
	);
        

	add_settings_field( 
		'cartstack_server_side', 
		__( 'Server Side Confirmation' .' <a href="#" class="hasTooltip"><img class="help_tip" src="' . plugins_url('/img/help.png',__FILE__).'" height="16" width="16"></a>'
                        . '<div class="tooltiptext">This will enable server side order tracking.  This makes the confirmation tracking more accurate. </div>', 'cartstack' ), 
		array($this,'cartstack_server_side_render'), 
		'cartstack_pluginPage', 
		'cartstack_cartstack_pluginPage_section' 
	);
        
        add_settings_field( 
		'cartstack_apikey', 
		__( 'API Key', 'cartstack' ).' <a href="#" class="hasTooltip"><img class="help_tip" src="' . plugins_url('/img/help.png',__FILE__).'" height="16" width="16"></a>'
                . '<div class="tooltiptext">API key (you can just replace the "where do I find this" link with ? icon) -  After enabling you will need to enter your API key, which you can find on the "Code" page in the admin dashboard (<a href="http://admin.cartstack.com/setup/" target="_BLANK">http://admin.cartstack.com/setup/</a>).  Scroll down and click on the "Server Side Confirmation Code" link, generate your API, then copy the value (see screenshot - <a href="http://d.pr/i/1lAWd" target="_BLANK">http://d.pr/i/1lAWd</a>).</div>', 
		array($this,'cartstack_apikey_render'), 
		'cartstack_pluginPage', 
		'cartstack_cartstack_pluginPage_section' 
	);


}


function cartstack_siteid_render(  ) { 

	
	?>
	<input type='text' name='cartstack_settings[cartstack_siteid]' value='<?php echo $this->siteid ?>'>
	<?php

}
function cartstack_apikey_render(  ) { 

	?>
        <script>
            (function($) {
	
	$(document).ready(function(){
            checkServerSide();
            $('#cartstack_server_side').change(function(e){
               checkServerSide();
            })
        })
        
        function checkServerSide(){
             if($('#cartstack_server_side').is(":checked")) {
                    $('#cartstack_apikey').parent().parent().show()
                }else{
                    $('#cartstack_apikey').parent().parent().hide()
                }
        }
	
})( jQuery );
            </script>
	<input type='text' id="cartstack_apikey" name='cartstack_settings[cartstack_apikey]' value='<?php echo $this->apikey; ?>'>
       
            <?php

}


function cartstack_captureonly_render(  ) { 

	?>
	<input type='checkbox' name='cartstack_settings[cartstack_captureonly]' <?php checked( $this->captureonly, 1 ); ?> value='1'>
	<?php

}


function cartstack_cartitems_render(  ) { 

	?>
	<input type='checkbox' name='cartstack_settings[cartstack_cartitems]' <?php checked( $this->cartitems, 1 ); ?> value='1'>
        
            <?php
        
}


function cartstack_server_side_render(  ) { 

	?>
	<input type='checkbox' id="cartstack_server_side" name='cartstack_settings[cartstack_server_side]' <?php checked( $this->serverside, 1 ); ?> value='1'>
	<?php

}


function cartstack_settings_section_callback(  ) { 

//	echo __( 'This section description', 'cartstack' );

}


function cartstack_options_page(  ) { 

	?>
	<form action='options.php' method='post'>
		
		<h2>CartStack</h2>
		
		<?php
		settings_fields( 'cartstack_pluginPage' );
		do_settings_sections( 'cartstack_pluginPage' );
		submit_button();
		?>
                <p>Need help?  Check out our <a href="https://help.cartstack.com/article/84-woocommerce" target="_BLANK">help doc</a> OR email us at <a href="mailto:support@cartstack.com">support@cartstack.com</a>.
</p>
	</form>
	<?php

}


  
	/**
	 * Registers and enqueues stylesheets for the administration panel and the
	 * public facing site.
	 */
	private function register_scripts_and_styles() {
            
                    
                     $this->load_file( self::slug . '-qtip-script', '/js/jquery.qtip.min.js', true );
                     $this->load_file( self::slug . '-script', '/js/admin.js', true );
                     $this->load_file( self::slug . '-qtipstyle', '/css/jquery.qtip.min.css', false );
                     $this->load_file( self::slug . '-style', '/css/admin.css', false );
                
             

	} // end register_scripts_and_styles
	private function URL_exists($url){
   $headers=get_headers($url);
   return stripos($headers[0],"200 OK")?true:false;
}

	/**
	 * Helper function for registering and enqueueing scripts and styles.
	 *
	 * @name	The 	ID to register with WordPress
	 * @file_path		The path to the actual file
	 * @is_script		Optional argument for if the incoming file_path is a JavaScript source file.
	 */
	private function load_file( $name, $file_path, $is_script = false ) {
            
            if(isset($_GET['page'])&& $_GET['page']=='cartstack'){
            if(strpos($file_path,'http://')===false){
		$url = plugins_url($file_path, __FILE__);
            
		$file = plugin_dir_path(__FILE__) . $file_path;
            }else{
                $file = '';
                $url = $file_path;
            }
		if(($file !='' && file_exists( $file ))||$this->URL_exists($url) ) {
			if( $is_script ) {
				
				wp_enqueue_script($name, $url, array('jquery'), false, true ); //depends on jquery
			} else {
				
				wp_enqueue_style( $name, $url );
			} // end if
		} // end if
            }
	} // end load_file
  
} // end class
new WooCartStack();