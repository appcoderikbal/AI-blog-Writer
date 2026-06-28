<?php
/**
 * AI Client — multi-provider AI API wrapper.
 *
 * Supports: OpenAI, Groq, Google Gemini
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

namespace TechZappAIWriter;

/**
 * Class AI_Client
 *
 * Routes article generation and improvement requests to the configured AI provider.
 */
class AI_Client {

	// ─── API Endpoints ───────────────────────────────────────────────────────

	private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
	private const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';

	/**
	 * Gemini API base — model name is injected at runtime.
	 */
	private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

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

	// ─────────────────────────────────────────────────────────────────────────
	// Public Interface
	// ─────────────────────────────────────────────────────────────────────────

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

		return $this->make_structured_request( $system_prompt, $user_prompt );
	}

	/**
	 * Improve, expand, or shorten existing article content.
	 *
	 * @param string $content     Current article HTML content.
	 * @param string $instruction Instruction: 'improve' | 'expand' | 'shorten'.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function improve_article( string $content, string $instruction ): array|\WP_Error {
		$allowed = [ 'improve', 'expand', 'shorten' ];
		if ( ! in_array( $instruction, $allowed, true ) ) {
			return new \WP_Error( 'invalid_instruction', __( 'Invalid improvement instruction.', 'techzapp-ai-writer' ) );
		}

		$system_prompt = 'You are an expert technology editor. You improve, refine, and enhance blog articles while preserving their HTML structure.';

		$instruction_map = [
			'improve'  => 'Improve the quality, clarity, and engagement of this article. Fix any awkward phrasing, add better examples, make it flow more naturally, and enhance readability. Keep the same structure and approximate length.',
			'expand'   => 'Expand this article by adding more detail, more examples, more context, and additional sections where appropriate. Aim to increase the word count by 30-50% while maintaining quality.',
			'shorten'  => 'Shorten this article by removing fluff, redundancy, and less important sections. Keep all key points but be more concise. Aim to reduce word count by 25-30% while preserving value.',
		];

		$user_prompt = $instruction_map[ $instruction ] . "\n\nReturn only the improved HTML content (no JSON wrapper, just the article HTML):\n\n" . wp_strip_all_tags( $content );

		$raw = $this->make_raw_request( $system_prompt, $user_prompt );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		return [ 'content' => $raw ];
	}

	/**
	 * Test the active provider's API connection.
	 *
	 * @return bool|\WP_Error
	 */
	public function test_connection(): bool|\WP_Error {
		$result = $this->make_raw_request(
			'You are a helpful assistant.',
			'Say "API connection successful" and nothing else.'
		);

		return is_wp_error( $result ) ? $result : true;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Provider Routing
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Make a request expecting a structured JSON response.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function make_structured_request( string $system_prompt, string $user_prompt ): array|\WP_Error {
		$raw = $this->make_raw_request( $system_prompt, $user_prompt );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		// Robust JSON extraction: Find the first '{' and the last '}'
		$first_brace = strpos( $raw, '{' );
		$last_brace  = strrpos( $raw, '}' );
		$json_str    = $raw;

		if ( false !== $first_brace && false !== $last_brace && $last_brace > $first_brace ) {
			$json_str = substr( $raw, $first_brace, $last_brace - $first_brace + 1 );
		} else {
			// Fallback to strip markdown code fences if braces not found (unlikely)
			if ( preg_match( '/```(?:json)?\s*([\s\S]+?)\s*```/i', $raw, $matches ) ) {
				$json_str = $matches[1];
			}
		}

		$decoded = json_decode( $json_str, true );

		if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'json_parse_error',
				__( 'Failed to parse AI response as JSON. Please try again.', 'techzapp-ai-writer' ),
				[ 'raw' => substr( $raw, 0, 500 ) ]
			);
		}

		return $decoded;
	}

	/**
	 * Route a raw text request to the active provider.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @return string|\WP_Error
	 */
	private function make_raw_request( string $system_prompt, string $user_prompt ): string|\WP_Error {
		$provider = (string) $this->settings->get( 'ai_provider', 'openai' );

		return match ( $provider ) {
			'groq'   => $this->request_groq( $system_prompt, $user_prompt ),
			'gemini' => $this->request_gemini( $system_prompt, $user_prompt ),
			default  => $this->request_openai( $system_prompt, $user_prompt ),
		};
	}

	// ─────────────────────────────────────────────────────────────────────────
	// OpenAI (Chat Completions API)
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Send a request to the OpenAI Chat Completions API.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @return string|\WP_Error
	 */
	private function request_openai( string $system_prompt, string $user_prompt ): string|\WP_Error {
		$api_key = (string) $this->settings->get( 'openai_api_key', '' );

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'OpenAI API key is not configured. Please add it in Settings.', 'techzapp-ai-writer' ) );
		}

		$model = (string) $this->settings->get( 'ai_model', 'gpt-4o-mini' );

		$body = wp_json_encode(
			[
				'model'    => $model,
				'messages' => [
					[ 'role' => 'system', 'content' => $system_prompt ],
					[ 'role' => 'user', 'content' => $user_prompt ],
				],
				'response_format' => [ 'type' => 'json_object' ],
			]
		);

		$response = wp_remote_post(
			self::OPENAI_URL,
			[
				'timeout' => 120,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => $body,
			]
		);

		return $this->parse_response(
			$response,
			static function ( array $data ): ?string {
				return $data['choices'][0]['message']['content'] ?? null;
			}
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Groq (OpenAI-compatible Chat Completions)
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Send a request to the Groq API (OpenAI-compatible format).
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @return string|\WP_Error
	 */
	private function request_groq( string $system_prompt, string $user_prompt ): string|\WP_Error {
		$api_key = (string) $this->settings->get( 'groq_api_key', '' );

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'Groq API key is not configured. Please add it in Settings.', 'techzapp-ai-writer' ) );
		}

		$model = (string) $this->settings->get( 'ai_model', 'llama-3.3-70b-versatile' );

		$body = wp_json_encode(
			[
				'model'    => $model,
				'messages' => [
					[ 'role' => 'system', 'content' => $system_prompt ],
					[ 'role' => 'user', 'content' => $user_prompt ],
				],
				'response_format' => [ 'type' => 'json_object' ],
			]
		);

		$response = wp_remote_post(
			self::GROQ_URL,
			[
				'timeout' => 120,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => $body,
			]
		);

		return $this->parse_response(
			$response,
			static function ( array $data ): ?string {
				return $data['choices'][0]['message']['content'] ?? null;
			}
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Google Gemini
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Send a request to the Google Gemini API.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @return string|\WP_Error
	 */
	private function request_gemini( string $system_prompt, string $user_prompt ): string|\WP_Error {
		$api_key = (string) $this->settings->get( 'gemini_api_key', '' );

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'Gemini API key is not configured. Please add it in Settings.', 'techzapp-ai-writer' ) );
		}

		$model = (string) $this->settings->get( 'ai_model', 'gemini-2.0-flash' );
		$url   = sprintf( self::GEMINI_URL, $model ) . '?key=' . $api_key;

		// Merge system prompt into the first user turn (Gemini v1beta style).
		$combined_prompt = $system_prompt . "\n\n" . $user_prompt;

		$body = wp_json_encode(
			[
				'contents' => [
					[
						'parts' => [
							[ 'text' => $combined_prompt ],
						],
					],
				],
				'generationConfig' => [
					'temperature'      => 0.7,
					'maxOutputTokens'  => 8192,
					'responseMimeType' => 'application/json',
				],
			]
		);

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 120,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => $body,
			]
		);

		return $this->parse_response(
			$response,
			static function ( array $data ): ?string {
				return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
			}
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Shared Response Parser
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Parse a WP HTTP response into a content string.
	 *
	 * @param array<mixed>|\WP_Error $response  WP remote response.
	 * @param callable               $extractor Closure to extract content from decoded JSON.
	 * @return string|\WP_Error
	 */
	private function parse_response( array|\WP_Error $response, callable $extractor ): string|\WP_Error {
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'request_failed',
				sprintf( __( 'HTTP request failed: %s', 'techzapp-ai-writer' ), $response->get_error_message() )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$message = $data['error']['message']
				?? $data['error']['status']
				?? __( 'Unknown API error.', 'techzapp-ai-writer' );
			return new \WP_Error( 'api_error', $message, [ 'status' => $code ] );
		}

		$content = $extractor( $data );

		if ( null === $content || '' === $content ) {
			return new \WP_Error( 'no_content', __( 'No content returned from AI provider. Please try again.', 'techzapp-ai-writer' ) );
		}

		return (string) $content;
	}
}
