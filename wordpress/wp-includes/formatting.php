<?php
/**
 * Main WordPress Formatting API.
 *
 * Handles many functions for formatting output.
 *
 * @package WordPress
 */

/**
 * Replaces common plain text characters into formatted entities
 *
 * As an example,
 *
 *     'cause today's effort makes it worth tomorrow's "holiday" ...
 *
 * Becomes:
 *
 *     &#8217;cause today&#8217;s effort makes it worth tomorrow&#8217;s &#8220;holiday&#8221; &#8230;
 *
 * Code within certain html blocks are skipped.
 *
 * Do not use this function before the {@see 'init'} action hook; everything will break.
 *
 * @since 0.71
 *
 * @global array $wp_cockneyreplace Array of formatted entities for certain common phrases
 * @global array $shortcode_tags
 * @staticvar array  $static_characters
 * @staticvar array  $static_replacements
 * @staticvar array  $dynamic_characters
 * @staticvar array  $dynamic_replacements
 * @staticvar array  $default_no_texturize_tags
 * @staticvar array  $default_no_texturize_shortcodes
 * @staticvar bool   $run_texturize
 * @staticvar string $apos
 * @staticvar string $prime
 * @staticvar string $double_prime
 * @staticvar string $opening_quote
 * @staticvar string $closing_quote
 * @staticvar string $opening_single_quote
 * @staticvar string $closing_single_quote
 * @staticvar string $open_q_flag
 * @staticvar string $open_sq_flag
 * @staticvar string $apos_flag
 *
 * @param string $text The text to be formatted
 * @param bool   $reset Set to true for unit testing. Translated patterns will reset.
 * @return string The string replaced with html entities
 */
function wptexturize( $text, $reset = false ) {
	global $wp_cockneyreplace, $shortcode_tags;
	static $static_characters = null,
		$static_replacements = null,
		$dynamic_characters = null,
		$dynamic_replacements = null,
		$default_no_texturize_tags = null,
		$default_no_texturize_shortcodes = null,
		$run_texturize = true,
		$apos = null,
		$prime = null,
		$double_prime = null,
		$opening_quote = null,
		$closing_quote = null,
		$opening_single_quote = null,
		$closing_single_quote = null,
		$open_q_flag = '<!--oq-->',
		$open_sq_flag = '<!--osq-->',
		$apos_flag = '<!--apos-->';

	// If there's nothing to do, just stop.
	if ( empty( $text ) || false === $run_texturize ) {
		return $text;
	}

	// Set up static variables. Run once only.
	if ( $reset || ! isset( $static_characters ) ) {
		/**
		 * Filters whether to skip running wptexturize().
		 *
		 * Passing false to the filter will effectively short-circuit wptexturize().
		 * returning the original text passed to the function instead.
		 *
		 * The filter runs only once, the first time wptexturize() is called.
		 *
		 * @since 4.0.0
		 *
		 * @see wptexturize()
		 *
		 * @param bool $run_texturize Whether to short-circuit wptexturize().
		 */
		$run_texturize = apply_filters( 'run_wptexturize', $run_texturize );
		if ( false === $run_texturize ) {
			return $text;
		}

		/* translators: opening curly double quote */
		$opening_quote = _x( '&#8220;', 'opening curly double quote' );
		/* translators: closing curly double quote */
		$closing_quote = _x( '&#8221;', 'closing curly double quote' );

		/* translators: apostrophe, for example in 'cause or can't */
		$apos = _x( '&#8217;', 'apostrophe' );

		/* translators: prime, for example in 9' (nine feet) */
		$prime = _x( '&#8242;', 'prime' );
		/* translators: double prime, for example in 9" (nine inches) */
		$double_prime = _x( '&#8243;', 'double prime' );

		/* translators: opening curly single quote */
		$opening_single_quote = _x( '&#8216;', 'opening curly single quote' );
		/* translators: closing curly single quote */
		$closing_single_quote = _x( '&#8217;', 'closing curly single quote' );

		/* translators: en dash */
		$en_dash = _x( '&#8211;', 'en dash' );
		/* translators: em dash */
		$em_dash = _x( '&#8212;', 'em dash' );

		$default_no_texturize_tags = array('pre', 'code', 'kbd', 'style', 'script', 'tt');
		$default_no_texturize_shortcodes = array('code');

		// if a plugin has provided an autocorrect array, use it
		if ( isset($wp_cockneyreplace) ) {
			$cockney = array_keys( $wp_cockneyreplace );
			$cockneyreplace = array_values( $wp_cockneyreplace );
		} else {
			/* translators: This is a comma-separated list of words that defy the syntax of quotations in normal use,
			 * for example...  'We do not have enough words yet' ... is a typical quoted phrase.  But when we write
			 * lines of code 'til we have enough of 'em, then we need to insert apostrophes instead of quotes.
			 */
			$cockney = explode( ',', _x( "'tain't,'twere,'twas,'tis,'twill,'til,'bout,'nuff,'round,'cause,'em",
				'Comma-separated list of words to texturize in your language' ) );

			$cockneyreplace = explode( ',', _x( '&#8217;tain&#8217;t,&#8217;twere,&#8217;twas,&#8217;tis,&#8217;twill,&#8217;til,&#8217;bout,&#8217;nuff,&#8217;round,&#8217;cause,&#8217;em',
				'Comma-separated list of replacement words in your language' ) );
		}

		$static_characters = array_merge( array( '...', '``', '\'\'', ' (tm)' ), $cockney );
		$static_replacements = array_merge( array( '&#8230;', $opening_quote, $closing_quote, ' &#8482;' ), $cockneyreplace );


		// Pattern-based replacements of characters.
		// Sort the remaining patterns into several arrays for performance tuning.
		$dynamic_characters = array( 'apos' => array(), 'quote' => array(), 'dash' => array() );
		$dynamic_replacements = array( 'apos' => array(), 'quote' => array(), 'dash' => array() );
		$dynamic = array();
		$spaces = wp_spaces_regexp();

		// '99' and '99" are ambiguous among other patterns; assume it's an abbreviated year at the end of a quotation.
		if ( "'" !== $apos || "'" !== $closing_single_quote ) {
			$dynamic[ '/\'(\d\d)\'(?=\Z|[.,:;!?)}\-\]]|&gt;|' . $spaces . ')/' ] = $apos_flag . '$1' . $closing_single_quote;
		}
		if ( "'" !== $apos || '"' !== $closing_quote ) {
			$dynamic[ '/\'(\d\d)"(?=\Z|[.,:;!?)}\-\]]|&gt;|' . $spaces . ')/' ] = $apos_flag . '$1' . $closing_quote;
		}

		// '99 '99s '99's (apostrophe)  But never '9 or '99% or '999 or '99.0.
		if ( "'" !== $apos ) {
			$dynamic[ '/\'(?=\d\d(?:\Z|(?![%\d]|[.,]\d)))/' ] = $apos_flag;
		}

		// Quoted Numbers like '0.42'
		if ( "'" !== $opening_single_quote && "'" !== $closing_single_quote ) {
			$dynamic[ '/(?<=\A|' . $spaces . ')\'(\d[.,\d]*)\'/' ] = $open_sq_flag . '$1' . $closing_single_quote;
		}

		// Single quote at start, or preceded by (, {, <, [, ", -, or spaces.
		if ( "'" !== $opening_single_quote ) {
			$dynamic[ '/(?<=\A|[([{"\-]|&lt;|' . $spaces . ')\'/' ] = $open_sq_flag;
		}

		// Apostrophe in a word.  No spaces, double apostrophes, or other punctuation.
		if ( "'" !== $apos ) {
			$dynamic[ '/(?<!' . $spaces . ')\'(?!\Z|[.,:;!?"\'(){}[\]\-]|&[lg]t;|' . $spaces . ')/' ] = $apos_flag;
		}

		$dynamic_characters['apos'] = array_keys( $dynamic );
		$dynamic_replacements['apos'] = array_values( $dynamic );
		$dynamic = array();

		// Quoted Numbers like "42"
		if ( '"' !== $opening_quote && '"' !== $closing_quote ) {
			$dynamic[ '/(?<=\A|' . $spaces . ')"(\d[.,\d]*)"/' ] = $open_q_flag . '$1' . $closing_quote;
		}

		// Double quote at start, or preceded by (, {, <, [, -, or spaces, and not followed by spaces.
		if ( '"' !== $opening_quote ) {
			$dynamic[ '/(?<=\A|[([{\-]|&lt;|' . $spaces . ')"(?!' . $spaces . ')/' ] = $open_q_flag;
		}

		$dynamic_characters['quote'] = array_keys( $dynamic );
		$dynamic_replacements['quote'] = array_values( $dynamic );
		$dynamic = array();

		// Dashes and spaces
		$dynamic[ '/---/' ] = $em_dash;
		$dynamic[ '/(?<=^|' . $spaces . ')--(?=$|' . $spaces . ')/' ] = $em_dash;
		$dynamic[ '/(?<!xn)--/' ] = $en_dash;
		$dynamic[ '/(?<=^|' . $spaces . ')-(?=$|' . $spaces . ')/' ] = $en_dash;

		$dynamic_characters['dash'] = array_keys( $dynamic );
		$dynamic_replacements['dash'] = array_values( $dynamic );
	}

	// Must do this every time in case plugins use these filters in a context sensitive manner
	/**
	 * Filters the list of HTML elements not to texturize.
	 *
	 * @since 2.8.0
	 *
	 * @param array $default_no_texturize_tags An array of HTML element names.
	 */
	$no_texturize_tags = apply_filters( 'no_texturize_tags', $default_no_texturize_tags );
	/**
	 * Filters the list of shortcodes not to texturize.
	 *
	 * @since 2.8.0
	 *
	 * @param array $default_no_texturize_shortcodes An array of shortcode names.
	 */
	$no_texturize_shortcodes = apply_filters( 'no_texturize_shortcodes', $default_no_texturize_shortcodes );

	$no_texturize_tags_stack = array();
	$no_texturize_shortcodes_stack = array();

	// Look for shortcodes and HTML elements.

	preg_match_all( '@\[/?([^<>&/\[\]\x00-\x20=]++)@', $text, $matches );
	$tagnames = array_intersect( array_keys( $shortcode_tags ), $matches[1] );
	$found_shortcodes = ! empty( $tagnames );
	$shortcode_regex = $found_shortcodes ? _get_wptexturize_shortcode_regex( $tagnames ) : '';
	$regex = _get_wptexturize_split_regex( $shortcode_regex );

	$textarr = preg_split( $regex, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

	foreach ( $textarr as &$curl ) {
		// Only call _wptexturize_pushpop_element if $curl is a delimiter.
		$first = $curl[0];
		if ( '<' === $first ) {
			if ( '<!--' === substr( $curl, 0, 4 ) ) {
				// This is an HTML comment delimiter.
				continue;
			} else {
				// This is an HTML element delimiter.

				// Replace each & with &#038; unless it already looks like an entity.
				$curl = preg_replace( '/&(?!#(?:\d+|x[a-f0-9]+);|[a-z1-4]{1,8};)/i', '&#038;', $curl );

				_wptexturize_pushpop_element( $curl, $no_texturize_tags_stack, $no_texturize_tags );
			}

		} elseif ( '' === trim( $curl ) ) {
			// This is a newline between delimiters.  Performance improves when we check this.
			continue;

		} elseif ( '[' === $first && $found_shortcodes && 1 === preg_match( '/^' . $shortcode_regex . '$/', $curl ) ) {
			// This is a shortcode delimiter.

			if ( '[[' !== substr( $curl, 0, 2 ) && ']]' !== substr( $curl, -2 ) ) {
				// Looks like a normal shortcode.
				_wptexturize_pushpop_element( $curl, $no_texturize_shortcodes_stack, $no_texturize_shortcodes );
			} else {
				// Looks like an escaped shortcode.
				continue;
			}

		} elseif ( empty( $no_texturize_shortcodes_stack ) && empty( $no_texturize_tags_stack ) ) {
			// This is neither a delimiter, nor is this content inside of no_texturize pairs.  Do texturize.

			$curl = str_replace( $static_characters, $static_replacements, $curl );

			if ( false !== strpos( $curl, "'" ) ) {
				$curl = preg_replace( $dynamic_characters['apos'], $dynamic_replacements['apos'], $curl );
				$curl = wptexturize_primes( $curl, "'", $prime, $open_sq_flag, $closing_single_quote );
				$curl = str_replace( $apos_flag, $apos, $curl );
				$curl = str_replace( $open_sq_flag, $opening_single_quote, $curl );
			}
			if ( false !== strpos( $curl, '"' ) ) {
				$curl = preg_replace( $dynamic_characters['quote'], $dynamic_replacements['quote'], $curl );
				$curl = wptexturize_primes( $curl, '"', $double_prime, $open_q_flag, $closing_quote );
				$curl = str_replace( $open_q_flag, $opening_quote, $curl );
			}
			if ( false !== strpos( $curl, '-' ) ) {
				$curl = preg_replace( $dynamic_characters['dash'], $dynamic_replacements['dash'], $curl );
			}

			// 9x9 (times), but never 0x9999
			if ( 1 === preg_match( '/(?<=\d)x\d/', $curl ) ) {
				// Searching for a digit is 10 times more expensive than for the x, so we avoid doing this one!
				$curl = preg_replace( '/\b(\d(?(?<=0)[\d\.,]+|[\d\.,]*))x(\d[\d\.,]*)\b/', '$1&#215;$2', $curl );
			}

			// Replace each & with &#038; unless it already looks like an entity.
			$curl = preg_replace( '/&(?!#(?:\d+|x[a-f0-9]+);|[a-z1-4]{1,8};)/i', '&#038;', $curl );
		}
	}

	return implode( '', $textarr );
}

/**
 * Implements a logic tree to determine whether or not "7'." represents seven feet,
 * then converts the special char into either a prime char or a closing quote char.
 *
 * @since 4.3.0
 *
 * @param string $haystack    The plain text to be searched.
 * @param string $needle      The character to search for such as ' or ".
 * @param string $prime       The prime char to use for replacement.
 * @param string $open_quote  The opening quote char. Opening quote replacement must be
 *                            accomplished already.
 * @param string $close_quote The closing quote char to use for replacement.
 * @return string The $haystack value after primes and quotes replacements.
 */
function wptexturize_primes( $haystack, $needle, $prime, $open_quote, $close_quote ) {
	$spaces = wp_spaces_regexp();
	$flag = '<!--wp-prime-or-quote-->';
	$quote_pattern = "/$needle(?=\\Z|[.,:;!?)}\\-\\]]|&gt;|" . $spaces . ")/";
	$prime_pattern    = "/(?<=\\d)$needle/";
	$flag_after_digit = "/(?<=\\d)$flag/";
	$flag_no_digit    = "/(?<!\\d)$flag/";

	$sentences = explode( $open_quote, $haystack );

	foreach ( $sentences as $key => &$sentence ) {
		if ( false === strpos( $sentence, $needle ) ) {
			continue;
		} elseif ( 0 !== $key && 0 === substr_count( $sentence, $close_quote ) ) {
			$sentence = preg_replace( $quote_pattern, $flag, $sentence, -1, $count );
			if ( $count > 1 ) {
				// This sentence appears to have multiple closing quotes.  Attempt Vulcan logic.
				$sentence = preg_replace( $flag_no_digit, $close_quote, $sentence, -1, $count2 );
				if ( 0 === $count2 ) {
					// Try looking for a quote followed by a period.
					$count2 = substr_count( $sentence, "$flag." );
					if ( $count2 > 0 ) {
						// Assume the rightmost quote-period match is the end of quotation.
						$pos = strrpos( $sentence, "$flag." );
					} else {
						// When all else fails, make the rightmost candidate a closing quote.
						// This is most likely to be problematic in the context of bug #18549.
						$pos = strrpos( $sentence, $flag );
					}
					$sentence = substr_replace( $sentence, $close_quote, $pos, strlen( $flag ) );
				}
				// Use conventional replacement on any remaining primes and quotes.
				$sentence = preg_replace( $prime_pattern, $prime, $sentence );
				$sentence = preg_replace( $flag_after_digit, $prime, $sentence );
				$sentence = str_replace( $flag, $close_quote, $sentence );
			} elseif ( 1 == $count ) {
				// Found only one closing quote candidate, so give it priority over primes.
				$sentence = str_replace( $flag, $close_quote, $sentence );
				$sentence = preg_replace( $prime_pattern, $prime, $sentence );
			} else {
				// No closing quotes found.  Just run primes pattern.
				$sentence = preg_replace( $prime_pattern, $prime, $sentence );
			}
		} else {
			$sentence = preg_replace( $prime_pattern, $prime, $sentence );
			$sentence = preg_replace( $quote_pattern, $close_quote, $sentence );
		}
		if ( '"' == $needle && false !== strpos( $sentence, '"' ) ) {
			$sentence = str_replace( '"', $close_quote, $sentence );
		}
	}

	return implode( $open_quote, $sentences );
}

/**
 * Search for disabled element tags. Push element to stack on tag open and pop
 * on tag close.
 *
 * Assumes first char of $text is tag opening and last char is tag closing.
 * Assumes second char of $text is optionally '/' to indicate closing as in </html>.
 *
 * @since 2.9.0
 * @access private
 *
 * @param string $text Text to check. Must be a tag like `<html>` or `[shortcode]`.
 * @param array  $stack List of open tag elements.
 * @param array  $disabled_elements The tag names to match against. Spaces are not allowed in tag names.
 */
function _wptexturize_pushpop_element( $text, &$stack, $disabled_elements ) {
	// Is it an opening tag or closing tag?
	if ( isset( $text[1] ) && '/' !== $text[1] ) {
		$opening_tag = true;
		$name_offset = 1;
	} elseif ( 0 == count( $stack ) ) {
		// Stack is empty. Just stop.
		return;
	} else {
		$opening_tag = false;
		$name_offset = 2;
	}

	// Parse out the tag name.
	$space = strpos( $text, ' ' );
	if ( false === $space ) {
		$space = -1;
	} else {
		$space -= $name_offset;
	}
	$tag = substr( $text, $name_offset, $space );

	// Handle disabled tags.
	if ( in_array( $tag, $disabled_elements ) ) {
		if ( $opening_tag ) {
			/*
			 * This disables texturize until we find a closing tag of our type
			 * (e.g. <pre>) even if there was invalid nesting before that
			 *
			 * Example: in the case <pre>sadsadasd</code>"baba"</pre>
			 *          "baba" won't be texturize
			 */

			array_push( $stack, $tag );
		} elseif ( end( $stack ) == $tag ) {
			array_pop( $stack );
		}
	}
}

/**
 * Replaces double line-breaks with paragraph elements.
 *
 * A group of regex replaces used to identify text formatted with newlines and
 * replace double line-breaks with HTML paragraph tags. The remaining line-breaks
 * after conversion become <<br />> tags, unless $br is set to '0' or 'false'.
 *
 * @since 0.71
 *
 * @param string $pee The text which has to be formatted.
 * @param bool   $br  Optional. If set, this will convert all remaining line-breaks
 *                    after paragraphing. Default true.
 * @return string Text which has been converted into correct paragraph tags.
 */
function wpautop( $pee, $br = true ) {
	$pre_tags = array();

	if ( trim($pee) === '' )
		return '';

	// Just to make things a little easier, pad the end.
	$pee = $pee . "\n";

	/*
	 * Pre tags shouldn't be touched by autop.
	 * Replace pre tags with placeholders and bring them back after autop.
	 */
	if ( strpos($pee, '<pre') !== false ) {
		$pee_parts = explode( '</pre>', $pee );
		$last_pee = array_pop($pee_parts);
		$pee = '';
		$i = 0;

		foreach ( $pee_parts as $pee_part ) {
			$start = strpos($pee_part, '<pre');

			// Malformed html?
			if ( $start === false ) {
				$pee .= $pee_part;
				continue;
			}

			$name = "<pre wp-pre-tag-$i></pre>";
			$pre_tags[$name] = substr( $pee_part, $start ) . '</pre>';

			$pee .= substr( $pee_part, 0, $start ) . $name;
			$i++;
		}

		$pee .= $last_pee;
	}
	// Change multiple <br>s into two line breaks, which will turn into paragraphs.
	$pee = preg_replace('|<br\s*/?>\s*<br\s*/?>|', "\n\n", $pee);

	$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';

	// Add a double line break above block-level opening tags.
	$pee = preg_replace('!(<' . $allblocks . '[\s/>])!', "\n\n$1", $pee);

	// Add a double line break below block-level closing tags.
	$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);

	// Standardize newline characters to "\n".
	$pee = str_replace(array("\r\n", "\r"), "\n", $pee);

	// Find newlines in all elements and add placeholders.
	$pee = wp_replace_in_html_tags( $pee, array( "\n" => " <!-- wpnl --> " ) );

	// Collapse line breaks before and after <option> elements so they don't get autop'd.
	if ( strpos( $pee, '<option' ) !== false ) {
		$pee = preg_replace( '|\s*<option|', '<option', $pee );
		$pee = preg_replace( '|</option>\s*|', '</option>', $pee );
	}

	/*
	 * Collapse line breaks inside <object> elements, before <param> and <embed> elements
	 * so they don't get autop'd.
	 */
	if ( strpos( $pee, '</object>' ) !== false ) {
		$pee = preg_replace( '|(<object[^>]*>)\s*|', '$1', $pee );
		$pee = preg_replace( '|\s*</object>|', '</object>', $pee );
		$pee = preg_replace( '%\s*(</?(?:param|embed)[^>]*>)\s*%', '$1', $pee );
	}

	/*
	 * Collapse line breaks inside <audio> and <video> elements,
	 * before and after <source> and <track> elements.
	 */
	if ( strpos( $pee, '<source' ) !== false || strpos( $pee, '<track' ) !== false ) {
		$pee = preg_replace( '%([<\[](?:audio|video)[^>\]]*[>\]])\s*%', '$1', $pee );
		$pee = preg_replace( '%\s*([<\[]/(?:audio|video)[>\]])%', '$1', $pee );
		$pee = preg_replace( '%\s*(<(?:source|track)[^>]*>)\s*%', '$1', $pee );
	}

	// Collapse line breaks before and after <figcaption> elements.
	if ( strpos( $pee, '<figcaption' ) !== false ) {
		$pee = preg_replace( '|\s*(<figcaption[^>]*>)|', '$1', $pee );
		$pee = preg_replace( '|</figcaption>\s*|', '</figcaption>', $pee );
	}

	// Remove more than two contiguous line breaks.
	$pee = preg_replace("/\n\n+/", "\n\n", $pee);

	// Split up the contents into an array of strings, separated by double line breaks.
	$pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);

	// Reset $pee prior to rebuilding.
	$pee = '';

	// Rebuild the content as a string, wrapping every bit with a <p>.
	foreach ( $pees as $tinkle ) {
		$pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
	}

	// Under certain strange conditions it could create a P of entirely whitespace.
	$pee = preg_replace('|<p>\s*</p>|', '', $pee);

	// Add a closing <p> inside <div>, <address>, or <form> tag if missing.
	$pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $pee);

	// If an opening or closing block element tag is wrapped in a <p>, unwrap it.
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);

	// In some cases <li> may get wrapped in <p>, fix them.
	$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee);

	// If a <blockquote> is wrapped with a <p>, move it inside the <blockquote>.
	$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
	$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);

	// If an opening or closing block element tag is preceded by an opening <p> tag, remove it.
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);

	// If an opening or closing block element tag is followed by a closing <p> tag, remove it.
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);

	// Optionally insert line breaks.
	if ( $br ) {
		// Replace newlines that shouldn't be touched with a placeholder.
		$pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', '_autop_newline_preservation_helper', $pee);

		// Normalize <br>
		$pee = str_replace( array( '<br>', '<br/>' ), '<br />', $pee );

		// Replace any new line characters that aren't preceded by a <br /> with a <br />.
		$pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee);

		// Replace newline placeholders with newlines.
		$pee = str_replace('<WPPreserveNewline />', "\n", $pee);
	}

	// If a <br /> tag is after an opening or closing block tag, remove it.
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);

	// If a <br /> tag is before a subset of opening or closing block tags, remove it.
	$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
	$pee = preg_replace( "|\n</p>$|", '</p>', $pee );

	// Replace placeholder <pre> tags with their original content.
	if ( !empty($pre_tags) )
		$pee = str_replace(array_keys($pre_tags), array_values($pre_tags), $pee);

	// Restore newlines in all elements.
	if ( false !== strpos( $pee, '<!-- wpnl -->' ) ) {
		$pee = str_replace( array( ' <!-- wpnl --> ', '<!-- wpnl -->' ), "\n", $pee );
	}

	return $pee;
}

/**
 * Separate HTML elements and comments from the text.
 *
 * @since 4.2.4
 *
 * @param string $input The text which has to be formatted.
 * @return array The formatted text.
 */
function wp_html_split( $input ) {
	return preg_split( get_html_split_regex(), $input, -1, PREG_SPLIT_DELIM_CAPTURE );
}

/**
 * Retrieve the regular expression for an HTML element.
 *
 * @since 4.4.0
 *
 * @staticvar string $regex
 *
 * @return string The regular expression
 */
function get_html_split_regex() {
	static $regex;

	if ( ! isset( $regex ) ) {
		$comments =
			  '!'           // Start of comment, after the <.
			. '(?:'         // Unroll the loop: Consume everything until --> is found.
			.     '-(?!->)' // Dash not followed by end of comment.
			.     '[^\-]*+' // Consume non-dashes.
			. ')*+'         // Loop possessively.
			. '(?:-->)?';   // End of comment. If not found, match all input.

		$cdata =
			  '!\[CDATA\['  // Start of comment, after the <.
			. '[^\]]*+'     // Consume non-].
			. '(?:'         // Unroll the loop: Consume everything until ]]> is found.
			.     '](?!]>)' // One ] not followed by end of comment.
			.     '[^\]]*+' // Consume non-].
			. ')*+'         // Loop possessively.
			. '(?:]]>)?';   // End of comment. If not found, match all input.

		$escaped =
			  '(?='           // Is the element escaped?
			.    '!--'
			. '|'
			.    '!\[CDATA\['
			. ')'
			. '(?(?=!-)'      // If yes, which type?
			.     $comments
			. '|'
			.     $cdata
			. ')';

		$regex =
			  '/('              // Capture the entire match.
			.     '<'           // Find start of element.
			.     '(?'          // Conditional expression follows.
			.         $escaped  // Find end of escaped element.
			.     '|'           // ... else ...
			.         '[^>]*>?' // Find end of normal element.
			.     ')'
			. ')/';
	}

	return $regex;
}

/**
 * Retrieve the combined regular expression for HTML and shortcodes.
 *
 * @access private
 * @ignore
 * @internal This function will be removed in 4.5.0 per Shortcode API Roadmap.
 * @since 4.4.0
 *
 * @staticvar string $html_regex
 *
 * @param string $shortcode_regex The result from _get_wptexturize_shortcode_regex().  Optional.
 * @return string The regular expression
 */
function _get_wptexturize_split_regex( $shortcode_regex = '' ) {
	static $html_regex;

	if ( ! isset( $html_regex ) ) {
		$comment_regex =
			  '!'           // Start of comment, after the <.
			. '(?:'         // Unroll the loop: Consume everything until --> is found.
			.     '-(?!->)' // Dash not followed by end of comment.
			.     '[^\-]*+' // Consume non-dashes.
			. ')*+'         // Loop possessively.
			. '(?:-->)?';   // End of comment. If not found, match all input.

		$html_regex =			 // Needs replaced with wp_html_split() per Shortcode API Roadmap.
			  '<'                // Find start of element.
			. '(?(?=!--)'        // Is this a comment?
			.     $comment_regex // Find end of comment.
			. '|'
			.     '[^>]*>?'      // Find end of element. If not found, match all input.
			. ')';
	}

	if ( empty( $shortcode_regex ) ) {
		$regex = '/(' . $html_regex . ')/';
	} else {
		$regex = '/(' . $html_regex . '|' . $shortcode_regex . ')/';
	}

	return $regex;
}

/**
 * Retrieve the regular expression for shortcodes.
 *
 * @access private
 * @ignore
 * @internal This function will be removed in 4.5.0 per Shortcode API Roadmap.
 * @since 4.4.0
 *
 * @param array $tagnames List of shortcodes to find.
 * @return string The regular expression
 */
function _get_wptexturize_shortcode_regex( $tagnames ) {
	$tagregexp = join( '|', array_map( 'preg_quote', $tagnames ) );
	$tagregexp = "(?:$tagregexp)(?=[\\s\\]\\/])"; // Excerpt of get_shortcode_regex().
	$regex =
		  '\['              // Find start of shortcode.
		. '[\/\[]?'         // Shortcodes may begin with [/ or [[
		. $tagregexp        // Only match registered shortcodes, because performance.
		. '(?:'
		.     '[^\[\]<>]+'  // Shortcodes do not contain other shortcodes. Quantifier critical.
		. '|'
		.     '<[^\[\]>]*>' // HTML elements permitted. Prevents matching ] before >.
		. ')*+'             // Possessive critical.
		. '\]'              // Find end of shortcode.
		. '\]?';            // Shortcodes may end with ]]

	return $regex;
}

/**
 * Replace characters or phrases within HTML elements only.
 *
 * @since 4.2.3
 *
 * @param string $haystack The text which has to be formatted.
 * @param array $replace_pairs In the form array('from' => 'to', ...).
 * @return string The formatted text.
 */
function wp_replace_in_html_tags( $haystack, $replace_pairs ) {
	// Find all elements.
	$textarr = wp_html_split( $haystack );
	$changed = false;

	// Optimize when searching for one item.
	if ( 1 === count( $replace_pairs ) ) {
		// Extract $needle and $replace.
		foreach ( $replace_pairs as $needle => $replace );

		// Loop through delimiters (elements) only.
		for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
			if ( false !== strpos( $textarr[$i], $needle ) ) {
				$textarr[$i] = str_replace( $needle, $replace, $textarr[$i] );
				$changed = true;
			}
		}
	} else {
		// Extract all $needles.
		$needles = array_keys( $replace_pairs );

		// Loop through delimiters (elements) only.
		for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $textarr[$i], $needle ) ) {
					$textarr[$i] = strtr( $textarr[$i], $replace_pairs );
					$changed = true;
					// After one strtr() break out of the foreach loop and look at next element.
					break;
				}
			}
		}
	}

	if ( $changed ) {
		$haystack = implode( $textarr );
	}

	return $haystack;
}

/**
 * Newline preservation help function for wpautop
 *
 * @since 3.1.0
 * @access private
 *
 * @param array $matches preg_replace_callback matches array
 * @return string
 */
function _autop_newline_preservation_helper( $matches ) {
	return str_replace( "\n", "<WPPreserveNewline />", $matches[0] );
}

/**
 * Don't auto-p wrap shortcodes that stand alone
 *
 * Ensures that shortcodes are not wrapped in `<p>...</p>`.
 *
 * @since 2.9.0
 *
 * @global array $shortcode_tags
 *
 * @param string $pee The content.
 * @return string The filtered content.
 */
function shortcode_unautop( $pee ) {
	global $shortcode_tags;

	if ( empty( $shortcode_tags ) || !is_array( $shortcode_tags ) ) {
		return $pee;
	}

	$tagregexp = join( '|', array_map( 'preg_quote', array_keys( $shortcode_tags ) ) );
	$spaces = wp_spaces_regexp();

	$pattern =
		  '/'
		. '<p>'                              // Opening paragraph
		. '(?:' . $spaces . ')*+'            // Optional leading whitespace
		. '('                                // 1: The shortcode
		.     '\\['                          // Opening bracket
		.     "($tagregexp)"                 // 2: Shortcode name
		.     '(?![\\w-])'                   // Not followed by word character or hyphen
		                                     // Unroll the loop: Inside the opening shortcode tag
		.     '[^\\]\\/]*'                   // Not a closing bracket or forward slash
		.     '(?:'
		.         '\\/(?!\\])'               // A forward slash not followed by a closing bracket
		.         '[^\\]\\/]*'               // Not a closing bracket or forward slash
		.     ')*?'
		.     '(?:'
		.         '\\/\\]'                   // Self closing tag and closing bracket
		.     '|'
		.         '\\]'                      // Closing bracket
		.         '(?:'                      // Unroll the loop: Optionally, anything between the opening and closing shortcode tags
		.             '[^\\[]*+'             // Not an opening bracket
		.             '(?:'
		.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
		.                 '[^\\[]*+'         // Not an opening bracket
		.             ')*+'
		.             '\\[\\/\\2\\]'         // Closing shortcode tag
		.         ')?'
		.     ')'
		. ')'
		. '(?:' . $spaces . ')*+'            // optional trailing whitespace
		. '<\\/p>'                           // closing paragraph
		. '/';

	return preg_replace( $pattern, '$1', $pee );
}

/**
 * Checks to see if a string is utf8 encoded.
 *
 * NOTE: This function checks for 5-Byte sequences, UTF8
 *       has Bytes Sequences with a maximum length of 4.
 *
 * @author bmorel at ssi dot fr (modified)
 * @since 1.2.1
 *
 * @param string $str The string to be checked
 * @return bool True if $str fits a UTF-8 model, false otherwise.
 */
function seems_utf8( $str ) {
	mbstring_binary_safe_encoding();
	$length = strlen($str);
	reset_mbstring_encoding();
	for ($i=0; $i < $length; $i++) {
		$c = ord($str[$i]);
		if ($c < 0x80) $n = 0; // 0bbbbbbb
		elseif (($c & 0xE0) == 0xC0) $n=1; // 110bbbbb
		elseif (($c & 0xF0) == 0xE0) $n=2; // 1110bbbb
		elseif (($c & 0xF8) == 0xF0) $n=3; // 11110bbb
		elseif (($c & 0xFC) == 0xF8) $n=4; // 111110bb
		elseif (($c & 0xFE) == 0xFC) $n=5; // 1111110b
		else return false; // Does not match any model
		for ($j=0; $j<$n; $j++) { // n bytes matching 10bbbbbb follow ?
			if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
				return false;
		}
	}
	return true;
}

/**
 * Converts a number of special characters into their HTML entities.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to encode " to
 * &quot;, or ENT_QUOTES to do both. Default is ENT_NOQUOTES where no quotes are encoded.
 *
 * @since 1.2.2
 * @access private
 *
 * @staticvar string $_charset
 *
 * @param string     $string         The text which is to be encoded.
 * @param int|string $quote_style    Optional. Converts double quotes if set to ENT_COMPAT,
 *                                   both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES.
 *                                   Also compatible with old values; converting single quotes if set to 'single',
 *                                   double if set to 'double' or both if otherwise set.
 *                                   Default is ENT_NOQUOTES.
 * @param string     $charset        Optional. The character encoding of the string. Default is false.
 * @param bool       $double_encode  Optional. Whether to encode existing html entities. Default is false.
 * @return string The encoded text with HTML entities.
 */
function _wp_specialchars( $string, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false ) {
	$string = (string) $string;

	if ( 0 === strlen( $string ) )
		return '';

	// Don't bother if there are no specialchars - saves some processing
	if ( ! preg_match( '/[&<>"\']/', $string ) )
		return $string;

	// Account for the previous behaviour of the function when the $quote_style is not an accepted value
	if ( empty( $quote_style ) )
		$quote_style = ENT_NOQUOTES;
	elseif ( ! in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) )
		$quote_style = ENT_QUOTES;

	// Store the site charset as a static to avoid multiple calls to wp_load_alloptions()
	if ( ! $charset ) {
		static $_charset = null;
		if ( ! isset( $_charset ) ) {
			$alloptions = wp_load_alloptions();
			$_charset = isset( $alloptions['blog_charset'] ) ? $alloptions['blog_charset'] : '';
		}
		$charset = $_charset;
	}

	if ( in_array( $charset, array( 'utf8', 'utf-8', 'UTF8' ) ) )
		$charset = 'UTF-8';

	$_quote_style = $quote_style;

	if ( $quote_style === 'double' ) {
		$quote_style = ENT_COMPAT;
		$_quote_style = ENT_COMPAT;
	} elseif ( $quote_style === 'single' ) {
		$quote_style = ENT_NOQUOTES;
	}

	if ( ! $double_encode ) {
		// Guarantee every &entity; is valid, convert &garbage; into &amp;garbage;
		// This is required for PHP < 5.4.0 because ENT_HTML401 flag is unavailable.
		$string = wp_kses_normalize_entities( $string );
	}

	$string = @htmlspecialchars( $string, $quote_style, $charset, $double_encode );

	// Back-compat.
	if ( 'single' === $_quote_style )
		$string = str_replace( "'", '&#039;', $string );

	return $string;
}

/**
 * Converts a number of HTML entities into their special characters.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to decode " entities,
 * or ENT_QUOTES to do both " and '. Default is ENT_NOQUOTES where no quotes are decoded.
 *
 * @since 2.8.0
 *
 * @param string     $string The text which is to be decoded.
 * @param string|int $quote_style Optional. Converts double quotes if set to ENT_COMPAT,
 *                                both single and double if set to ENT_QUOTES or
 *                                none if set to ENT_NOQUOTES.
 *                                Also compatible with old _wp_specialchars() values;
 *                                converting single quotes if set to 'single',
 *                                double if set to 'double' or both if otherwise set.
 *                                Default is ENT_NOQUOTES.
 * @return string The decoded text without HTML entities.
 */
function wp_specialchars_decode( $string, $quote_style = ENT_NOQUOTES ) {
	$string = (string) $string;

	if ( 0 === strlen( $string ) ) {
		return '';
	}

	// Don't bother if there are no entities - saves a lot of processing
	if ( strpos( $string, '&' ) === false ) {
		return $string;
	}

	// Match the previous behaviour of _wp_specialchars() when the $quote_style is not an accepted value
	if ( empty( $quote_style ) ) {
		$quote_style = ENT_NOQUOTES;
	} elseif ( !in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) ) {
		$quote_style = ENT_QUOTES;
	}

	// More complete than get_html_translation_table( HTML_SPECIALCHARS )
	$single = array( '&#039;'  => '\'', '&#x27;' => '\'' );
	$single_preg = array( '/&#0*39;/'  => '&#039;', '/&#x0*27;/i' => '&#x27;' );
	$double = array( '&quot;' => '"', '&#034;'  => '"', '&#x22;' => '"' );
	$double_preg = array( '/&#0*34;/'  => '&#034;', '/&#x0*22;/i' => '&#x22;' );
	$others = array( '&lt;'   => '<', '&#060;'  => '<', '&gt;'   => '>', '&#062;'  => '>', '&amp;'  => '&', '&#038;'  => '&', '&#x26;' => '&' );
	$others_preg = array( '/&#0*60;/'  => '&#060;', '/&#0*62;/'  => '&#062;', '/&#0*38;/'  => '&#038;', '/&#x0*26;/i' => '&#x26;' );

	if ( $quote_style === ENT_QUOTES ) {
		$translation = array_merge( $single, $double, $others );
		$translation_preg = array_merge( $single_preg, $double_preg, $others_preg );
	} elseif ( $quote_style === ENT_COMPAT || $quote_style === 'double' ) {
		$translation = array_merge( $double, $others );
		$translation_preg = array_merge( $double_preg, $others_preg );
	} elseif ( $quote_style === 'single' ) {
		$translation = array_merge( $single, $others );
		$translation_preg = array_merge( $single_preg, $others_preg );
	} elseif ( $quote_style === ENT_NOQUOTES ) {
		$translation = $others;
		$translation_preg = $others_preg;
	}

	// Remove zero padding on numeric entities
	$string = preg_replace( array_keys( $translation_preg ), array_values( $translation_preg ), $string );

	// Replace characters according to translation table
	return strtr( $string, $translation );
}

/**
 * Checks for invalid UTF8 in a string.
 *
 * @since 2.8.0
 *
 * @staticvar bool $is_utf8
 * @staticvar bool $utf8_pcre
 *
 * @param string  $string The text which is to be checked.
 * @param bool    $strip Optional. Whether to attempt to strip out invalid UTF8. Default is false.
 * @return string The checked text.
 */
function wp_check_invalid_utf8( $string, $strip = false ) {
	$string = (string) $string;

	if ( 0 === strlen( $string ) ) {
		return '';
	}

	// Store the site charset as a static to avoid multiple calls to get_option()
	static $is_utf8 = null;
	if ( ! isset( $is_utf8 ) ) {
		$is_utf8 = in_array( get_option( 'blog_charset' ), array( 'utf8', 'utf-8', 'UTF8', 'UTF-8' ) );
	}
	if ( ! $is_utf8 ) {
		return $string;
	}

	// Check for support for utf8 in the installed PCRE library once and store the result in a static
	static $utf8_pcre = null;
	if ( ! isset( $utf8_pcre ) ) {
		$utf8_pcre = @preg_match( '/^./u', 'a' );
	}
	// We can't demand utf8 in the PCRE installation, so just return the string in those cases
	if ( !$utf8_pcre ) {
		return $string;
	}

	// preg_match fails when it encounters invalid UTF8 in $string
	if ( 1 === @preg_match( '/^./us', $string ) ) {
		return $string;
	}

	// Attempt to strip the bad chars if requested (not recommended)
	if ( $strip && function_exists( 'iconv' ) ) {
		return iconv( 'utf-8', 'utf-8', $string );
	}

	return '';
}

/**
 * Encode the Unicode values to be used in the URI.
 *
 * @since 1.5.0
 *
 * @param string $utf8_string
 * @param int    $length Max  length of the string
 * @return string String with Unicode encoded for URI.
 */
function utf8_uri_encode( $utf8_string, $length = 0 ) {
	$unicode = '';
	$values = array();
	$num_octets = 1;
	$unicode_length = 0;

	mbstring_binary_safe_encoding();
	$string_length = strlen( $utf8_string );
	reset_mbstring_encoding();

	for ($i = 0; $i < $string_length; $i++ ) {

		$value = ord( $utf8_string[ $i ] );

		if ( $value < 128 ) {
			if ( $length && ( $unicode_length >= $length ) )
				break;
			$unicode .= chr($value);
			$unicode_length++;
		} else {
			if ( count( $values ) == 0 ) {
				if ( $value < 224 ) {
					$num_octets = 2;
				} elseif ( $value < 240 ) {
					$num_octets = 3;
				} else {
					$num_octets = 4;
				}
			}

			$values[] = $value;

			if ( $length && ( $unicode_length + ($num_octets * 3) ) > $length )
				break;
			if ( count( $values ) == $num_octets ) {
				for ( $j = 0; $j < $num_octets; $j++ ) {
					$unicode .= '%' . dechex( $values[ $j ] );
				}

				$unicode_length += $num_octets * 3;

				$values = array();
				$num_octets = 1;
			}
		}
	}

	return $unicode;
}

/**
 * Converts all accent characters to ASCII characters.
 *
 * If there are no accent characters, then the string given is just returned.
 *
 * **Accent characters converted:**
 *
 * Currency signs:
 *
 * |   Code   | Glyph | Replacement |     Description     |
 * | -------- | ----- | ----------- | ------------------- |
 * | U+00A3   | £     | (empty)     | British Pound sign  |
 * | U+20AC   | €     | E           | Euro sign           |
 *
 * Decompositions for Latin-1 Supplement:
 *
 * |  Code   | Glyph | Replacement |               Description              |
 * | ------- | ----- | ----------- | -------------------------------------- |
 * | U+00AA  | ª     | a           | Feminine ordinal indicator             |
 * | U+00BA  | º     | o           | Masculine ordinal indicator            |
 * | U+00C0  | À     | A           | Latin capital letter A with grave      |
 * | U+00C1  | Á     | A           | Latin capital letter A with acute      |
 * | U+00C2  | Â     | A           | Latin capital letter A with circumflex |
 * | U+00C3  | Ã     | A           | Latin capital letter A with tilde      |
 * | U+00C4  | Ä     | A           | Latin capital letter A with diaeresis  |
 * | U+00C5  | Å     | A           | Latin capital letter A with ring above |
 * | U+00C6  | Æ     | AE          | Latin capital letter AE                |
 * | U+00C7  | Ç     | C           | Latin capital letter C with cedilla    |
 * | U+00C8  | È     | E           | Latin capital letter E with grave      |
 * | U+00C9  | É     | E           | Latin capital letter E with acute      |
 * | U+00CA  | Ê     | E           | Latin capital letter E with circumflex |
 * | U+00CB  | Ë     | E           | Latin capital letter E with diaeresis  |
 * | U+00CC  | Ì     | I           | Latin capital letter I with grave      |
 * | U+00CD  | Í     | I           | Latin capital letter I with acute      |
 * | U+00CE  | Î     | I           | Latin capital letter I with circumflex |
 * | U+00CF  | Ï     | I           | Latin capital letter I with diaeresis  |
 * | U+00D0  | Ð     | D           | Latin capital letter Eth               |
 * | U+00D1  | Ñ     | N           | Latin capital letter N with tilde      |
 * | U+00D2  | Ò     | O           | Latin capital letter O with grave      |
 * | U+00D3  | Ó     | O           | Latin capital letter O with acute      |
 * | U+00D4  | Ô     | O           | Latin capital letter O with circumflex |
 * | U+00D5  | Õ     | O           | Latin capital letter O with tilde      |
 * | U+00D6  | Ö     | O           | Latin capital letter O with diaeresis  |
 * | U+00D8  | Ø     | O           | Latin capital letter O with stroke     |
 * | U+00D9  | Ù     | U           | Latin capital letter U with grave      |
 * | U+00DA  | Ú     | U           | Latin capital letter U with acute      |
 * | U+00DB  | Û     | U           | Latin capital letter U with circumflex |
 * | U+00DC  | Ü     | U           | Latin capital letter U with diaeresis  |
 * | U+00DD  | Ý     | Y           | Latin capital letter Y with acute      |
 * | U+00DE  | Þ     | TH          | Latin capital letter Thorn             |
 * | U+00DF  | ß     | s           | Latin small letter sharp s             |
 * | U+00E0  | à     | a           | Latin small letter a with grave        |
 * | U+00E1  | á     | a           | Latin small letter a with acute        |
 * | U+00E2  | â     | a           | Latin small letter a with circumflex   |
 * | U+00E3  | ã     | a           | Latin small letter a with tilde        |
 * | U+00E4  | ä     | a           | Latin small letter a with diaeresis    |
 * | U+00E5  | å     | a           | Latin small letter a with ring above   |
 * | U+00E6  | æ     | ae          | Latin small letter ae                  |
 * | U+00E7  | ç     | c           | Latin small letter c with cedilla      |
 * | U+00E8  | è     | e           | Latin small letter e with grave        |
 * | U+00E9  | é     | e           | Latin small letter e with acute        |
 * | U+00EA  | ê     | e           | Latin small letter e with circumflex   |
 * | U+00EB  | ë     | e           | Latin small letter e with diaeresis    |
 * | U+00EC  | ì     | i           | Latin small letter i with grave        |
 * | U+00ED  | í     | i           | Latin small letter i with acute        |
 * | U+00EE  | î     | i           | Latin small letter i with circumflex   |
 * | U+00EF  | ï     | i           | Latin small letter i with diaeresis    |
 * | U+00F0  | ð     | d           | Latin small letter Eth                 |
 * | U+00F1  | ñ     | n           | Latin small letter n with tilde        |
 * | U+00F2  | ò     | o           | Latin small letter o with grave        |
 * | U+00F3  | ó     | o           | Latin small letter o with acute        |
 * | U+00F4  | ô     | o           | Latin small letter o with circumflex   |
 * | U+00F5  | õ     | o           | Latin small letter o with tilde        |
 * | U+00F6  | ö     | o           | Latin small letter o with diaeresis    |
 * | U+00F8  | ø     | o           | Latin small letter o with stroke       |
 * | U+00F9  | ù     | u           | Latin small letter u with grave        |
 * | U+00FA  | ú     | u           | Latin small letter u with acute        |
 * | U+00FB  | û     | u           | Latin small letter u with circumflex   |
 * | U+00FC  | ü     | u           | Latin small letter u with diaeresis    |
 * | U+00FD  | ý     | y           | Latin small letter y with acute        |
 * | U+00FE  | þ     | th          | Latin small letter Thorn               |
 * | U+00FF  | ÿ     | y           | Latin small letter y with diaeresis    |
 *
 * Decompositions for Latin Extended-A:
 *
 * |  Code   | Glyph | Replacement |                    Description                    |
 * | ------- | ----- | ----------- | ------------------------------------------------- |
 * | U+0100  | Ā     | A           | Latin capital letter A with macron                |
 * | U+0101  | ā     | a           | Latin small letter a with macron                  |
 * | U+0102  | Ă     | A           | Latin capital letter A with breve                 |
 * | U+0103  | ă     | a           | Latin small letter a with breve                   |
 * | U+0104  | Ą     | A           | Latin capital letter A with ogonek                |
 * | U+0105  | ą     | a           | Latin small letter a with ogonek                  |
 * | U+01006 | Ć     | C           | Latin capital letter C with acute                 |
 * | U+0107  | ć     | c           | Latin small letter c with acute                   |
 * | U+0108  | Ĉ     | C           | Latin capital letter C with circumflex            |
 * | U+0109  | ĉ     | c           | Latin small letter c with circumflex              |
 * | U+010A  | Ċ     | C           | Latin capital letter C with dot above             |
 * | U+010B  | ċ     | c           | Latin small letter c with dot above               |
 * | U+010C  | Č     | C           | Latin capital letter C with caron                 |
 * | U+010D  | č     | c           | Latin small letter c with caron                   |
 * | U+010E  | Ď     | D           | Latin capital letter D with caron                 |
 * | U+010F  | ď     | d           | Latin small letter d with caron                   |
 * | U+0110  | Đ     | D           | Latin capital letter D with stroke                |
 * | U+0111  | đ     | d           | Latin small letter d with stroke                  |
 * | U+0112  | Ē     | E           | Latin capital letter E with macron                |
 * | U+0113  | ē     | e           | Latin small letter e with macron                  |
 * | U+0114  | Ĕ     | E           | Latin capital letter E with breve                 |
 * | U+0115  | ĕ     | e           | Latin small letter e with breve                   |
 * | U+0116  | Ė     | E           | Latin capital letter E with dot above             |
 * | U+0117  | ė     | e           | Latin small letter e with dot above               |
 * | U+0118  | Ę     | E           | Latin capital letter E with ogonek                |
 * | U+0119  | ę     | e           | Latin small letter e with ogonek                  |
 * | U+011A  | Ě     | E           | Latin capital letter E with caron                 |
 * | U+011B  | ě     | e           | Latin small letter e with caron                   |
 * | U+011C  | Ĝ     | G           | Latin capital letter G with circumflex            |
 * | U+011D  | ĝ     | g           | Latin small letter g with circumflex              |
 * | U+011E  | Ğ     | G           | Latin capital letter G with breve                 |
 * | U+011F  | ğ     | g           | Latin small letter g with breve                   |
 * | U+0120  | Ġ     | G           | Latin capital letter G with dot above             |
 * | U+0121  | ġ     | g           | Latin small letter g with dot above               |
 * | U+0122  | Ģ     | G           | Latin capital letter G with cedilla               |
 * | U+0123  | ģ     | g           | Latin small letter g with cedilla                 |
 * | U+0124  | Ĥ     | H           | Latin capital letter H with circumflex            |
 * | U+0125  | ĥ     | h           | Latin small letter h with circumflex              |
 * | U+0126  | Ħ     | H           | Latin capital letter H with stroke                |
 * | U+0127  | ħ     | h           | Latin small letter h with stroke                  |
 * | U+0128  | Ĩ     | I           | Latin capital letter I with tilde                 |
 * | U+0129  | ĩ     | i           | Latin small letter i with tilde                   |
 * | U+012A  | Ī     | I           | Latin capital letter I with macron                |
 * | U+012B  | ī     | i           | Latin small letter i with macron                  |
 * | U+012C  | Ĭ     | I           | Latin capital letter I with breve                 |
 * | U+012D  | ĭ     | i           | Latin small letter i with breve                   |
 * | U+012E  | Į     | I           | Latin capital letter I with ogonek                |
 * | U+012F  | į     | i           | Latin small letter i with ogonek                  |
 * | U+0130  | İ     | I           | Latin capital letter I with dot above             |
 * | U+0131  | ı     | i           | Latin small letter dotless i                      |
 * | U+0132  | Ĳ     | IJ          | Latin capital ligature IJ                         |
 * | U+0133  | ĳ     | ij          | Latin small ligature ij                           |
 * | U+0134  | Ĵ     | J           | Latin capital letter J with circumflex            |
 * | U+0135  | ĵ     | j           | Latin small letter j with circumflex              |
 * | U+0136  | Ķ     | K           | Latin capital letter K with cedilla               |
 * | U+0137  | ķ     | k           | Latin small letter k with cedilla                 |
 * | U+0138  | ĸ     | k           | Latin small letter Kra                            |
 * | U+0139  | Ĺ     | L           | Latin capital letter L with acute                 |
 * | U+013A  | ĺ     | l           | Latin small letter l with acute                   |
 * | U+013B  | Ļ     | L           | Latin capital letter L with cedilla               |
 * | U+013C  | ļ     | l           | Latin small letter l with cedilla                 |
 * | U+013D  | Ľ     | L           | Latin capital letter L with caron                 |
 * | U+013E  | ľ     | l           | Latin small letter l with caron                   |
 * | U+013F  | Ŀ     | L           | Latin capital letter L with middle dot            |
 * | U+0140  | ŀ     | l           | Latin small letter l with middle dot              |
 * | U+0141  | Ł     | L           | Latin capital letter L with stroke                |
 * | U+0142  | ł     | l           | Latin small letter l with stroke                  |
 * | U+0143  | Ń     | N           | Latin capital letter N with acute                 |
 * | U+0144  | ń     | n           | Latin small letter N with acute                   |
 * | U+0145  | Ņ     | N           | Latin capital letter N with cedilla               |
 * | U+0146  | ņ     | n           | Latin small letter n with cedilla                 |
 * | U+0147  | Ň     | N           | Latin capital letter N with caron                 |
 * | U+0148  | ň     | n           | Latin small letter n with caron                   |
 * | U+0149  | ŉ     | n           | Latin small letter n preceded by apostrophe       |
 * | U+014A  | Ŋ     | N           | Latin capital letter Eng                          |
 * | U+014B  | ŋ     | n           | Latin small letter Eng                            |
 * | U+014C  | Ō     | O           | Latin capital letter O with macron                |
 * | U+014D  | ō     | o           | Latin small letter o with macron                  |
 * | U+014E  | Ŏ     | O           | Latin capital letter O with breve                 |
 * | U+014F  | ŏ     | o           | Latin small letter o with breve                   |
 * | U+0150  | Ő     | O           | Latin capital letter O with double acute          |
 * | U+0151  | ő     | o           | Latin small letter o with double acute            |
 * | U+0152  | Œ     | OE          | Latin capital ligature OE                         |
 * | U+0153  | œ     | oe          | Latin small ligature oe                           |
 * | U+0154  | Ŕ     | R           | Latin capital letter R with acute                 |
 * | U+0155  | ŕ     | r           | Latin small letter r with acute                   |
 * | U+0156  | Ŗ     | R           | Latin capital letter R with cedilla               |
 * | U+0157  | ŗ     | r           | Latin small letter r with cedilla                 |
 * | U+0158  | Ř     | R           | Latin capital letter R with caron                 |
 * | U+0159  | ř     | r           | Latin small letter r with caron                   |
 * | U+015A  | Ś     | S           | Latin capital letter S with acute                 |
 * | U+015B  | ś     | s           | Latin small letter s with acute                   |
 * | U+015C  | Ŝ     | S           | Latin capital letter S with circumflex            |
 * | U+015D  | ŝ     | s           | Latin small letter s with circumflex              |
 * | U+015E  | Ş     | S           | Latin capital letter S with cedilla               |
 * | U+015F  | ş     | s           | Latin small letter s with cedilla                 |
 * | U+0160  | Š     | S           | Latin capital letter S with caron                 |
 * | U+0161  | š     | s           | Latin small letter s with caron                   |
 * | U+0162  | Ţ     | T           | Latin capital letter T with cedilla               |
 * | U+0163  | ţ     | t           | Latin small letter t with cedilla                 |
 * | U+0164  | Ť     | T           | Latin capital letter T with caron                 |
 * | U+0165  | ť     | t           | Latin small letter t with caron                   |
 * | U+0166  | Ŧ     | T           | Latin capital letter T with stroke                |
 * | U+0167  | ŧ     | t           | Latin small letter t with stroke                  |
 * | U+0168  | Ũ     | U           | Latin capital letter U with tilde                 |
 * | U+0169  | ũ     | u           | Latin small letter u with tilde                   |
 * | U+016A  | Ū     | U           | Latin capital letter U with macron                |
 * | U+016B  | ū     | u           | Latin small letter u with macron                  |
 * | U+016C  | Ŭ     | U           | Latin capital letter U with breve                 |
 * | U+016D  | ŭ     | u           | Latin small letter u with breve                   |
 * | U+016E  | Ů     | U           | Latin capital letter U with ring above            |
 * | U+016F  | ů     | u           | Latin small letter u with ring above              |
 * | U+0170  | Ű     | U           | Latin capital letter U with double acute          |
 * | U+0171  | ű     | u           | Latin small letter u with double acute            |
 * | U+0172  | Ų     | U           | Latin capital letter U with ogonek                |
 * | U+0173  | ų     | u           | Latin small letter u with ogonek                  |
 * | U+0174  | Ŵ     | W           | Latin capital letter W with circumflex            |
 * | U+0175  | ŵ     | w           | Latin small letter w with circumflex              |
 * | U+0176  | Ŷ     | Y           | Latin capital letter Y with circumflex            |
 * | U+0177  | ŷ     | y           | Latin small letter y with circumflex              |
 * | U+0178  | Ÿ     | Y           | Latin capital letter Y with diaeresis             |
 * | U+0179  | Ź     | Z           | Latin capital letter Z with acute                 |
 * | U+017A  | ź     | z           | Latin small letter z with acute                   |
 * | U+017B  | Ż     | Z           | Latin capital letter Z with dot above             |
 * | U+017C  | ż     | z           | Latin small letter z with dot above               |
 * | U+017D  | Ž     | Z           | Latin capital letter Z with caron                 |
 * | U+017E  | ž     | z           | Latin small letter z with caron                   |
 * | U+017F  | ſ     | s           | Latin small letter long s                         |
 * | U+01A0  | Ơ     | O           | Latin capital letter O with horn                  |
 * | U+01A1  | ơ     | o           | Latin small letter o with horn                    |
 * | U+01AF  | Ư     | U           | Latin capital letter U with horn                  |
 * | U+01B0  | ư     | u           | Latin small letter u with horn                    |
 * | U+01CD  | Ǎ     | A           | Latin capital letter A with caron                 |
 * | U+01CE  | ǎ     | a           | Latin small letter a with caron                   |
 * | U+01CF  | Ǐ     | I           | Latin capital letter I with caron                 |
 * | U+01D0  | ǐ     | i           | Latin small letter i with caron                   |
 * | U+01D1  | Ǒ     | O           | Latin capital letter O with caron                 |
 * | U+01D2  | ǒ     | o           | Latin small letter o with caron                   |
 * | U+01D3  | Ǔ     | U           | Latin capital letter U with caron                 |
 * | U+01D4  | ǔ     | u           | Latin small letter u with caron                   |
 * | U+01D5  | Ǖ     | U           | Latin capital letter U with diaeresis and macron  |
 * | U+01D6  | ǖ     | u           | Latin small letter u with diaeresis and macron    |
 * | U+01D7  | Ǘ     | U           | Latin capital letter U with diaeresis and acute   |
 * | U+01D8  | ǘ     | u           | Latin small letter u with diaeresis and acute     |
 * | U+01D9  | Ǚ     | U           | Latin capital letter U with diaeresis and caron   |
 * | U+01DA  | ǚ     | u           | Latin small letter u with diaeresis and caron     |
 * | U+01DB  | Ǜ     | U           | Latin capital letter U with diaeresis and grave   |
 * | U+01DC  | ǜ     | u           | Latin small letter u with diaeresis and grave     |
 *
 * Decompositions for Latin Extended-B:
 *
 * |   Code   | Glyph | Replacement |                Description                |
 * | -------- | ----- | ----------- | ----------------------------------------- |
 * | U+0218   | Ș     | S           | Latin capital letter S with comma below   |
 * | U+0219   | ș     | s           | Latin small letter s with comma below     |
 * | U+021A   | Ț     | T           | Latin capital letter T with comma below   |
 * | U+021B   | ț     | t           | Latin small letter t with comma below     |
 *
 * Vowels with diacritic (Chinese, Hanyu Pinyin):
 *
 * |   Code   | Glyph | Replacement |                      Description                      |
 * | -------- | ----- | ----------- | ----------------------------------------------------- |
 * | U+0251   | ɑ     | a           | Latin small letter alpha                              |
 * | U+1EA0   | Ạ     | A           | Latin capital letter A with dot below                 |
 * | U+1EA1   | ạ     | a           | Latin small letter a with dot below                   |
 * | U+1EA2   | Ả     | A           | Latin capital letter A with hook above                |
 * | U+1EA3   | ả     | a           | Latin small letter a with hook above                  |
 * | U+1EA4   | Ấ     | A           | Latin capital letter A with circumflex and acute      |
 * | U+1EA5   | ấ     | a           | Latin small letter a with circumflex and acute        |
 * | U+1EA6   | Ầ     | A           | Latin capital letter A with circumflex and grave      |
 * | U+1EA7   | ầ     | a           | Latin small letter a with circumflex and grave        |
 * | U+1EA8   | Ẩ     | A           | Latin capital letter A with circumflex and hook above |
 * | U+1EA9   | ẩ     | a           | Latin small letter a with circumflex and hook above   |
 * | U+1EAA   | Ẫ     | A           | Latin capital letter A with circumflex and tilde      |
 * | U+1EAB   | ẫ     | a           | Latin small letter a with circumflex and tilde        |
 * | U+1EA6   | Ậ     | A           | Latin capital letter A with circumflex and dot below  |
 * | U+1EAD   | ậ     | a           | Latin small letter a with circumflex and dot below    |
 * | U+1EAE   | Ắ     | A           | Latin capital letter A with breve and acute           |
 * | U+1EAF   | ắ     | a           | Latin small letter a with breve and acute             |
 * | U+1EB0   | Ằ     | A           | Latin capital letter A with breve and grave           |
 * | U+1EB1   | ằ     | a           | Latin small letter a with breve and grave             |
 * | U+1EB2   | Ẳ     | A           | Latin capital letter A with breve and hook above      |
 * | U+1EB3   | ẳ     | a           | Latin small letter a with breve and hook above        |
 * | U+1EB4   | Ẵ     | A           | Latin capital letter A with breve and tilde           |
 * | U+1EB5   | ẵ     | a           | Latin small letter a with breve and tilde             |
 * | U+1EB6   | Ặ     | A           | Latin capital letter A with breve and dot below       |
 * | U+1EB7   | ặ     | a           | Latin small letter a with breve and dot below         |
 * | U+1EB8   | Ẹ     | E           | Latin capital letter E with dot below                 |
 * | U+1EB9   | ẹ     | e           | Latin small letter e with dot below                   |
 * | U+1EBA   | Ẻ     | E           | Latin capital letter E with hook above                |
 * | U+1EBB   | ẻ     | e           | Latin small letter e with hook above                  |
 * | U+1EBC   | Ẽ     | E           | Latin capital letter E with tilde                     |
 * | U+1EBD   | ẽ     | e           | Latin small letter e with tilde                       |
 * | U+1EBE   | Ế     | E           | Latin capital letter E with circumflex and acute      |
 * | U+1EBF   | ế     | e           | Latin small letter e with circumflex and acute        |
 * | U+1EC0   | Ề     | E           | Latin capital letter E with circumflex and grave      |
 * | U+1EC1   | ề     | e           | Latin small letter e with circumflex and grave        |
 * | U+1EC2   | Ể     | E           | Latin capital letter E with circumflex and hook above |
 * | U+1EC3   | ể     | e           | Latin small letter e with circumflex and hook above   |
 * | U+1EC4   | Ễ     | E           | Latin capital letter E with circumflex and tilde      |
 * | U+1EC5   | ễ     | e           | Latin small letter e with circumflex and tilde        |
 * | U+1EC6   | Ệ     | E           | Latin capital letter E with circumflex and dot below  |
 * | U+1EC7   | ệ     | e           | Latin small letter e with circumflex and dot below    |
 * | U+1EC8   | Ỉ     | I           | Latin capital letter I with hook above                |
 * | U+1EC9   | ỉ     | i           | Latin small letter i with hook above                  |
 * | U+1ECA   | Ị     | I           | Latin capital letter I with dot below                 |
 * | U+1ECB   | ị     | i           | Latin small letter i with dot below                   |
 * | U+1ECC   | Ọ     | O           | Latin capital letter O with dot below                 |
 * | U+1ECD   | ọ     | o           | Latin small letter o with dot below                   |
 * | U+1ECE   | Ỏ     | O           | Latin capital letter O with hook above                |
 * | U+1ECF   | ỏ     | o           | Latin small letter o with hook above                  |
 * | U+1ED0   | Ố     | O           | Latin capital letter O with circumflex and acute      |
 * | U+1ED1   | ố     | o           | Latin small letter o with circumflex and acute        |
 * | U+1ED2   | Ồ     | O           | Latin capital letter O with circumflex and grave      |
 * | U+1ED3   | ồ     | o           | Latin small letter o with circumflex and grave        |
 * | U+1ED4   | Ổ     | O           | Latin capital letter O with circumflex and hook above |
 * | U+1ED5   | ổ     | o           | Latin small letter o with circumflex and hook above   |
 * | U+1ED6   | Ỗ     | O           | Latin capital letter O with circumflex and tilde      |
 * | U+1ED7   | ỗ     | o           | Latin small letter o with circumflex and tilde        |
 * | U+1ED8   | Ộ     | O           | Latin capital letter O with circumflex and dot below  |
 * | U+1ED9   | ộ     | o           | Latin small letter o with circumflex and dot below    |
 * | U+1EDA   | Ớ     | O           | Latin capital letter O with horn and acute            |
 * | U+1EDB   | ớ     | o           | Latin small letter o with horn and acute              |
 * | U+1EDC   | Ờ     | O           | Latin capital letter O with horn and grave            |
 * | U+1EDD   | ờ     | o           | Latin small letter o with horn and grave              |
 * | U+1EDE   | Ở     | O           | Latin capital letter O with horn and hook above       |
 * | U+1EDF   | ở     | o           | Latin small letter o with horn and hook above         |
 * | U+1EE0   | Ỡ     | O           | Latin capital letter O with horn and tilde            |
 * | U+1EE1   | ỡ     | o           | Latin small letter o with horn and tilde              |
 * | U+1EE2   | Ợ     | O           | Latin capital letter O with horn and dot below        |
 * | U+1EE3   | ợ     | o           | Latin small letter o with horn and dot below          |
 * | U+1EE4   | Ụ     | U           | Latin capital letter U with dot below                 |
 * | U+1EE5   | ụ     | u           | Latin small letter u with dot below                   |
 * | U+1EE6   | Ủ     | U           | Latin capital letter U with hook above                |
 * | U+1EE7   | ủ     | u           | Latin small letter u with hook above                  |
 * | U+1EE8   | Ứ     | U           | Latin capital letter U with horn and acute            |
 * | U+1EE9   | ứ     | u           | Latin small letter u with horn and acute              |
 * | U+1EEA   | Ừ     | U           | Latin capital letter U with horn and grave            |
 * | U+1EEB   | ừ     | u           | Latin small letter u with horn and grave              |
 * | U+1EEC   | Ử     | U           | Latin capital letter U with horn and hook above       |
 * | U+1EED   | ử     | u           | Latin small letter u with horn and hook above         |
 * | U+1EEE   | Ữ     | U           | Latin capital letter U with horn and tilde            |
 * | U+1EEF   | ữ     | u           | Latin small letter u with horn and tilde              |
 * | U+1EF0   | Ự     | U           | Latin capital letter U with horn and dot below        |
 * | U+1EF1   | ự     | u           | Latin small letter u with horn and dot below          |
 * | U+1EF2   | Ỳ     | Y           | Latin capital letter Y with grave                     |
 * | U+1EF3   | ỳ     | y           | Latin small letter y with grave                       |
 * | U+1EF4   | Ỵ     | Y           | Latin capital letter Y with dot below                 |
 * | U+1EF5   | ỵ     | y           | Latin small letter y with dot below                   |
 * | U+1EF6   | Ỷ     | Y           | Latin capital letter Y with hook above                |
 * | U+1EF7   | ỷ     | y           | Latin small letter y with hook above                  |
 * | U+1EF8   | Ỹ     | Y           | Latin capital letter Y with tilde                     |
 * | U+1EF9   | ỹ     | y           | Latin small letter y with tilde                       |
 *
 * German (`de_DE`), German formal (`de_DE_formal`), German (Switzerland) formal (`de_CH`),
 * and German (Switzerland) informal (`de_CH_informal`) locales:
 *
 * |   Code   | Glyph | Replacement |               Description               |
 * | -------- | ----- | ----------- | --------------------------------------- |
 * | U+00C4   | Ä     | Ae          | Latin capital letter A with diaeresis   |
 * | U+00E4   | ä     | ae          | Latin small letter a with diaeresis     |
 * | U+00D6   | Ö     | Oe          | Latin capital letter O with diaeresis   |
 * | U+00F6   | ö     | oe          | Latin small letter o with diaeresis     |
 * | U+00DC   | Ü     | Ue          | Latin capital letter U with diaeresis   |
 * | U+00FC   | ü     | ue          | Latin small letter u with diaeresis     |
 * | U+00DF   | ß     | ss          | Latin small letter sharp s              |
 *
 * Danish (`da_DK`) locale:
 *
 * |   Code   | Glyph | Replacement |               Description               |
 * | -------- | ----- | ----------- | --------------------------------------- |
 * | U+00C6   | Æ     | Ae          | Latin capital letter AE                 |
 * | U+00E6   | æ     | ae          | Latin small letter ae                   |
 * | U+00D8   | Ø     | Oe          | Latin capital letter O with stroke      |
 * | U+00F8   | ø     | oe          | Latin small letter o with stroke        |
 * | U+00C5   | Å     | Aa          | Latin capital letter A with ring above  |
 * | U+00E5   | å     | aa          | Latin small letter a with ring above    |
 *
 * Catalan (`ca`) locale:
 *
 * |   Code   | Glyph | Replacement |               Description               |
 * | -------- | ----- | ----------- | --------------------------------------- |
 * | U+00B7   | l·l   | ll          | Flown dot (between two Ls)              |
 *
 * Serbian (`sr_RS`) and Bosnian (`bs_BA`) locales:
 *
 * |   Code   | Glyph | Replacement |               Description               |
 * | -------- | ----- | ----------- | --------------------------------------- |
 * | U+0110   | Đ     | DJ          | Latin capital letter D with stroke      |
 * | U+0111   | đ     | dj          | Latin small letter d with stroke        |
 *
 * @since 1.2.1
 * @since 4.6.0 Added locale support for `de_CH`, `de_CH_informal`, and `ca`.
 * @since 4.7.0 Added locale support for `sr_RS`.
 * @since 4.8.0 Added locale support for `bs_BA`.
 *
 * @param string $string Text that might have accent characters
 * @return string Filtered string with replaced "nice" characters.
 */
function remove_accents( $string ) {
	if ( !preg_match('/[\x80-\xff]/', $string) )
		return $string;

	if (seems_utf8($string)) {
		$chars = array(
		// Decompositions for Latin-1 Supplement
		'ª' => 'a', 'º' => 'o',
		'À' => 'A', 'Á' => 'A',
		'Â' => 'A', 'Ã' => 'A',
		'Ä' => 'A', 'Å' => 'A',
		'Æ' => 'AE','Ç' => 'C',
		'È' => 'E', 'É' => 'E',
		'Ê' => 'E', 'Ë' => 'E',
		'Ì' => 'I', 'Í' => 'I',
		'Î' => 'I', 'Ï' => 'I',
		'Ð' => 'D', 'Ñ' => 'N',
		'Ò' => 'O', 'Ó' => 'O',
		'Ô' => 'O', 'Õ' => 'O',
		'Ö' => 'O', 'Ù' => 'U',
		'Ú' => 'U', 'Û' => 'U',
		'Ü' => 'U', 'Ý' => 'Y',
		'Þ' => 'TH','ß' => 's',
		'à' => 'a', 'á' => 'a',
		'â' => 'a', 'ã' => 'a',
		'ä' => 'a', 'å' => 'a',
		'æ' => 'ae','ç' => 'c',
		'è' => 'e', 'é' => 'e',
		'ê' => 'e', 'ë' => 'e',
		'ì' => 'i', 'í' => 'i',
		'î' => 'i', 'ï' => 'i',
		'ð' => 'd', 'ñ' => 'n',
		'ò' => 'o', 'ó' => 'o',
		'ô' => 'o', 'õ' => 'o',
		'ö' => 'o', 'ø' => 'o',
		'ù' => 'u', 'ú' => 'u',
		'û' => 'u', 'ü' => 'u',
		'ý' => 'y', 'þ' => 'th',
		'ÿ' => 'y', 'Ø' => 'O',
		// Decompositions for Latin Extended-A
		'Ā' => 'A', 'ā' => 'a',
		'Ă' => 'A', 'ă' => 'a',
		'Ą' => 'A', 'ą' => 'a',
		'Ć' => 'C', 'ć' => 'c',
		'Ĉ' => 'C', 'ĉ' => 'c',
		'Ċ' => 'C', 'ċ' => 'c',
		'Č' => 'C', 'č' => 'c',
		'Ď' => 'D', 'ď' => 'd',
		'Đ' => 'D', 'đ' => 'd',
		'Ē' => 'E', 'ē' => 'e',
		'Ĕ' => 'E', 'ĕ' => 'e',
		'Ė' => 'E', 'ė' => 'e',
		'Ę' => 'E', 'ę' => 'e',
		'Ě' => 'E', 'ě' => 'e',
		'Ĝ' => 'G', 'ĝ' => 'g',
		'Ğ' => 'G', 'ğ' => 'g',
		'Ġ' => 'G', 'ġ' => 'g',
		'Ģ' => 'G', 'ģ' => 'g',
		'Ĥ' => 'H', 'ĥ' => 'h',
		'Ħ' => 'H', 'ħ' => 'h',
		'Ĩ' => 'I', 'ĩ' => 'i',
		'Ī' => 'I', 'ī' => 'i',
		'Ĭ' => 'I', 'ĭ' => 'i',
		'Į' => 'I', 'į' => 'i',
		'İ' => 'I', 'ı' => 'i',
		'Ĳ' => 'IJ','ĳ' => 'ij',
		'Ĵ' => 'J', 'ĵ' => 'j',
		'Ķ' => 'K', 'ķ' => 'k',
		'ĸ' => 'k', 'Ĺ' => 'L',
		'ĺ' => 'l', 'Ļ' => 'L',
		'ļ' => 'l', 'Ľ' => 'L',
		'ľ' => 'l', 'Ŀ' => 'L',
		'ŀ' => 'l', 'Ł' => 'L',
		'ł' => 'l', 'Ń' => 'N',
		'ń' => 'n', 'Ņ' => 'N',
		'ņ' => 'n', 'Ň' => 'N',
		'ň' => 'n', 'ŉ' => 'n',
		'Ŋ' => 'N', 'ŋ' => 'n',
		'Ō' => 'O', 'ō' => 'o',
		'Ŏ' => 'O', 'ŏ' => 'o',
		'Ő' => 'O', 'ő' => 'o',
		'Œ' => 'OE','œ' => 'oe',
		'Ŕ' => 'R','ŕ' => 'r',
		'Ŗ' => 'R','ŗ' => 'r',
		'Ř' => 'R','ř' => 'r',
		'Ś' => 'S','ś' => 's',
		'Ŝ' => 'S','ŝ' => 's',
		'Ş' => 'S','ş' => 's',
		'Š' => 'S', 'š' => 's',
		'Ţ' => 'T', 'ţ' => 't',
		'Ť' => 'T', 'ť' => 't',
		'Ŧ' => 'T', 'ŧ' => 't',
		'Ũ' => 'U', 'ũ' => 'u',
		'Ū' => 'U', 'ū' => 'u',
		'Ŭ' => 'U', 'ŭ' => 'u',
		'Ů' => 'U', 'ů' => 'u',
		'Ű' => 'U', 'ű' => 'u',
		'Ų' => 'U', 'ų' => 'u',
		'Ŵ' => 'W', 'ŵ' => 'w',
		'Ŷ' => 'Y', 'ŷ' => 'y',
		'Ÿ' => 'Y', 'Ź' => 'Z',
		'ź' => 'z', 'Ż' => 'Z',
		'ż' => 'z', 'Ž' => 'Z',
		'ž' => 'z', 'ſ' => 's',
		// Decompositions for Latin Extended-B
		'Ș' => 'S', 'ș' => 's',
		'Ț' => 'T', 'ț' => 't',
		// Euro Sign
		'€' => 'E',
		// GBP (Pound) Sign
		'£' => '',
		// Vowels with diacritic (Vietnamese)
		// unmarked
		'Ơ' => 'O', 'ơ' => 'o',
		'Ư' => 'U', 'ư' => 'u',
		// grave accent
		'Ầ' => 'A', 'ầ' => 'a',
		'Ằ' => 'A', 'ằ' => 'a',
		'Ề' => 'E', 'ề' => 'e',
		'Ồ' => 'O', 'ồ' => 'o',
		'Ờ' => 'O', 'ờ' => 'o',
		'Ừ' => 'U', 'ừ' => 'u',
		'Ỳ' => 'Y', 'ỳ' => 'y',
		// hook
		'Ả' => 'A', 'ả' => 'a',
		'Ẩ' => 'A', 'ẩ' => 'a',
		'Ẳ' => 'A', 'ẳ' => 'a',
		'Ẻ' => 'E', 'ẻ' => 'e',
		'Ể' => 'E', 'ể' => 'e',
		'Ỉ' => 'I', 'ỉ' => 'i',
		'Ỏ' => 'O', 'ỏ' => 'o',
		'Ổ' => 'O', 'ổ' => 'o',
		'Ở' => 'O', 'ở' => 'o',
		'Ủ' => 'U', 'ủ' => 'u',
		'Ử' => 'U', 'ử' => 'u',
		'Ỷ' => 'Y', 'ỷ' => 'y',
		// tilde
		'Ẫ' => 'A', 'ẫ' => 'a',
		'Ẵ' => 'A', 'ẵ' => 'a',
		'Ẽ' => 'E', 'ẽ' => 'e',
		'Ễ' => 'E', 'ễ' => 'e',
		'Ỗ' => 'O', 'ỗ' => 'o',
		'Ỡ' => 'O', 'ỡ' => 'o',
		'Ữ' => 'U', 'ữ' => 'u',
		'Ỹ' => 'Y', 'ỹ' => 'y',
		// acute accent
		'Ấ' => 'A', 'ấ' => 'a',
		'Ắ' => 'A', 'ắ' => 'a',
		'Ế' => 'E', 'ế' => 'e',
		'Ố' => 'O', 'ố' => 'o',
		'Ớ' => 'O', 'ớ' => 'o',
		'Ứ' => 'U', 'ứ' => 'u',
		// dot below
		'Ạ' => 'A', 'ạ' => 'a',
		'Ậ' => 'A', 'ậ' => 'a',
		'Ặ' => 'A', 'ặ' => 'a',
		'Ẹ' => 'E', 'ẹ' => 'e',
		'Ệ' => 'E', 'ệ' => 'e',
		'Ị' => 'I', 'ị' => 'i',
		'Ọ' => 'O', 'ọ' => 'o',
		'Ộ' => 'O', 'ộ' => 'o',
		'Ợ' => 'O', 'ợ' => 'o',
		'Ụ' => 'U', 'ụ' => 'u',
		'Ự' => 'U', 'ự' => 'u',
		'Ỵ' => 'Y', 'ỵ' => 'y',
		// Vowels with diacritic (Chinese, Hanyu Pinyin)
		'ɑ' => 'a',
		// macron
		'Ǖ' => 'U', 'ǖ' => 'u',
		// acute accent
		'Ǘ' => 'U', 'ǘ' => 'u',
		// caron
		'Ǎ' => 'A', 'ǎ' => 'a',
		'Ǐ' => 'I', 'ǐ' => 'i',
		'Ǒ' => 'O', 'ǒ' => 'o',
		'Ǔ' => 'U', 'ǔ' => 'u',
		'Ǚ' => 'U', 'ǚ' => 'u',
		// grave accent
		'Ǜ' => 'U', 'ǜ' => 'u',
		);

		// Used for locale-specific rules
		$locale = get_locale();

		if ( 'de_DE' == $locale || 'de_DE_formal' == $locale || 'de_CH' == $locale || 'de_CH_informal' == $locale ) {
			$chars[ 'Ä' ] = 'Ae';
			$chars[ 'ä' ] = 'ae';
			$chars[ 'Ö' ] = 'Oe';
			$chars[ 'ö' ] = 'oe';
			$chars[ 'Ü' ] = 'Ue';
			$chars[ 'ü' ] = 'ue';
			$chars[ 'ß' ] = 'ss';
		} elseif ( 'da_DK' === $locale ) {
			$chars[ 'Æ' ] = 'Ae';
 			$chars[ 'æ' ] = 'ae';
			$chars[ 'Ø' ] = 'Oe';
			$chars[ 'ø' ] = 'oe';
			$chars[ 'Å' ] = 'Aa';
			$chars[ 'å' ] = 'aa';
		} elseif ( 'ca' === $locale ) {
			$chars[ 'l·l' ] = 'll';
		} elseif ( 'sr_RS' === $locale || 'bs_BA' === $locale ) {
			$chars[ 'Đ' ] = 'DJ';
			$chars[ 'đ' ] = 'dj';
		}

		$string = strtr($string, $chars);
	} else {
		$chars = array();
		// Assume ISO-8859-1 if not UTF-8
		$chars['in'] = "\x80\x83\x8a\x8e\x9a\x9e"
			."\x9f\xa2\xa5\xb5\xc0\xc1\xc2"
			."\xc3\xc4\xc5\xc7\xc8\xc9\xca"
			."\xcb\xcc\xcd\xce\xcf\xd1\xd2"
			."\xd3\xd4\xd5\xd6\xd8\xd9\xda"
			."\xdb\xdc\xdd\xe0\xe1\xe2\xe3"
			."\xe4\xe5\xe7\xe8\xe9\xea\xeb"
			."\xec\xed\xee\xef\xf1\xf2\xf3"
			."\xf4\xf5\xf6\xf8\xf9\xfa\xfb"
			."\xfc\xfd\xff";

		$chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

		$string = strtr($string, $chars['in'], $chars['out']);
		$double_chars = array();
		$double_chars['in'] = array("\x8c", "\x9c", "\xc6", "\xd0", "\xde", "\xdf", "\xe6", "\xf0", "\xfe");
		$double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
		$string = str_replace($double_chars['in'], $double_chars['out'], $string);
	}

	return $string;
}

/**
 * Sanitizes a filename, replacing whitespace with dashes.
 *
 * Removes special characters that are illegal in filenames on certain
 * operating systems and special characters requiring special escaping
 * to manipulate at the command line. Replaces spaces and consecutive
 * dashes with a single dash. Trims period, dash and underscore from beginning
 * and end of filename. It is not guaranteed that this function will return a
 * filename that is allowed to be uploaded.
 *
 * @since 2.1.0
 *
 * @param string $filename The filename to be sanitized
 * @return string The sanitized filename
 */
function sanitize_file_name( $filename ) {
	$filename_raw = $filename;
	$special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", "%", "+", chr(0));
	/**
	 * Filters the list of characters to remove from a filename.
	 *
	 * @since 2.8.0
	 *
	 * @param array  $special_chars Characters to remove.
	 * @param string $filename_raw  Filename as it was passed into sanitize_file_name().
	 */
	$special_chars = apply_filters( 'sanitize_file_name_chars', $special_chars, $filename_raw );
	$filename = preg_replace( "#\x{00a0}#siu", ' ', $filename );
	$filename = str_replace( $special_chars, '', $filename );
	$filename = str_replace( array( '%20', '+' ), '-', $filename );
	$filename = preg_replace( '/[\r\n\t -]+/', '-', $filename );
	$filename = trim( $filename, '.-_' );

	if ( false === strpos( $filename, '.' ) ) {
		$mime_types = wp_get_mime_types();
		$filetype = wp_check_filetype( 'test.' . $filename, $mime_types );
		if ( $filetype['ext'] === $filename ) {
			$filename = 'unnamed-file.' . $filetype['ext'];
		}
	}

	// Split the filename into a base and extension[s]
	$parts = explode('.', $filename);

	// Return if only one extension
	if ( count( $parts ) <= 2 ) {
		/**
		 * Filters a sanitized filename string.
		 *
		 * @since 2.8.0
		 *
		 * @param string $filename     Sanitized filename.
		 * @param string $filename_raw The filename prior to sanitization.
		 */
		return apply_filters( 'sanitize_file_name', $filename, $filename_raw );
	}

	// Process multiple extensions
	$filename = array_shift($parts);
	$extension = array_pop($parts);
	$mimes = get_allowed_mime_types();

	/*
	 * Loop over any intermediate extensions. Postfix them with a trailing underscore
	 * if they are a 2 - 5 character long alpha string not in the extension whitelist.
	 */
	foreach ( (array) $parts as $part) {
		$filename .= '.' . $part;

		if ( preg_match("/^[a-zA-Z]{2,5}\d?$/", $part) ) {
			$allowed = false;
			foreach ( $mimes as $ext_preg => $mime_match ) {
				$ext_preg = '!^(' . $ext_preg . ')$!i';
				if ( preg_match( $ext_preg, $part ) ) {
					$allowed = true;
					break;
				}
			}
			if ( !$allowed )
				$filename .= '_';
		}
	}
	$filename .= '.' . $extension;
	/** This filter is documented in wp-includes/formatting.php */
	return apply_filters('sanitize_file_name', $filename, $filename_raw);
}

/**
 * Sanitizes a username, stripping out unsafe characters.
 *
 * Removes tags, octets, entities, and if strict is enabled, will only keep
 * alphanumeric, _, space, ., -, @. After sanitizing, it passes the username,
 * raw username (the username in the parameter), and the value of $strict as
 * parameters for the {@see 'sanitize_user'} filter.
 *
 * @since 2.0.0
 *
 * @param string $username The username to be sanitized.
 * @param bool   $strict   If set limits $username to specific characters. Default false.
 * @return string The sanitized username, after passing through filters.
 */
function sanitize_user( $username, $strict = false ) {
	$raw_username = $username;
	$username = wp_strip_all_tags( $username );
	$username = remove_accents( $username );
	// Kill octets
	$username = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '', $username );
	$username = preg_replace( '/&.+?;/', '', $username ); // Kill entities

	// If strict, reduce to ASCII for max portability.
	if ( $strict )
		$username = preg_replace( '|[^a-z0-9 _.\-@]|i', '', $username );

	$username = trim( $username );
	// Consolidate contiguous whitespace
	$username = preg_replace( '|\s+|', ' ', $username );

	/**
	 * Filters a sanitized username string.
	 *
	 * @since 2.0.1
	 *
	 * @param string $username     Sanitized username.
	 * @param string $raw_username The username prior to sanitization.
	 * @param bool   $strict       Whether to limit the sanitization to specific characters. Default false.
	 */
	return apply_filters( 'sanitize_user', $username, $raw_username, $strict );
}

/**
 * Sanitizes a string key.
 *
 * Keys are used as internal identifiers. Lowercase alphanumeric characters, dashes and underscores are allowed.
 *
 * @since 3.0.0
 *
 * @param string $key String key
 * @return string Sanitized key
 */
function sanitize_key( $key ) {
	$raw_key = $key;
	$key = strtolower( $key );
	$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

	/**
	 * Filters a sanitized key string.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key     Sanitized key.
	 * @param string $raw_key The key prior to sanitization.
	 */
	return apply_filters( 'sanitize_key', $key, $raw_key );
}

/**
 * Sanitizes a title, or returns a fallback title.
 *
 * Specifically, HTML and PHP tags are stripped. Further actions can be added
 * via the plugin API. If $title is empty and $fallback_title is set, the latter
 * will be used.
 *
 * @since 1.0.0
 *
 * @param string $title          The string to be sanitized.
 * @param string $fallback_title Optional. A title to use if $title is empty.
 * @param string $context        Optional. The operation for which the string is sanitized
 * @return string The sanitized string.
 */
function sanitize_title( $title, $fallback_title = '', $context = 'save' ) {
	$raw_title = $title;

	if ( 'save' == $context )
		$title = remove_accents($title);

	/**
	 * Filters a sanitized title string.
	 *
	 * @since 1.2.0
	 *
	 * @param string $title     Sanitized title.
	 * @param string $raw_title The title prior to sanitization.
	 * @param string $context   The context for which the title is being sanitized.
	 */
	$title = apply_filters( 'sanitize_title', $title, $raw_title, $context );

	if ( '' === $title || false === $title )
		$title = $fallback_title;

	return $title;
}

/**
 * Sanitizes a title with the 'query' context.
 *
 * Used for querying the database for a value from URL.
 *
 * @since 3.1.0
 *
 * @param string $title The string to be sanitized.
 * @return string The sanitized string.
 */
function sanitize_title_for_query( $title ) {
	return sanitize_title( $title, '', 'query' );
}

/**
 * Sanitizes a title, replacing whitespace and a few other characters with dashes.
 *
 * Limits the output to alphanumeric characters, underscore (_) and dash (-).
 * Whitespace becomes a dash.
 *
 * @since 1.2.0
 *
 * @param string $title     The title to be sanitized.
 * @param string $raw_title Optional. Not used.
 * @param string $context   Optional. The operation for which the string is sanitized.
 * @return string The sanitized title.
 */
function sanitize_title_with_dashes( $title, $raw_title = '', $context = 'display' ) {
	$title = strip_tags($title);
	// Preserve escaped octets.
	$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
	// Remove percent signs that are not part of an octet.
	$title = str_replace('%', '', $title);
	// Restore octets.
	$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

	if (seems_utf8($title)) {
		if (function_exists('mb_strtolower')) {
			$title = mb_strtolower($title, 'UTF-8');
		}
		$title = utf8_uri_encode($title, 200);
	}

	$title = strtolower($title);

	if ( 'save' == $context ) {
		// Convert nbsp, ndash and mdash to hyphens
		$title = str_replace( array( '%c2%a0', '%e2%80%93', '%e2%80%94' ), '-', $title );
		// Convert nbsp, ndash and mdash HTML entities to hyphens
		$title = str_replace( array( '&nbsp;', '&#160;', '&ndash;', '&#8211;', '&mdash;', '&#8212;' ), '-', $title );
		// Convert forward slash to hyphen
		$title = str_replace( '/', '-', $title );

		// Strip these characters entirely
		$title = str_replace( array(
			// iexcl and iquest
			'%c2%a1', '%c2%bf',
			// angle quotes
			'%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba',
			// curly quotes
			'%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d',
			'%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f',
			// copy, reg, deg, hellip and trade
			'%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2',
			// acute accents
			'%c2%b4', '%cb%8a', '%cc%81', '%cd%81',
			// grave accent, macron, caron
			'%cc%80', '%cc%84', '%cc%8c',
		), '', $title );

		// Convert times to x
		$title = str_replace( '%c3%97', 'x', $title );
	}

	$title = preg_replace('/&.+?;/', '', $title); // kill entities
	$title = str_replace('.', '-', $title);

	$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
	$title = preg_replace('/\s+/', '-', $title);
	$title = preg_replace('|-+|', '-', $title);
	$title = trim($title, '-');

	return $title;
}

/**
 * Ensures a string is a valid SQL 'order by' clause.
 *
 * Accepts one or more columns, with or without a sort order (ASC / DESC).
 * e.g. 'column_1', 'column_1, column_2', 'column_1 ASC, column_2 DESC' etc.
 *
 * Also accepts 'RAND()'.
 *
 * @since 2.5.1
 *
 * @param string $orderby Order by clause to be validated.
 * @return string|false Returns $orderby if valid, false otherwise.
 */
function sanitize_sql_orderby( $orderby ) {
	if ( preg_match( '/^\s*(([a-z0-9_]+|`[a-z0-9_]+`)(\s+(ASC|DESC))?\s*(,\s*(?=[a-z0-9_`])|$))+$/i', $orderby ) || preg_match( '/^\s*RAND\(\s*\)\s*$/i', $orderby ) ) {
		return $orderby;
	}
	return false;
}

/**
 * Sanitizes an HTML classname to ensure it only contains valid characters.
 *
 * Strips the string down to A-Z,a-z,0-9,_,-. If this results in an empty
 * string then it will return the alternative value supplied.
 *
 * @todo Expand to support the full range of CDATA that a class attribute can contain.
 *
 * @since 2.8.0
 *
 * @param string $class    The classname to be sanitized
 * @param string $fallback Optional. The value to return if the sanitization ends up as an empty string.
 * 	Defaults to an empty string.
 * @return string The sanitized value
 */
function sanitize_html_class( $class, $fallback = '' ) {
	//Strip out any % encoded octets
	$sanitized = preg_replace( '|%[a-fA-F0-9][a-fA-F0-9]|', '', $class );

	//Limit to A-Z,a-z,0-9,_,-
	$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', $sanitized );

	if ( '' == $sanitized && $fallback ) {
		return sanitize_html_class( $fallback );
	}
	/**
	 * Filters a sanitized HTML class string.
	 *
	 * @since 2.8.0
	 *
	 * @param string $sanitized The sanitized HTML class.
	 * @param string $class     HTML class before sanitization.
	 * @param string $fallback  The fallback string.
	 */
	return apply_filters( 'sanitize_html_class', $sanitized, $class, $fallback );
}

/**
 * Converts lone & characters into `&#038;` (a.k.a. `&amp;`)
 *
 * @since 0.71
 *
 * @param string $content    String of characters to be converted.
 * @param string $deprecated Not used.
 * @return string Converted string.
 */
function convert_chars( $content, $deprecated = '' ) {
	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '0.71' );
	}

	if ( strpos( $content, '&' ) !== false ) {
		$content = preg_replace( '/&([^#])(?![a-z1-4]{1,8};)/i', '&#038;$1', $content );
	}

	return $content;
}

/**
 * Converts invalid Unicode references range to valid range.
 *
 * @since 4.3.0
 *
 * @param string $content String with entities that need converting.
 * @return string Converted string.
 */
function convert_invalid_entities( $content ) {
	$wp_htmltranswinuni = array(
		'&#128;' => '&#8364;', // the Euro sign
		'&#129;' => '',
		'&#130;' => '&#8218;', // these are Windows CP1252 specific characters
		'&#131;' => '&#402;',  // they would look weird on non-Windows browsers
		'&#132;' => '&#8222;',
		'&#133;' => '&#8230;',
		'&#134;' => '&#8224;',
		'&#135;' => '&#8225;',
		'&#136;' => '&#710;',
		'&#137;' => '&#8240;',
		'&#138;' => '&#352;',
		'&#139;' => '&#8249;',
		'&#140;' => '&#338;',
		'&#141;' => '',
		'&#142;' => '&#381;',
		'&#143;' => '',
		'&#144;' => '',
		'&#145;' => '&#8216;',
		'&#146;' => '&#8217;',
		'&#147;' => '&#8220;',
		'&#148;' => '&#8221;',
		'&#149;' => '&#8226;',
		'&#150;' => '&#8211;',
		'&#151;' => '&#8212;',
		'&#152;' => '&#732;',
		'&#153;' => '&#8482;',
		'&#154;' => '&#353;',
		'&#155;' => '&#8250;',
		'&#156;' => '&#339;',
		'&#157;' => '',
		'&#158;' => '&#382;',
		'&#159;' => '&#376;'
	);

	if ( strpos( $content, '&#1' ) !== false ) {
		$content = strtr( $content, $wp_htmltranswinuni );
	}

	return $content;
}

/**
 * Balances tags if forced to, or if the 'use_balanceTags' option is set to true.
 *
 * @since 0.71
 *
 * @param string $text  Text to be balanced
 * @param bool   $force If true, forces balancing, ignoring the value of the option. Default false.
 * @return string Balanced text
 */
function balanceTags( $text, $force = false ) {
	if ( $force || get_option('use_balanceTags') == 1 ) {
		return force_balance_tags( $text );
	} else {
		return $text;
	}
}

/**
 * Balances tags of string using a modified stack.
 *
 * @since 2.0.4
 *
 * @author Leonard Lin <leonard@acm.org>
 * @license GPL
 * @copyright November 4, 2001
 * @version 1.1
 * @todo Make better - change loop condition to $text in 1.2
 * @internal Modified by Scott Reilly (coffee2code) 02 Aug 2004
 *		1.1  Fixed handling of append/stack pop order of end text
 *			 Added Cleaning Hooks
 *		1.0  First Version
 *
 * @param string $text Text to be balanced.
 * @return string Balanced text.
 */
function force_balance_tags( $text ) {
	$tagstack = array();
	$stacksize = 0;
	$tagqueue = '';
	$newtext = '';
	// Known single-entity/self-closing tags
	$single_tags = array( 'area', 'base', 'basefont', 'br', 'col', 'command', 'embed', 'frame', 'hr', 'img', 'input', 'isindex', 'link', 'meta', 'param', 'source' );
	// Tags that can be immediately nested within themselves
	$nestable_tags = array( 'blockquote', 'div', 'object', 'q', 'span' );

	// WP bug fix for comments - in case you REALLY meant to type '< !--'
	$text = str_replace('< !--', '<    !--', $text);
	// WP bug fix for LOVE <3 (and other situations with '<' before a number)
	$text = preg_replace('#<([0-9]{1})#', '&lt;$1', $text);

	while ( preg_match("/<(\/?[\w:]*)\s*([^>]*)>/", $text, $regex) ) {
		$newtext .= $tagqueue;

		$i = strpos($text, $regex[0]);
		$l = strlen($regex[0]);

		// clear the shifter
		$tagqueue = '';
		// Pop or Push
		if ( isset($regex[1][0]) && '/' == $regex[1][0] ) { // En