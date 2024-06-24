<?php

namespace Ibroid\PhpTts;

class Tts
{
  private const SPACE_REGEX = '\s\x{FEFF}\x{A0}';
  private const DEFAULT_PUNCTUATION_REGEX = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';

  /**
   * Asserts that the input types are valid.
   *
   * @param string $text The text to be checked.
   * @param string $lang The language to be checked.
   * @param bool $slow The slow flag to be checked.
   * @param string $host The host to be checked.
   * @throws \InvalidArgumentException If any of the input types are empty strings.
   * @return void
   */
  private function assertInputTypes(string $text, string $lang, bool $slow, string $host): void
  {
    if ($text === '') {
      throw new \InvalidArgumentException('text should be a non-empty string');
    }

    if ($lang === '') {
      throw new \InvalidArgumentException('lang should be a non-empty string');
    }

    if ($host === '') {
      throw new \InvalidArgumentException('host should be a non-empty string');
    }
  }

  /**
   * Determines if a character at the specified position is a space or punctuation.
   *
   * @param string $s The input string.
   * @param int $i The index of the character to check.
   * @param string $splitPunct The punctuation to check for.
   * @return bool
   */
  private function isSpaceOrPunct(string $s, int $i, string $splitPunct): bool
  {
    $splitPunctEscaped = preg_quote($splitPunct, '/');
    $regex = '/[' . self::SPACE_REGEX . self::DEFAULT_PUNCTUATION_REGEX . $splitPunctEscaped . ']/u';
    return (bool) preg_match($regex, mb_substr($s, $i, 1));
  }

  /**
   * Finds the last index of a space or punctuation character in a string within a given range.
   *
   * @param string $s The input string.
   * @param int $left The left boundary of the range (inclusive).
   * @param int $right The right boundary of the range (inclusive).
   * @param string $splitPunct The punctuation to check for.
   * @return int The index of the last space or punctuation character within the range, or -1 if none found.
   */
  private function lastIndexOfSpaceOrPunct(string $s, int $left, int $right, string $splitPunct): int
  {
    for ($i = $right; $i >= $left; $i--) {
      if ($this->isSpaceOrPunct($s, $i, $splitPunct)) {
        return $i;
      }
    }
    return -1;
  }

  /**
   * Splits a long text into an array of short texts.
   *
   * @param string $text The text to be split.
   * @param array $options An optional array of options. Default is an empty array.
   *   - maxLength (int): The maximum length of each short text. Default is 200.
   *   - splitPunct (string): The punctuation to split the text by. Default is an empty string.
   * @return array An array of short texts.
   * @throws \RuntimeException If the word is too long to split into a short text.
   */
  private function splitLongText(string $text, array $options = []): array
  {
    $maxLength = $options['maxLength'] ?? 200;
    $splitPunct = $options['splitPunct'] ?? '';

    $result = [];
    $start = 0;

    while (true) {
      if (mb_strlen($text) - $start <= $maxLength) {
        $result[] = mb_substr($text, $start);
        break;
      }

      $end = $start + $maxLength - 1;

      if (
        $this->isSpaceOrPunct($text, $end, $splitPunct) ||
        $this->isSpaceOrPunct($text, $end + 1, $splitPunct)
      ) {
        $result[] = mb_substr($text, $start, $end - $start + 1);
        $start = $end + 1;
        continue;
      }

      $end = $this->lastIndexOfSpaceOrPunct($text, $start, $end, $splitPunct);
      if ($end === -1) {
        throw new \RuntimeException(
          'The word is too long to split into a short text:' .
            "\n" . mb_substr($text, $start, $maxLength) . " ..." .
            "\n\nTry the option \"splitPunct\" to split the text by punctuation."
        );
      }

      $result[] = mb_substr($text, $start, $end - $start + 1);
      $start = $end + 1;
    }

    return $result;
  }

  /**
   * Retrieves the base64-encoded audio for the given text.
   *
   * @param string $text The text to be converted to audio.
   * @param array $options An optional array of options. Default is an empty array.
   *   - lang (string): The language of the text. Default is 'en'.
   *   - slow (bool): Whether to slow down the speech. Default is false.
   *   - host (string): The host URL for the translation service. Default is 'https://translate.google.com'.
   *   - timeout (int): The timeout in milliseconds for the request. Default is 10000.
   * @throws \InvalidArgumentException If the timeout is not a positive number.
   * @throws \RangeException If the text length is greater than 200 characters.
   * @throws \RuntimeException If the request fails, the language might not exist, or the response structure is unexpected.
   * @return string The base64-encoded audio.
   */
  public function getAudioBase64(string $text, array $options = []): string
  {
    $lang = $options['lang'] ?? 'en';
    $slow = $options['slow'] ?? false;
    $host = $options['host'] ?? 'https://translate.google.com';
    $timeout = $options['timeout'] ?? 10000;

    $this->assertInputTypes($text, $lang, $slow, $host);

    if (!is_int($timeout) || $timeout <= 0) {
      throw new \InvalidArgumentException('timeout should be a positive number');
    }

    if (strlen($text) > 200) {
      throw new \RangeException(
        'text length (' . strlen($text) . ') should be less than 200 characters. Try "getAllAudioBase64" for long text.'
      );
    }

    $data = 'f.req=' . urlencode(json_encode([
      [
        ['jQ1olc', json_encode([$text, $lang, $slow ? true : null, 'null']), null, 'generic']
      ]
    ]));

    $options = [
      'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => $data,
        'timeout' => $timeout / 1000, // convert to seconds
      ],
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($host . '/_/TranslateWebserverUi/data/batchexecute', false, $context);

    if ($result === FALSE) {
      throw new \RuntimeException('Request failed');
    }

    $evalStr = substr($result, 5);
    $parsedRes = json_decode($evalStr, true);

    if (!isset($parsedRes[0][2])) {
      throw new \RuntimeException('parse response failed: unexpected structure');
    }

    $result = $parsedRes[0][2];

    if (!$result) {
      throw new \RuntimeException('lang "' . $lang . '" might not exist');
    }

    $result = json_decode($result, true);

    if (!isset($result[0])) {
      throw new \RuntimeException('parse response failed: unexpected structure');
    }

    return $result[0];
  }

  /**
   * Retrieves the base64-encoded audio for all short texts generated from the given text.
   *
   * @param string $text The text to be converted to audio.
   * @param array $options An optional array of options. Default is an empty array.
   *   - lang (string): The language of the text. Default is 'en'.
   *   - slow (bool): Whether to slow down the speech. Default is false.
   *   - host (string): The host URL for the translation service. Default is 'https://translate.google.com'.
   *   - splitPunct (string): The punctuation to split the text by. Default is an empty string.
   *   - timeout (int): The timeout in milliseconds for the request. Default is 10000.
   * @throws \InvalidArgumentException If splitPunct is not a string or timeout is not a positive number.
   * @return array An array of associative arrays, each containing the short text and its base64-encoded audio.
   */
  public function getAllAudioBase64(string $text, array $options = []): array
  {
    $lang = $options['lang'] ?? 'en';
    $slow = $options['slow'] ?? false;
    $host = $options['host'] ?? 'https://translate.google.com';
    $splitPunct = $options['splitPunct'] ?? '';
    $timeout = $options['timeout'] ?? 10000;

    $this->assertInputTypes($text, $lang, $slow, $host);

    if (!is_string($splitPunct)) {
      throw new \InvalidArgumentException('splitPunct should be a string');
    }

    if (!is_int($timeout) || $timeout <= 0) {
      throw new \InvalidArgumentException('timeout should be a positive number');
    }

    $shortTextList = $this->splitLongText($text, ['splitPunct' => $splitPunct]);

    $result = [];
    foreach ($shortTextList as $shortText) {
      $base64 = $this->getAudioBase64($shortText, [
        'lang' => $lang,
        'slow' => $slow,
        'host' => $host,
        'timeout' => $timeout
      ]);
      $result[] = [
        'shortText' => $shortText,
        'base64' => $base64
      ];
    }

    return $result;
  }

  /**
   * Generates long audio from the given text.
   *
   * @param string $text The text to be converted to audio.
   * @param array $options An optional array of options. Default is an empty array.
   *   - lang (string): The language of the text. Default is 'en'.
   *   - slow (bool): Whether to slow down the speech. Default is false.
   *   - host (string): The host URL for the translation service. Default is 'https://translate.google.com'.
   *   - splitPunct (string): The punctuation to split the text by. Default is an empty string.
   *   - timeout (int): The timeout in milliseconds for the request. Default is 10000.
   * @return array An array of associative arrays, each containing the short text and its base64-encoded audio.
   */
  public static function generateLongAudio(string $text, array $options = []): array
  {
    $instance = new self();
    return $instance->getAllAudioBase64($text, $options);
  }

  /**
   * Generates audio from the given text.
   *
   * @param string $text The text to be converted to audio.
   * @param array $options An optional array of options.
   * @return string The base64-encoded audio.
   */
  public static function generateAudio($text, $options)
  {
    $instance = new self();
    return $instance->getAudioBase64($text, $options);
  }
}
