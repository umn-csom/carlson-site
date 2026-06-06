<?php

namespace Drupal\carlson_general\Service;

/**
 * Compresses cache debug header tokens by abbreviating repeated prefixes.
 */
class CacheTagHeaderCompressor {

  /**
   * Compresses space-separated header tokens using shared prefix initials.
   *
   * Prefix segments are split on ":" and "." only. When a token shares a
   * leading segment sequence with any earlier token, those shared segments are
   * abbreviated to the first character of each underscore-delimited word.
   *
   * @param string $header_value
   *   The raw header value.
   *
   * @return string
   *   The compressed header value.
   */
  public function compress(string $header_value): string {
    $tokens = preg_split('/\s+/', trim($header_value), -1, PREG_SPLIT_NO_EMPTY);
    if ($tokens === FALSE || $tokens === []) {
      return $header_value;
    }

    $compressed_tokens = [];
    $previous_segment_lists = [];

    foreach ($tokens as $token) {
      $parts = $this->parseTag($token);
      $segments = $this->extractSegments($parts);
      $prefix_length = $this->longestCommonPrefixLength(
        $segments,
        $previous_segment_lists,
      );
      $compressed_tokens[] = $this->buildCompressedTag($parts, $prefix_length);
      $previous_segment_lists[] = $segments;
    }

    return implode(' ', $compressed_tokens);
  }

  /**
   * Splits a token into segments and prefix delimiters.
   *
   * @param string $token
   *   A single header token.
   *
   * @return string[]
   *   Alternating segment and delimiter tokens.
   */
  protected function parseTag(string $token): array {
    $parts = preg_split(
      '/([:.])/',
      $token,
      -1,
      PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
    );

    return $parts === FALSE ? [$token] : $parts;
  }

  /**
   * Extracts non-delimiter segments from a parsed token.
   *
   * @param string[] $parts
   *   Parsed token parts.
   *
   * @return string[]
   *   Segment values only.
   */
  protected function extractSegments(array $parts): array {
    $segments = [];
    foreach ($parts as $part) {
      if ($part !== ':' && $part !== '.') {
        $segments[] = $part;
      }
    }

    return $segments;
  }

  /**
   * Finds the longest shared leading segment count with prior tokens.
   *
   * @param string[] $segments
   *   Segments for the current token.
   * @param string[][] $previous_segment_lists
   *   Segment lists for earlier tokens.
   *
   * @return int
   *   Number of leading segments to abbreviate.
   */
  protected function longestCommonPrefixLength(
    array $segments,
    array $previous_segment_lists,
  ): int {
    $max_length = 0;
    foreach ($previous_segment_lists as $previous_segments) {
      $length = 0;
      $limit = min(count($segments), count($previous_segments));
      for ($index = 0; $index < $limit; $index++) {
        if ($segments[$index] !== $previous_segments[$index]) {
          break;
        }
        $length++;
      }
      $max_length = max($max_length, $length);
    }

    return $max_length;
  }

  /**
   * Rebuilds a token, abbreviating the first N segments.
   *
   * @param string[] $parts
   *   Parsed token parts.
   * @param int $abbreviate_count
   *   Number of leading segments to abbreviate.
   *
   * @return string
   *   The compressed token.
   */
  protected function buildCompressedTag(array $parts, int $abbreviate_count): string {
    $output = '';
    $segment_index = 0;

    foreach ($parts as $part) {
      if ($part === ':' || $part === '.') {
        $output .= $part;
        continue;
      }

      if ($segment_index < $abbreviate_count) {
        $output .= $this->abbreviateSegment($part);
      }
      else {
        $output .= $part;
      }
      $segment_index++;
    }

    return $output;
  }

  /**
   * Abbreviates a segment using underscore-delimited word initials.
   *
   * @param string $segment
   *   A single path segment.
   *
   * @return string
   *   The abbreviated segment.
   */
  protected function abbreviateSegment(string $segment): string {
    $initials = '';
    foreach (explode('_', $segment) as $word) {
      if ($word === '') {
        continue;
      }
      $initials .= $word[0];
    }

    return $initials !== '' ? $initials : $segment;
  }

}
