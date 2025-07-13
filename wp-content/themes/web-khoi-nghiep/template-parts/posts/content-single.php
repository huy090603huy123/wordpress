<div class="entry-content single-page">

<?php the_content(); ?>

<?php
	wp_link_pages( array(
		'before' => '<div class="page-links">' . __( 'Pages:', 'flatsome' ),
		'after'  => '</div>',
	) );
?>

<?php if(flatsome_option('blog_share')) {
	// SHARE ICONS
	echo '<div class="blog-share text-center">';

	echo do_shortcode('[share]');
	echo '</div>';
} ?>
</div><!-- .entry-content2 -->

<footer class="entry-meta text-<?php echo flatsome_option('blog_posts_title_align');?>">
<?php
	/* translators: used between list items, there is a space after the comma */
	$category_list = get_the_category_list( __( ', ', 'flatsome' ) );

	/* translators: used between list items, there is a space after the comma */
	$tag_list = get_the_tag_list( '', __( ', ', 'flatsome' ) );


	// But this blog has loads of categories so we should probably display them here
	if ( '' != $tag_list ) {
		$meta_text = __( 'This entry was posted in %1$s and tagged %2$s.', 'flatsome' );
	} else {
		$meta_text = __( 'This entry was posted in %1$s. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', 'flatsome' );
	}
	
	printf(
		$meta_text,
		$category_list,
		$tag_list,
		get_permalink(),
		the_title_attribute( 'echo=0' )
	);
?>
</footer><!-- .entry-meta -->


<?php if(flatsome_option('blog_author_box')) { ?>
<div class="entry-author author-box">
	<div class="flex-row align-top">
		<div class="flex-col mr circle">
			<div class="blog-author-image">
				<?php 
					$user = get_the_author_meta('ID');
					echo get_avatar($user,90); 
				?>
			</div>
		</div><!-- .flex-col -->
		<div class="flex-col flex-grow">
			<h5 class="author-name uppercase pt-half">
				<?php echo esc_html( get_the_author_meta( 'display_name' ) );?>
			</h5>
			<p class="author-desc small"><?php echo esc_html(get_the_author_meta('user_description'));?></p>
		</div><!-- .flex-col -->
	</div>
</div>
<?php } ?>



<?php $orig_post = $post;
global $post;
$categories = get_the_category($post->ID);
if ($categories) {
$category_ids = array();
foreach($categories as $individual_category) $category_ids[] = $individual_category->term_id;
$args=array(
'category__in' => $category_ids,
'post__not_in' => array($post->ID),
'posts_per_page'=> 3, // Number of related posts that will be shown.
'caller_get_posts'=>1
);
$my_query = new wp_query( $args );
if( $my_query->have_posts() ) {
?>
<div class="related-post">
<div  class="" style="margin-top: 30px;margin-bottom: 20px"> <h7>Bài viết liên quan</h7><div class="duong-line"></div></div>
<div class="clearfix"></div>
   <div class="row large-columns-3 medium-columns-2 small-columns-1 has-shadow row-box-shadow-1 row-box-shadow-1-hover">
<?php
while( $my_query->have_posts() ) {
$my_query->the_post();?>


 
  		<div class="col post-item">
			<div class="col-inner">
			<a href="<?php the_permalink()?>" class="plain">
				<div class="box box-bounce box-text-bottom box-blog-post has-hover">
            					<div class="box-image">
  						<div class="image-cover" style="padding-top:56%;">
  							<?php the_post_thumbnail(); ?>						  							  						</div>
  						  					</div><!-- .box-image -->
          					<div class="box-text text-center">
					<div class="box-text-inner blog-post-inner">

					
										<h5 class="post-title is-large "><?php the_title(); ?></h5>
					<div class="post-meta is-small op-8"><?php the_time('M j, Y') ?></div>					<div class="is-divider"></div>
										
										
					
					
					</div><!-- .box-text-inner -->
					</div><!-- .box-text -->
									</div><!-- .box -->
				</a><!-- .link -->
			</div><!-- .col-inner -->
		</div><!-- .col -->
	
	



<?php
}
?>
</div>
 </div>

<?php
}
}
$post = $orig_post;
wp_reset_query(); ?>






