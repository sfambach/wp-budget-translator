<?php
/**
 * Simple word-level source diff for review UI.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Admin;

/**
 * Class SourceDiff
 */
final class SourceDiff {

	/**
	 * Render HTML highlighting removals/additions between previous and current source.
	 *
	 * @param string $previous Previous source.
	 * @param string $current  Current source.
	 */
	public static function render( string $previous, string $current ): string {
		if ( '' === $previous || $previous === $current ) {
			return '';
		}

		$old = preg_split( '/(\s+)/u', $previous, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: array( $previous );
		$new = preg_split( '/(\s+)/u', $current, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: array( $current );

		$matrix = array();
		$m      = count( $old );
		$n      = count( $new );
		for ( $i = 0; $i <= $m; $i++ ) {
			$matrix[ $i ][0] = 0;
		}
		for ( $j = 0; $j <= $n; $j++ ) {
			$matrix[0][ $j ] = 0;
		}
		for ( $i = 1; $i <= $m; $i++ ) {
			for ( $j = 1; $j <= $n; $j++ ) {
				if ( $old[ $i - 1 ] === $new[ $j - 1 ] ) {
					$matrix[ $i ][ $j ] = $matrix[ $i - 1 ][ $j - 1 ] + 1;
				} else {
					$matrix[ $i ][ $j ] = max( $matrix[ $i - 1 ][ $j ], $matrix[ $i ][ $j - 1 ] );
				}
			}
		}

		$html = '';
		$i    = $m;
		$j    = $n;
		$stack = array();
		while ( $i > 0 && $j > 0 ) {
			if ( $old[ $i - 1 ] === $new[ $j - 1 ] ) {
				array_unshift( $stack, array( 'eq', $old[ $i - 1 ] ) );
				--$i;
				--$j;
			} elseif ( $matrix[ $i - 1 ][ $j ] >= $matrix[ $i ][ $j - 1 ] ) {
				array_unshift( $stack, array( 'del', $old[ $i - 1 ] ) );
				--$i;
			} else {
				array_unshift( $stack, array( 'ins', $new[ $j - 1 ] ) );
				--$j;
			}
		}
		while ( $i > 0 ) {
			array_unshift( $stack, array( 'del', $old[ $i - 1 ] ) );
			--$i;
		}
		while ( $j > 0 ) {
			array_unshift( $stack, array( 'ins', $new[ $j - 1 ] ) );
			--$j;
		}

		foreach ( $stack as $part ) {
			[ $type, $token ] = $part;
			$esc = esc_html( $token );
			if ( 'del' === $type ) {
				$html .= '<del class="bt-diff-del">' . $esc . '</del>';
			} elseif ( 'ins' === $type ) {
				$html .= '<ins class="bt-diff-ins">' . $esc . '</ins>';
			} else {
				$html .= $esc;
			}
		}

		return $html;
	}
}
