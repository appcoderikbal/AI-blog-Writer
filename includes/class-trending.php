<?php
/**
 * Trending Topics Aggregator.
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

namespace TechZappAIWriter;

/**
 * Class Trending
 *
 * Fetches trending technology topics from multiple public APIs.
 * Results are cached in a transient for 12 hours to avoid rate limiting.
 */
class Trending {

	/**
	 * Transient key for caching.
	 */
	private const TRANSIENT_KEY = 'tzaw_trending_topics';

	/**
	 * Cache duration in seconds (12 hours).
	 */
	private const CACHE_DURATION = 43200;

	/**
	 * Get trending topics from all sources.
	 *
	 * @param bool $force_refresh Force-refresh the cache.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_topics( bool $force_refresh = false ): array {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$topics = array_merge(
			$this->fetch_hacker_news(),
			$this->fetch_github_trending(),
			$this->fetch_reddit_technology(),
			$this->fetch_devto()
		);

		// Sort by score descending.
		usort( $topics, static fn( $a, $b ) => $b['score'] <=> $a['score'] );

		// Limit to 30 total topics.
		$topics = array_slice( $topics, 0, 30 );

		set_transient( self::TRANSIENT_KEY, $topics, self::CACHE_DURATION );

		return $topics;
	}

	/**
	 * Fetch top stories from Hacker News.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_hacker_news(): array {
		$response = wp_remote_get(
			'https://hacker-news.firebaseio.com/v0/topstories.json',
			[ 'timeout' => 10 ]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$ids  = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $ids ) ) {
			return [];
		}

		$ids    = array_slice( $ids, 0, 10 );
		$topics = [];

		foreach ( $ids as $id ) {
			$item_response = wp_remote_get(
				"https://hacker-news.firebaseio.com/v0/item/{$id}.json",
				[ 'timeout' => 8 ]
			);

			if ( is_wp_error( $item_response ) ) {
				continue;
			}

			$item = json_decode( wp_remote_retrieve_body( $item_response ), true );

			if ( ! is_array( $item ) || empty( $item['title'] ) ) {
				continue;
			}

			// Filter to tech-relevant stories.
			$title = sanitize_text_field( $item['title'] );
			if ( ! $this->is_tech_relevant( $title ) ) {
				continue;
			}

			$topics[] = [
				'id'       => 'hn-' . $id,
				'title'    => $title,
				'category' => 'Technology',
				'source'   => 'Hacker News',
				'score'    => min( (int) ( $item['score'] ?? 0 ), 999 ),
				'url'      => esc_url( $item['url'] ?? "https://news.ycombinator.com/item?id={$id}" ),
			];
		}

		return $topics;
	}

	/**
	 * Fetch trending repositories from GitHub.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_github_trending(): array {
		$date     = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$url      = "https://api.github.com/search/repositories?q=created:>{$date}&sort=stars&order=desc&per_page=10";

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 10,
				'headers' => [
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'TechZapp-AI-Writer/' . TZAW_VERSION,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return [];
		}

		$topics = [];
		foreach ( $data['items'] as $repo ) {
			$name        = sanitize_text_field( $repo['full_name'] ?? '' );
			$description = sanitize_text_field( $repo['description'] ?? '' );
			$title       = $description ?: $name;

			if ( empty( $title ) ) {
				continue;
			}

			$topics[] = [
				'id'       => 'gh-' . sanitize_key( $name ),
				'title'    => $title,
				'category' => 'Open Source / ' . sanitize_text_field( $repo['language'] ?? 'Development' ),
				'source'   => 'GitHub Trending',
				'score'    => min( (int) ( $repo['stargazers_count'] ?? 0 ), 999 ),
				'url'      => esc_url( $repo['html_url'] ?? '' ),
			];
		}

		return $topics;
	}

	/**
	 * Fetch top posts from Reddit Technology.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_reddit_technology(): array {
		$response = wp_remote_get(
			'https://www.reddit.com/r/technology/top.json?limit=10&t=day',
			[
				'timeout' => 10,
				'headers' => [
					'User-Agent' => 'TechZapp-AI-Writer/' . TZAW_VERSION,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $data['data']['children'] ) ) {
			return [];
		}

		$topics = [];
		foreach ( $data['data']['children'] as $child ) {
			$post  = $child['data'] ?? [];
			$title = sanitize_text_field( $post['title'] ?? '' );

			if ( empty( $title ) ) {
				continue;
			}

			$topics[] = [
				'id'       => 'reddit-' . sanitize_key( $post['id'] ?? uniqid() ),
				'title'    => $title,
				'category' => 'Technology News',
				'source'   => 'Reddit Technology',
				'score'    => min( (int) ( $post['score'] ?? 0 ), 999 ),
				'url'      => esc_url( 'https://reddit.com' . ( $post['permalink'] ?? '' ) ),
			];
		}

		return $topics;
	}

	/**
	 * Fetch trending articles from Dev.to.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_devto(): array {
		$response = wp_remote_get(
			'https://dev.to/api/articles?top=1&per_page=10',
			[
				'timeout' => 10,
				'headers' => [
					'User-Agent' => 'TechZapp-AI-Writer/' . TZAW_VERSION,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$articles = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $articles ) ) {
			return [];
		}

		$topics = [];
		foreach ( $articles as $article ) {
			$title = sanitize_text_field( $article['title'] ?? '' );

			if ( empty( $title ) ) {
				continue;
			}

			$tag      = sanitize_text_field( $article['tag_list'][0] ?? 'Development' );
			$category = ucwords( str_replace( '-', ' ', $tag ) );

			$topics[] = [
				'id'       => 'devto-' . ( $article['id'] ?? uniqid() ),
				'title'    => $title,
				'category' => $category,
				'source'   => 'Dev.to',
				'score'    => min( (int) ( $article['positive_reactions_count'] ?? 0 ), 999 ),
				'url'      => esc_url( $article['url'] ?? '' ),
			];
		}

		return $topics;
	}

	/**
	 * Check if a title is likely tech-relevant.
	 *
	 * @param string $title Title to check.
	 * @return bool
	 */
	private function is_tech_relevant( string $title ): bool {
		$tech_keywords = [
			'ai', 'ml', 'gpt', 'llm', 'api', 'software', 'code', 'programming', 'developer',
			'javascript', 'python', 'rust', 'go', 'typescript', 'react', 'open source',
			'github', 'cloud', 'aws', 'google', 'microsoft', 'apple', 'startup', 'tech',
			'database', 'security', 'cybersecurity', 'linux', 'kernel', 'framework',
			'model', 'neural', 'machine learning', 'algorithm', 'data', 'web', 'app',
		];

		$lower = strtolower( $title );
		foreach ( $tech_keywords as $keyword ) {
			if ( str_contains( $lower, $keyword ) ) {
				return true;
			}
		}

		return false;
	}
}
