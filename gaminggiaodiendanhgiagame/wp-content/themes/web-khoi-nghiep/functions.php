<?php
// Add custom Theme Functions here
//Copy từng phần và bỏ vào file functions.php của theme:


//Tùy chỉnh admin footer
function custom_admin_footer() { 
 echo 'Thiết kế bởi <a href="http://webkhoinghiep.net" target="blank">Web Khởi Nghiệp</a>';}
 add_filter('admin_footer_text', 'custom_admin_footer');


//Ẩn các panel không cần thiết
 add_action('wp_dashboard_setup', 'my_custom_dashboard_widgets');

function my_custom_dashboard_widgets()
{
     global $wp_meta_boxes;

     // Right Now - Comments, Posts, Pages at a glance
     unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);

     // Recent Comments
     unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments']);

     // Incoming Links
     unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links']);

     // Plugins - Popular, New and Recently updated WordPress Plugins
     unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins']);

     // WordPress Development Blog Feed
     unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);

     // Other WordPress News Feed
     unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary']);

     // Quick Press Form
     unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);

     // Recent Drafts List
     unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_recent_drafts']);
}



//Ẩn Welcome Panel:
add_action( 'load-index.php', 'hide_welcome_panel' );

function hide_welcome_panel() {
    $user_id = get_current_user_id();

    if ( 1 == get_user_meta( $user_id, 'show_welcome_panel', true ) )
        update_user_meta( $user_id, 'show_welcome_panel', 0 );
}


//Thêm Panel welcome mới:
add_action( 'admin_footer', 'rv_custom_dashboard_widget' );
function rv_custom_dashboard_widget() {
    // Bail if not viewing the main dashboard page
    if ( get_current_screen()->base !== 'dashboard' ) {
        return;
    }
    ?>

    <div id="custom-id" class="welcome-panel" style="display: none;border: 2px solid #52accc;">
        <div class="welcome-panel-content" >
        <iframe src="http://webkhoinghiep.net/huong-dan-su-dung-nhung/" width="100%" height="480px" frameborder="0"></iframe>
        </div>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('#welcome-panel').after($('#custom-id').show());
        });
    </script>

<?php }




//Xóa logo wordpress
add_action( 'admin_bar_menu', 'remove_wp_logo', 999 );

function remove_wp_logo( $wp_admin_bar ) {
    $wp_admin_bar->remove_node( 'wp-logo' );
}



//Ẩn cập nhật woo

//Remove WooCommerce's annoying update message
remove_action( 'admin_notices', 'woothemes_updater_notice' );

// REMOVE THE WORDPRESS UPDATE NOTIFICATION FOR ALL USERS EXCEPT ADMIN
   global $user_login;
   get_currentuserinfo();
   if (!current_user_can('update_plugins'))
   {
        // checks to see if current user can update plugins
           add_action( 'init', create_function( '$a', "remove_action( 'init', 'wp_version_check' );" ), 2 );
           add_filter( 'pre_option_update_core', create_function( '$a', "return null;" ) );
   }

//xoa mã bưu điện thanh toán
add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );
function custom_override_checkout_fields( $fields ) {
     unset($fields['billing']['billing_postcode']);
     unset($fields['billing']['billing_country']);
     unset($fields['billing']['billing_address_2']);
     unset($fields['billing']['billing_company']);
     
    
     return $fields;
}
// Custom Dashboard
function my_custom_dashboard() {
    $screen = get_current_screen();
    if( $screen->base == 'dashboard' ) {
        include 'admin/dashboard-panel.php';
    }
}
add_action('admin_notices', 'my_custom_dashboard');







function register_my_menu() {
  register_nav_menu('product-menu',__( 'Menu Danh mục' ));
}
add_action( 'init', 'register_my_menu' );



//Doan code thay chữ giảm giá bằng % sale

//* Add stock status to archive pages
add_filter( 'woocommerce_get_availability', 'custom_override_get_availability', 1, 2);
 
// The hook in function $availability is passed via the filter!
function custom_override_get_availability( $availability, $_product ) {
if ( $_product->is_in_stock() ) $availability['availability'] = __('Còn hàng', 'woocommerce');
return $availability;
}

// Thay doi duong dan logo admin
function wpc_url_login(){
return "http://webkhoinghiep.net/"; // duong dan vao website cua ban
}
add_filter('login_headerurl', 'wpc_url_login');
// Thay doi logo admin wordpress
function login_css() {
wp_enqueue_style( 'login_css', get_stylesheet_directory_uri() . '/login.css' ); // duong dan den file css moi
}
add_action('login_head', 'login_css');
// Enqueue Scripts and Styles.
add_action( 'wp_enqueue_scripts', 'flatsome_enqueue_scripts_styles' );
function flatsome_enqueue_scripts_styles() {
wp_enqueue_style( 'dashicons' );
wp_enqueue_style( 'flatsome-ionicons', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' );
}


// Dịch woocommerce


function ra_change_translate_text( $translated_text ) {
if ( $translated_text == 'Old Text' ) {
$translated_text = 'New Translation';
}
return $translated_text;
}
add_filter( 'gettext', 'ra_change_translate_text', 20 );
function ra_change_translate_text_multiple( $translated ) {
$text = array(
'Continue Shopping' => 'Tiếp tục mua hàng',
'Update cart' => 'Cập nhật giỏ hàng',
'Apply Coupon' => 'Áp dụng mã ưu đãi',
'WooCommerce' => 'Quản lý bán hàng',











);
$translated = str_ireplace( array_keys($text), $text, $translated );
return $translated;
}
add_filter( 'gettext', 'ra_change_translate_text_multiple', 20 );
// End dich


function new_excerpt_more( $more ) {
    return '';
}
add_filter('excerpt_more', 'new_excerpt_more');

class Auto_Save_Images{
 
    function __construct(){     
        
        add_filter( 'content_save_pre',array($this,'post_save_images') ); 
    }
    
    function post_save_images( $content ){
        if( ($_POST['save'] || $_POST['publish'] )){
            set_time_limit(240);
            global $post;
            $post_id=$post->ID;
            $preg=preg_match_all('/<img.*?src="(.*?)"/',stripslashes($content),$matches);
            if($preg){
                foreach($matches[1] as $image_url){
                    if(empty($image_url)) continue;
                    $pos=strpos($image_url,$_SERVER['HTTP_HOST']);
                    if($pos===false){
                        $res=$this->save_images($image_url,$post_id);
                        $replace=$res['url'];
                        $content=str_replace($image_url,$replace,$content);
                    }
                }
            }
        }
        remove_filter( 'content_save_pre', array( $this, 'post_save_images' ) );
        return $content;
    }
    
    function save_images($image_url,$post_id){
        $file=file_get_contents($image_url);
        $post = get_post($post_id);
        $posttitle = $post->post_title;
        $postname = sanitize_title($posttitle);
        $im_name = "$postname-$post_id.jpg";
        $res=wp_upload_bits($im_name,'',$file);
        $this->insert_attachment($res['file'],$post_id);
        return $res;
    }
    
    function insert_attachment($file,$id){
        $dirs=wp_upload_dir();
        $filetype=wp_check_filetype($file);
        $attachment=array(
            'guid'=>$dirs['baseurl'].'/'._wp_relative_upload_path($file),
            'post_mime_type'=>$filetype['type'],
            'post_title'=>preg_replace('/\.[^.]+$/','',basename($file)),
            'post_content'=>'',
            'post_status'=>'inherit'
        );
        $attach_id=wp_insert_attachment($attachment,$file,$id);
        $attach_data=wp_generate_attachment_metadata($attach_id,$file);
        wp_update_attachment_metadata($attach_id,$attach_data);
        return $attach_id;
    }
}
new Auto_Save_Images();


/*
* Remove product-category in URL
* Thay danh-muc bằng slug hiện tại của bạn. Themes tại WEb Khởi Nghiệp - Mặc định là danh-muc
*/
add_filter( 'term_link', 'devvn_product_cat_permalink', 10, 3 );
function devvn_product_cat_permalink( $url, $term, $taxonomy ){
    switch ($taxonomy):
        case 'product_cat':
            $taxonomy_slug = 'danh-muc'; //Thay bằng slug hiện tại của bạn. Mặc định Của WKN là danh-muc
            if(strpos($url, $taxonomy_slug) === FALSE) break;
            $url = str_replace('/' . $taxonomy_slug, '', $url);
            break;
    endswitch;
    return $url;
}
// Add our custom product cat rewrite rules
function devvn_product_category_rewrite_rules($flash = false) {
    $terms = get_terms( array(
        'taxonomy' => 'product_cat',
        'post_type' => 'product',
        'hide_empty' => false,
    ));
    if($terms && !is_wp_error($terms)){
        $siteurl = esc_url(home_url('/'));
        foreach ($terms as $term){
            $term_slug = $term->slug;
            $baseterm = str_replace($siteurl,'',get_term_link($term->term_id,'product_cat'));
            add_rewrite_rule($baseterm.'?$','index.php?product_cat='.$term_slug,'top');
            add_rewrite_rule($baseterm.'page/([0-9]{1,})/?$', 'index.php?product_cat='.$term_slug.'&paged=$matches[1]','top');
            add_rewrite_rule($baseterm.'(?:feed/)?(feed|rdf|rss|rss2|atom)/?$', 'index.php?product_cat='.$term_slug.'&feed=$matches[1]','top');
        }
    }
    if ($flash == true)
        flush_rewrite_rules(false);
}
add_action('init', 'devvn_product_category_rewrite_rules');
/*Sửa lỗi khi tạo mới taxomony bị 404*/
add_action( 'create_term', 'devvn_new_product_cat_edit_success', 10, 2 );
function devvn_new_product_cat_edit_success( $term_id, $taxonomy ) {
    devvn_product_category_rewrite_rules(true);
}

/*
* Code Bỏ /san-pham/ hoặc ... có hỗ trợ dạng %product_cat%
*/
function devvn_remove_slug( $post_link, $post ) {
    if ( !in_array( get_post_type($post), array( 'product' ) ) || 'publish' != $post->post_status ) {
        return $post_link;
    }
    if('product' == $post->post_type){
        $post_link = str_replace( '/san-pham/', '/', $post_link ); //Thay cua-hang bằng slug hiện tại của bạn
    }else{
        $post_link = str_replace( '/' . $post->post_type . '/', '/', $post_link );
    }
    return $post_link;
}
add_filter( 'post_type_link', 'devvn_remove_slug', 10, 2 );
/*Sửa lỗi 404 sau khi đã remove slug product hoặc cua-hang*/
function devvn_woo_product_rewrite_rules($flash = false) {
    global $wp_post_types, $wpdb;
    $siteLink = esc_url(home_url('/'));
    foreach ($wp_post_types as $type=>$custom_post) {
        if($type == 'product'){
            if ($custom_post->_builtin == false) {
                $querystr = "SELECT {$wpdb->posts}.post_name, {$wpdb->posts}.ID
                            FROM {$wpdb->posts} 
                            WHERE {$wpdb->posts}.post_status = 'publish' 
                            AND {$wpdb->posts}.post_type = '{$type}'";
                $posts = $wpdb->get_results($querystr, OBJECT);
                foreach ($posts as $post) {
                    $current_slug = get_permalink($post->ID);
                    $base_product = str_replace($siteLink,'',$current_slug);
                    add_rewrite_rule($base_product.'?$', "index.php?{$custom_post->query_var}={$post->post_name}", 'top');
                }
            }
        }
    }
    if ($flash == true)
        flush_rewrite_rules(false);
}
add_action('init', 'devvn_woo_product_rewrite_rules');
/*Fix lỗi khi tạo sản phẩm mới bị 404*/
function devvn_woo_new_product_post_save($post_id){
    global $wp_post_types;
    $post_type = get_post_type($post_id);
    foreach ($wp_post_types as $type=>$custom_post) {
        if ($custom_post->_builtin == false && $type == $post_type) {
            devvn_woo_product_rewrite_rules(true);
        }
    }
}
add_action('wp_insert_post', 'devvn_woo_new_product_post_save');


add_action('woocommerce_shop_loop_item_title','thong_tin_them_sp');

function thong_tin_them_sp(){
    $diem=get_field('diem_danh_gia');

    global $product;

?><div class="row-thong-tin">
    <p class="danh-gia"><i class="fa fa-star" aria-hidden="true"></i> <?php echo $diem;?>/ 10</p>
</div>
            <?php
}

add_action( 'woocommerce_single_product_summary', 'dev_thong_tin_chi_tiet', 8 );
function dev_thong_tin_chi_tiet(){
    $diem=get_field('diem_danh_gia');

    global $product;

?><div class="row-thong-tin">
    <p class="danh-gia">Đánh giá: <i class="fa fa-star" aria-hidden="true"></i> <?php echo $diem;?>/ 10</p>
</div>
            <?php
}




      add_filter( 'woocommerce_product_tabs', 'tab_dac_tinh' );
function tab_dac_tinh( $tabs ) {
    $tab_hinh_anh="Hình ảnh";
    $tab_video="Video";

 

    // Adds the new tab
    
    $tabs['hinh_anh_tab'] = array(
        'title'     => __( $tab_hinh_anh, 'woocommerce' ),
        'priority'  => 20,
        'callback'  => 'woo_new_tab_hinh_anh'
    );
   $tabs['video_tab'] = array(
        'title'     => __( $tab_video, 'woocommerce' ),
        'priority'  => 21,
        'callback'  => 'woo_new_tab_video'
    );
      
 
 
    return $tabs;
 
}
function woo_new_tab_hinh_anh() {
   



$image_ids = get_field('hinh_anh', false, false);
if(!empty($image_ids)){


$shortcode = '[' . 'gallery ids="' . implode(',', $image_ids) . '"]';

echo do_shortcode( $shortcode );
}else{
    echo "Hình ảnh đang cập nhật...";
}

    
}
function woo_new_tab_video() {
        $video = get_field('video');
    // The new tab content

   if(!empty($video)){
            echo $video;   
        }else{
             echo "Video đang cập nhật...";
        }
   
    
}
