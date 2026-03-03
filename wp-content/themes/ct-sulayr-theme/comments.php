<?php
/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ct-sulayr-theme
 */

if ( false !== post_password_required() ):
	return;
endif;
?>

<div id="comments" class="comments-area">
	<?php if ( false !== have_comments() ): ?>
		<h2 class="comments-title"><?php _e( 'Comments in this post', 'ct_sulayr_theme' ) ?></h2>
		<?php //IMPORTANT. README: https://codex.wordpress.org/Function_Reference/wp_list_comments ?>
		<ol class="comment-list">
			<?php $args = [
				'style' => 'ul',
				'reply_text' => __( 'Reply', 'ct_sulayr_theme' ),
				'short_ping' => true,
				'avatar_size' => '32',
			];
			wp_list_comments( $args ); ?>
		</ol>
		<?php //https://developer.wordpress.org/reference/functions/get_the_comments_navigation/
		$args = [
			'prev_text' => __( '<', 'ct_sulayr_theme' ),
			'next_text' => __( '>', 'ct_sulayr_theme' ),
			'screen_reader_text' => __( 'View more comments', 'ct_sulayr_theme' ),
		];
		the_comments_navigation( $args );

		if ( false === comments_open() ): ?>
			<p class="no-comments"><?php __( 'Comments are closed', 'ct_sulayr_theme' ); ?></p>
		<?php endif;
	endif;

	//IMPORTANT. README: https://developer.wordpress.org/reference/functions/comment_form/
	$fields = [
		'author' => '<p class="comment-form-author"><label for="author">' . __( 'Your name', 'ct_sulayr_theme' ) . '</label>' . '<input id="author" name="author" type="text" value="" size="30" /></p>',
		'email' => '<p class="comment-form-email"><label for="email">' . __( 'Your email', 'ct_sulayr_theme' ) . '</label>' . '<input id="email" name="email" type="text" value="" size="30" /></p>',
		'url' => '<p class="comment-form-url"><label for="url">' . __( 'Your website', 'ct_sulayr_theme' ) . '</label><input id="url" name="url" type="text" value="" size="30" /></p>',
		'cookies' => '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" /><label for="wp-comment-cookies-consent">' . __( 'Save my name, email, and website in this browser for the next time I comment.', 'ct_sulayr_theme' ) . '</label></p>'
	];
	$comment_field = '<p class="comment-form-comment"><label for="comment">' . _x( 'Your comment here', 'ct_sulayr_theme' ) . '</label><textarea id="comment" name="comment" cols="45" rows="8" aria-required="true"></textarea></p>';
	$must_log_in = '<p class="must-log-in">' .  sprintf( __( 'You must be <a href="%s">logged in</a> to post a comment.', 'ct_sulayr_theme' ), wp_login_url( apply_filters( 'the_permalink', get_permalink() ) ) ) . '</p>';
	$logged_in_as = '<p class="logged-in-as">' . sprintf( __( 'Logged in as <a href="%1$s">%2$s</a>. <a href="%3$s" title="Log out of this account">Log out?</a>', 'ct_sulayr_theme' ), admin_url( 'profile.php' ), $user_identity, wp_logout_url( apply_filters( 'the_permalink', get_permalink() ) ) ) . '</p>';

	$args = [
		'fields' => $fields,
		'comment_field' => $comment_field,
		'must_log_in' => $must_log_in,
		'logged_in_as' => $logged_in_as,
		'id_form' => 'commentform',
		'class_form' => 'comment-form',
		'id_submit' => 'submit',
		'class_submit' => 'submit',
		'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
		'title_reply' => __( 'Write your comment here', 'ct_sulayr_theme' ),
		'title_reply_after' => '</h3>',
		'cancel_reply_before' => '<small>',
		'cancel_reply_link' => __( 'Cancel reply', 'ct_sulayr_theme' ),
		'cancel_reply_after' => '</small>',
		'label_submit' => __( 'Send your comment', 'ct_sulayr_theme' ),
	];
	comment_form( $args ); ?>
</div>