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
				$uuid = wp_generate_uuid4();

				$processor->set_meta_attribute(
					'viewport',
					(string) $group->get_minimum_viewport_width()
				);

				$style = $processor->get_attribute( 'style' );
				$style = is_string( $style ) ? $style : '';
				$processor->set_attribute(
					'style',
					"anchor-name: --od-debug-element-$uuid;" . $style
				);

				$processor->set_meta_attribute(
					'debug-is-lcp',
					true
				);

				$anchor_text  = __( 'Optimization Detective', 'optimization-detective' );
				$popover_text = __( 'LCP Element', 'optimization-detective' );

				$processor->append_body_html(
					<<<HTML
<button
	class="od-debug-dot od-debug-dot-lcp"
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
		}

		// Add JavaScript to annotate all INP elements.
		if ( $processor->get_tag() === 'BODY' ) {
			foreach ( $context->url_metric_group_collection as $group ) {
				$inp_dots = array();

				foreach ( $group as $url_metric ) {
					$inp_data_set = $url_metric->get( 'inpData' );
					if ( ! is_array( $inp_data_set ) ) {
						continue;
					}

					foreach ( $inp_data_set as $inp_data ) {
						if ( isset( $inp_dots[ $inp_data['interactionTarget'] ] ) ) {
							$inp_dots[ $inp_data['interactionTarget'] ][] = array(
								'value'  => $inp_data['value'],
								'rating' => $inp_data['rating'],
							);
						} else {
							$inp_dots[ $inp_data['interactionTarget'] ] = array(
								array(
									'value'  => $inp_data['value'],
									'rating' => $inp_data['rating'],
								),
							);
						}
					}
				}

				$inp_dots_json = wp_json_encode( $inp_dots );

				// TODO: Maybe only add the $inp_dots_json here and move the rest to an external script.
				if ( array() !== ( $inp_dots ) ) {
					$processor->append_body_html(
						<<<HTML
	<script>
		let count = 0;
		for ( const [ interactionTarget, entries ] of Object.entries( $inp_dots_json ) ) {
			const el = document.querySelector( interactionTarget );
			if ( ! el ) {
				continue;
			}

			count++;

			let anchorName = el.style.anchorName;

			if ( ! anchorName ) {
				anchorName = `--od-debug-element-\${count}`;
				el.style.anchorName = anchorName;
			}

			const anchor = document.createElement( 'button' );
			anchor.setAttribute( 'class', 'od-debug-dot od-debug-dot-inp' );
			anchor.setAttribute( 'popovertarget', `od-debug-popover-\${count}` );
			anchor.setAttribute( 'popovertargetaction', 'toggle' );
			anchor.setAttribute( 'style', `anchor-name: --od-debug-dot-\${count}; position-anchor: \${anchorName};` );
			anchor.setAttribute( 'aria-details', `od-debug-popover-\${count}` );
			anchor.setAttribute( 'aria-label', 'INP element' );

			const tooltip = document.createElement( 'div' );
			tooltip.setAttribute( 'id', `od-debug-popover-\${count}` );
			tooltip.setAttribute( 'popover', '' );
			tooltip.setAttribute( 'class', 'od-debug-popover' );
			tooltip.setAttribute( 'style', `position-anchor: --od-debug-dot-\${count};` );
			tooltip.textContent = `INP Element (Value: \${entries[0].value}) (Rating: \${entries[0].rating}) (Tag name: \${el.tagName})`;

			document.body.append(anchor);
			document.body.append(tooltip);
		}
	</script>
HTML
					);
				}
			}
		}

		return false;
	}
}
