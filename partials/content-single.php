<?php
/**
 * @package ttf-start
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php get_template_part( 'partials/_entry', 'title' ); ?>
		<?php get_template_part( 'partials/_entry', 'date' ); ?>
	</header>

	<div class="entry-content">
		<?php the_content(); ?>
		<?php get_template_part( 'partials/_entry', 'pagination' ); ?>
	</div>

	<footer class="entry-footer">
		<?php get_template_part( 'partials/_entry', 'taxonomy' ); ?>
	</footer>
</article>
