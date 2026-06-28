<?php
/**
 * Draft Saver — creates WordPress draft posts.
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

namespace TechZappAIWriter;

/**
 * Class Draft_Saver
 */
class Draft_Saver {

	/**
	 * Save an article as a WordPress draft.
	 *
	 * @param array<string, mixed> $data Post data.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function save( array $data ): int|\WP_Error {
		$title          = sanitize_text_field( $data['title'] ?? '' );
		$content        = wp_kses_post( $data['content'] ?? '' );
		$excerpt        = sanitize_text_field( $data['meta_description'] ?? '' );
		$category_ids   = array_map( 'intval', (array) ( $data['categories'] ?? [] ) );
		$tags           = array_map( 'sanitize_text_field', (array) ( $data['tags'] ?? [] ) );

		if ( empty( $title ) ) {
			return new \WP_Error(
				'missing_title',
				__( 'Post title is required.', 'techzapp-ai-writer' )
			);
		}

		if ( empty( $content ) ) {
			return new \WP_Error(
				'missing_content',
				__( 'Post content is required.', 'techzapp-ai-writer' )
			);
		}

		$post_data = [
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		];

		// Add categories.
		if ( ! empty( $category_ids ) ) {
			$post_data['post_category'] = $category_ids;
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Add tags.
		if ( ! empty( $tags ) ) {
			wp_set_post_tags( $post_id, $tags, false );
		}

		// Save meta description as post meta (useful for SEO plugins).
		if ( ! empty( $excerpt ) ) {
			update_post_meta( $post_id, '_tzaw_meta_description', sanitize_text_field( $excerpt ) );
		}

		// Save focus keyword if provided.
		if ( ! empty( $data['focus_keyword'] ) ) {
			update_post_meta( $post_id, '_tzaw_focus_keyword', sanitize_text_field( $data['focus_keyword'] ) );
		}

		// Mark post as AI-generated.
		update_post_meta( $post_id, '_tzaw_ai_generated', '1' );
		update_post_meta( $post_id, '_tzaw_generated_at', current_time( 'mysql' ) );

		return $post_id;
	}

	/**
	 * Get recently generated drafts.
	 *
	 * @param int $limit Number of posts to retrieve.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_drafts( int $limit = 5 ): array {
		$posts = get_posts(
			[
				'post_status'    => [ 'draft', 'publish' ],
				'post_type'      => 'post',
				'posts_per_page' => $limit,
				'meta_key'       => '_tzaw_ai_generated', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => '1',                  // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		return array_map(
			static fn( \WP_Post $post ) => [
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'status'     => $post->post_status,
				'date'       => $post->post_date,
				'edit_url'   => get_edit_post_link( $post->ID, 'raw' ),
				'view_url'   => get_permalink( $post->ID ),
			],
			$posts
		);
	}

	/**
	 * Get total count of AI-generated posts.
	 *
	 * @return int
	 */
	public function get_total_count(): int {
		global $wpdb;

		$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND pm.meta_value = %s
				AND p.post_type = 'post'
				AND p.post_status != 'trash'",
				'_tzaw_ai_generated',
				'1'
			)
		);

		return (int) $count;
	}
}
