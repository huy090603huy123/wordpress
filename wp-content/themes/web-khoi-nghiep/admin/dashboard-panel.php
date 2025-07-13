<!-- Hide Old Wrap with CSS -->
<style type="text/css">
div#wpcontent div.wrap {

}
div#wpcontent div.my-dashboard {
    display: block;
}
</style>

<!-- New Wrap with custom welcome screen-->
<div class="wrap mjp-dashboard">
    <h2>Dashboard</h2>

    <div id="welcome-panel" class="welcome-panel" style="padding:0px 10px">
        <?php wp_nonce_field( 'welcome-panel-nonce', 'welcomepanelnonce', false ); ?>
        <?php //do_action( 'welcome_panel' ); ?>
      <marquee width="50%"> <p class="text-center chao-mung" style="color:#e74c3c;font-weight: bold;margin:0px">Chào mừng bạn đến với hệ thống quản trị website - Web Khởi Nghiệp</p></marquee>
    </div>

   

</div><!-- wrap -->