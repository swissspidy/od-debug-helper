<?php
/**
 * Optimization_Detective_Debug_Helper_Tag_Visitor class
 *
 * @package od-debug-helper
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag visitor for the Optimization Detective debug helper.
 *
 * @phpstan-import-type LinkAttributes from OD_Link_Collection
 *
 * @since 0.1.0
 * @access private
 */
final class Optimization_Detective_Debug_Helper_Tag_Visitor {

	/**
	 * Visits a tag.
	 *
	 * This tag visitor doesn't itself request elements to be tracked in URL Metrics, but will reuse tracking that other tag visitors have opted-in to.
	 *
	 * @since 0.1.0
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return false Always returns false.
	 */
	public function __invoke( OD_Tag_Visitor_Context $context ): bool {
		$processor = $context->processor;

		if ( ! $context->url_metric_group_collection->is_any_group_populated() ) {
			return false;
		}

		$xpath = $processor->get_xpath();

		foreach ( $context->url_metric_group_collection as $group ) {
			// This is the LCP element for this group.
			if ( $group->get_lcp_element() instanceof OD_Element && $xpath === $group->get_lcp_element()->get_xpath() ) {
				$anchor_text  = __( 'Optimization Detective', 'od-debug-helper' );
				$popover_text = __( 'LCP Element', 'od-debug-helper' );

				$this->add_dot( $processor, $anchor_text, $popover_text );
			}

			// Annotate INP elements.
			$inp_dots = array();

			foreach ( $group as $url_metric ) {
				$inp_data_set = $url_metric->get( 'inpData' );
				if ( ! is_array( $inp_data_set ) ) {
					continue;
				}

				foreach ( $inp_data_set as $inp_data ) {
					if ( $xpath !== $inp_data['xpath'] ) {
						continue;
					}

					if ( isset( $inp_dots[ $inp_data['xpath'] ] ) ) {
						$inp_dots[ $inp_data['xpath'] ][] = array(
						  'value'  => $inp_data['value'],
						  'rating' => $inp_data['rating'],
						);
					} else {
						$inp_dots[ $inp_data['xpath'] ] = array(
						  array(
							'value'  => $inp_data['value'],
							'rating' => $inp_data['rating'],
						  ),
						);
					}
				}

				if ( array() !== $inp_dots ) {
					foreach ( $inp_dots as $xpath => $data ) {
						// TODO: List all ratings, not just the first one.
						$anchor_text  = __( 'Optimization Detective', 'od-debug-helper' );
						$popover_text =
						  sprintf(
							/* translators: 1: INP value. 2: Rating. */
							__( 'INP Element: (Value: %1$s) (Rating: %2$s)', 'od-debug-helper' ),
							$data[0]['value'],
							$data[0]['rating']
						  );

						$this->add_dot( $processor, $anchor_text, $popover_text );
					}
				}
			}
		}

		return false;
	}

	/**
	 * Adds a debug dot for the element
	 *
	 * @since 0.1.0
	 *
	 * @phpstan-param NormalizedAttributeNames $attribute_name
	 *
	 * @param OD_HTML_Tag_Processor $processor      Processor.
	 */
	protected function add_dot( OD_HTML_Tag_Processor $processor, $anchor_text, $popover_text ) {
		$uuid = wp_generate_uuid4();

		$anchor_name = $this->get_anchor_name( $processor );

		if ( ! $anchor_name ) {
			$anchor_name = "--od-debug-element-$uuid";
			$style = $processor->get_attribute( 'style' );
			$style = is_string( $style ) ? $style : '';
			$processor->set_attribute(
			  'style',
			  "anchor-name: $anchor_name;" . $style
			);
		}

		$processor->append_body_html(
		  <<<HTML
<button
	class="od-debug-dot inp"
	type="button"
	popovertarget="od-debug-popover-$uuid"
	popovertargetaction="toggle"
	style="anchor-name: --od-debug-dot-$uuid; position-anchor: --od-debug-element-$uuid;"
	aria-details="od-debug-popover-$uuid"
	aria-label="$anchor_text"
	>
</button>
<div
	id="od-debug-popover-$uuid"
	popover
	class="od-debug-popover"
	style="position-anchor: --od-debug-dot-$uuid;"
	>
	$popover_text
</div>
HTML
		);
	}

	/**
	 * Get anchor name for element.
	 *
	 * @since 0.1.0
	 *
	 * @phpstan-param NormalizedAttributeNames $attribute_name
	 *
	 * @param OD_HTML_Tag_Processor $processor      Processor.
	 * @param string                $attribute_name Attribute name.
	 * @return string|null Normalized attribute value.
	 */
	protected function get_anchor_name( OD_HTML_Tag_Processor $processor ) {
		$style = $processor->get_attribute( 'style' );
		$style = is_string( $style ) ? $style : '';

		$matches = array();

		if ( preg_match( '/anchor-name:\s?([^;]+)/', $style, $matches ) ) {
			if ( isset( $matches[1] ) ) {
				return $matches[1];
			}
		}

		return null;
	}
}
