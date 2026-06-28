<?php
/**
 * OpenAI API client.
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

namespace TechZappAIWriter;

/**
 * Class OpenAI
 *
 * Handles all communication with the OpenAI Responses API.
 */
class OpenAI {

	/**
	 * OpenAI API endpoint.
	 */
	private const API_URL = 'https://api.openai.com/v1/responses';

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Generate a full blog article.
	 *
	 * @param array<string, mixed> $params Generation parameters.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function generate_article( array $params ): array|\WP_Error {
		$title      = sanitize_text_field( $params['title'] ?? '' );
		$keyword    = sanitize_text_field( $params['keyword'] ?? $title );
		$secondary  = sanitize_text_field( $params['secondary_keywords'] ?? '' );
		$audience   = sanitize_text_field( $params['audience'] ?? 'general tech enthusiasts' );
		$tone       = sanitize_text_field( $params['tone'] ?? $this->settings->get( 'default_tone' ) );
		$word_count = (int) ( $params['word_count'] ?? $this->settings->get( 'default_word_count' ) );

		$tone_descriptions = [
			'professional'      => 'professional and authoritative',
			'friendly'          => 'friendly and approachable',
			'conversational'    => 'conversational and casual',
			'beginner-friendly' => 'beginner-friendly and simple to understand',
			'technical'         => 'highly technical and detailed',
		];

		$tone_desc = $tone_descriptions[ $tone ] ?? 'professional';

		$system_prompt = <<<PROMPT
You are an expert technology blogger and writer with 10+ years of experience writing for top-tier tech publications. Your articles are well-researched, engaging, and genuinely helpful to readers.

When asked to write an article, you must:
- Write completely original, unique content
- Explain concepts clearly with real-world examples
- Use a natural mix of short and long sentences
- Include practical, actionable advice
- Avoid fluff, padding, or repetitive phrasing
- Sound like an experienced human writer, not a bot
- Include comparisons, pros/cons, and real examples where relevant
- Make the article engaging from the opening hook to the final word
PROMPT;

		$user_prompt = <<<PROMPT
Write a complete, high-quality blog article with the following specifications:

**Topic/Title:** {$title}
**Primary Keyword:** {$keyword}
**Secondary Keywords:** {$secondary}
**Target Audience:** {$audience}
**Writing Tone:** {$tone_desc}
**Target Word Count:** approximately {$word_count} words

The article MUST include ALL of these sections:
1. SEO-optimized title (slightly different from the input title if needed)
2. Meta description (150-160 characters, compelling, includes primary keyword)
3. URL slug (lowercase, hyphens, includes primary keyword)
4. Introduction (hook the reader, explain what they'll learn)
5. Table of Contents (linked to main H2 headings)
6. Multiple H2 main sections with H3 subheadings
7. Bullet point lists for features/benefits
8. Numbered lists for step-by-step processes
9. Tips & Best Practices section
10. Common Mistakes to Avoid section
11. FAQ section (5-7 questions with detailed answers)
12. Conclusion (summarize key points, include a call to action)

Return your response as a valid JSON object with this exact structure:
{
  "seoTitle": "string",
  "metaDescription": "string",
  "slug": "string",
  "focusKeyword": "string",
  "content": "string (full article in HTML format using proper h2, h3, p, ul, ol, li, strong, em tags)"
}

Important: The "content" field must be clean, valid HTML. Do not include the title or meta in the content — just the article body starting from the introduction.
PROMPT;

		return $this->make_request( $system_prompt, $user_prompt );
	}

	/**
	 * Improve, expand, or shorten existing article content.
	 *
	 * @param string $content     Current article HTML content.
	 * @param string $instruction Instruction: 'improve' | 'expand' | 'shorten'.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function improve_article( string $content, string $instruction ): array|\WP_Error {
		$allowed_instructions = [ 'improve', 'expand', 'shorten' ];
		if ( ! in_array( $instruction, $allowed_instructions, true ) ) {
			return new \WP_Error( 'invalid_instruction', __( 'Invalid improvement instruction.', 'techzapp-ai-writer' ) );
		}

		$system_prompt = 'You are an expert technology editor. You improve, refine, and enhance blog articles while preserving their HTML structure.';

		$instruction_map = [
			'improve'  => 'Improve the quality, clarity, and engagement of this article. Fix any awkward phrasing, add better examples, make it flow more naturally, and enhance readability. Keep the same structure and approximate length.',
			'expand'   => 'Expand this article by adding more detail, more examples, more context, and additional sections where appropriate. Aim to increase the word count by 30-50% while maintaining quality.',
			'shorten'  => 'Shorten this article by removing fluff, redundancy, and less important sections. Keep all key points but be more concise. Aim to reduce word count by 25-30% while preserving value.',
		];

		$user_prompt = $instruction_map[ $instruction ] . "\n\nReturn only the improved HTML content (no JSON wrapper, just the article HTML):\n\n" . wp_strip_all_tags( $content );

		$result = $this->make_raw_request( $system_prompt, $user_prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [ 'content' => $result ];
	}

	/**
	 * Test the API connection with a minimal request.
	 *
	 * @return bool|\WP_Error
	 */
	public function test_connection(): bool|\WP_Error {
		$result = $this->make_raw_request(
			'You are a helpful assistant.',
			'Say "API connection successful" and nothing else.'
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Make an API request expecting a JSON response.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function make_request( string $system_prompt, string $user_prompt ): array|\WP_Error {
		$raw = $this->make_raw_request( $system_prompt, $user_prompt );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		// Extract JSON from the response (model may wrap in markdown code fences).
		$json_str = $raw;
		if ( preg_match( '/```(?:json)?\s*([\s\S]+?)\s*```/i', $raw, $matches ) ) {
			$json_str = $matches[1];
		}

		$decoded = json_decode( $json_str, true );

		if ( ! is_array( $decoded ) || json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'json_parse_error',
				__( 'Failed to parse AI response as JSON. Please try again.', 'techzapp-ai-writer' ),
				[ 'raw' => substr( $raw, 0, 500 ) ]
			);
		}

		return $decoded;
	}

	/**
	 * Make a raw API request, returning the text content.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @return string|\WP_Error
	 */
	private function make_raw_request( string $system_prompt, string $user_prompt ): string|\WP_Error {
		$api_key = $this->settings->get_api_key();

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'no_api_key',
				__( 'OpenAI API key is not configured. Please add it in Settings.', 'techzapp-ai-writer' )
			);
		}

		$model = (string) $this->settings->get( 'ai_model', 'gpt-4o-mini' );

		$body = wp_json_encode(
			[
				'model' => $model,
				'input' => [
					[
						'role'    => 'system',
						'content' => $system_prompt,
					],
					[
						'role'    => 'user',
						'content' => $user_prompt,
					],
				],
			]
		);

		$response = wp_remote_post(
			self::API_URL,
			[
				'timeout' => 120,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Request to OpenAI failed: %s', 'techzapp-ai-writer' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown OpenAI error.', 'techzapp-ai-writer' );
			return new \WP_Error(
				'openai_api_error',
				$error_message,
				[ 'status' => $code ]
			);
		}

		// Extract text from Responses API output.
		$content = $data['output'][0]['content'][0]['text'] ?? null;

		if ( null === $content ) {
			return new \WP_Error(
				'no_content',
				__( 'No content returned from OpenAI. Please try again.', 'techzapp-ai-writer' )
			);
		}

		return (string) $content;
	}
}
