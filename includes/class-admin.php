<?php
/**
 * Admin screen: WooCommerce → Kirki Templates.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI for template rules, designs and settings.
 */
final class Admin {

	const SLUG = 'kirki-woo';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_kirki_woo_save_rule', array( __CLASS__, 'handle_save_rule' ) );
		add_action( 'admin_post_kirki_woo_delete_rule', array( __CLASS__, 'handle_delete_rule' ) );
		add_action( 'admin_post_kirki_woo_create_design', array( __CLASS__, 'handle_create_design' ) );
		add_action( 'admin_post_kirki_woo_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KIRKI_WOO_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Kirki Templates', 'kirki-woocommerce-bridge' ),
			__( 'Kirki Templates', 'kirki-woocommerce-bridge' ),
			Api::CAP_MANAGE,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Plugin row links.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Templates', 'kirki-woocommerce-bridge' ) . '</a>' );
		return $links;
	}

	/**
	 * Screen URL.
	 *
	 * @param array<string,string> $args Query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Guard for handlers.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( Api::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'kirki-woocommerce-bridge' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirect back with a notice.
	 *
	 * @param string $type    success|error.
	 * @param string $message Message.
	 * @return void
	 */
	private static function back( $type, $message ) {
		set_transient( 'kirki_woo_notice_' . get_current_user_id(), array( $type, $message ), 60 );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Parse conditions from the form.
	 *
	 * @param string $context Context.
	 * @return array<string,mixed>
	 */
	private static function posted_conditions( $context ) {
		$contexts = Templates::contexts();
		$allowed  = isset( $contexts[ $context ] ) ? $contexts[ $context ]['conditions'] : array();
		$schema   = Templates::condition_schema();
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in guard(); values sanitized per type below and in Templates::validate().
		$raw = isset( $_POST['conditions'] ) && is_array( $_POST['conditions'] ) ? wp_unslash( $_POST['conditions'] ) : array();
		// phpcs:enable
		$out = array();
		foreach ( $allowed as $key ) {
			if ( ! isset( $raw[ $key ] ) ) {
				continue;
			}
			$value = $raw[ $key ];
			$type  = $schema[ $key ]['type'];
			if ( 'integer[]' === $type ) {
				$list = Product_Query::csv_ints( is_array( $value ) ? implode( ',', $value ) : (string) $value );
				if ( $list ) {
					$out[ $key ] = $list;
				}
			} elseif ( 'string[]' === $type ) {
				$list = is_array( $value ) ? array_map( 'sanitize_key', $value ) : Product_Query::csv_slugs( (string) $value );
				$list = array_values( array_filter( $list, 'strlen' ) );
				if ( $list ) {
					$out[ $key ] = $list;
				}
			} elseif ( 'boolean' === $type ) {
				$value = sanitize_key( (string) $value );
				if ( 'yes' === $value || 'no' === $value ) {
					$out[ $key ] = 'yes' === $value;
				}
			} else {
				$value = sanitize_key( (string) $value );
				if ( '' !== $value && 'any' !== $value ) {
					$out[ $key ] = $value;
				}
			}
		}
		return $out;
	}

	/**
	 * Save (create/update) a rule.
	 *
	 * @return void
	 */
	public static function handle_save_rule() {
		self::guard( 'kirki_woo_save_rule' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$id      = isset( $_POST['rule_id'] ) ? sanitize_key( wp_unslash( $_POST['rule_id'] ) ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';
		$input   = array(
			'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'context'    => $context,
			'design_id'  => isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0,
			'priority'   => isset( $_POST['priority'] ) ? (int) $_POST['priority'] : 10,
			'enabled'    => ! empty( $_POST['enabled'] ),
			'conditions' => self::posted_conditions( $context ),
		);
		// phpcs:enable
		$result = $id ? Templates::update( $id, $input ) : Templates::create( $input );
		if ( is_wp_error( $result ) ) {
			self::back( 'error', $result->get_error_message() );
		}
		self::back( 'success', __( 'Template rule saved.', 'kirki-woocommerce-bridge' ) );
	}

	/**
	 * Delete a rule.
	 *
	 * @return void
	 */
	public static function handle_delete_rule() {
		self::guard( 'kirki_woo_delete_rule' );
		$id = isset( $_POST['rule_id'] ) ? sanitize_key( wp_unslash( $_POST['rule_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		if ( Templates::delete( $id ) ) {
			self::back( 'success', __( 'Template rule deleted. The Kirki design was kept.', 'kirki-woocommerce-bridge' ) );
		}
		self::back( 'error', __( 'Template rule not found.', 'kirki-woocommerce-bridge' ) );
	}

	/**
	 * Create a design.
	 *
	 * @return void
	 */
	public static function handle_create_design() {
		self::guard( 'kirki_woo_create_design' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$starter = ! empty( $_POST['starter'] );
		// phpcs:enable
		$result = Templates::create_design( $context, $title, $starter, true );
		if ( is_wp_error( $result ) ) {
			self::back( 'error', $result->get_error_message() );
		}
		$link = '<a href="' . esc_url( $result['design']['editor_url'] ) . '">' . esc_html__( 'Open it in Kirki', 'kirki-woocommerce-bridge' ) . '</a>';
		self::back( 'success', sprintf( /* translators: %s: link. */ __( 'Design created and assigned. %s', 'kirki-woocommerce-bridge' ), $link ) );
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		self::guard( 'kirki_woo_save_settings' );
		$changes = array();
		foreach ( Settings::schema() as $key => $def ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
			if ( 'boolean' === $def['type'] ) {
				$changes[ $key ] = ! empty( $_POST[ $key ] );
			} elseif ( isset( $_POST[ $key ] ) ) {
				$changes[ $key ] = 'integer' === $def['type'] ? absint( $_POST[ $key ] ) : sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
			// phpcs:enable
		}
		$result = Settings::update( $changes );
		if ( is_wp_error( $result ) ) {
			self::back( 'error', $result->get_error_message() );
		}
		self::back( 'success', __( 'Settings saved.', 'kirki-woocommerce-bridge' ) );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( Api::CAP_MANAGE ) ) {
			return;
		}
		$contexts = Templates::contexts();
		$schema   = Templates::condition_schema();
		$designs  = Templates::designs();
		$rules    = Templates::all();
		$settings = Settings::all();
		$notice   = get_transient( 'kirki_woo_notice_' . get_current_user_id() );
		delete_transient( 'kirki_woo_notice_' . get_current_user_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection of the rule to edit.
		$edit_id       = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
		$editing       = $edit_id ? Templates::get( $edit_id ) : null;
		$design_titles = array();
		foreach ( $designs as $d ) {
			$design_titles[ $d['id'] ] = $d;
		}
		?>
		<div class="wrap kirki-woo-admin">
			<h1><?php esc_html_e( 'Kirki WooCommerce Templates', 'kirki-woocommerce-bridge' ); ?></h1>
			<p><?php esc_html_e( 'Design WooCommerce pages in Kirki, then assign each design to a WooCommerce context here. The highest-priority matching rule wins; ties go to the most specific rule.', 'kirki-woocommerce-bridge' ); ?></p>
			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $notice[0] ? 'error' : 'success'; ?> is-dismissible"><p><?php echo wp_kses( $notice[1], array( 'a' => array( 'href' => array() ) ) ); ?></p></div>
			<?php endif; ?>
			<?php self::render_compat_notice(); ?>

			<h2><?php esc_html_e( 'Template rules', 'kirki-woocommerce-bridge' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Title', 'kirki-woocommerce-bridge' ); ?></th>
					<th><?php esc_html_e( 'Context', 'kirki-woocommerce-bridge' ); ?></th>
					<th><?php esc_html_e( 'Design', 'kirki-woocommerce-bridge' ); ?></th>
					<th><?php esc_html_e( 'Conditions', 'kirki-woocommerce-bridge' ); ?></th>
					<th><?php esc_html_e( 'Priority', 'kirki-woocommerce-bridge' ); ?></th>
					<th><?php esc_html_e( 'Status', 'kirki-woocommerce-bridge' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'kirki-woocommerce-bridge' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $rules ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No rules yet. Create a design below to get started.', 'kirki-woocommerce-bridge' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rules as $rule ) : ?>
					<?php $design = isset( $design_titles[ $rule['design_id'] ] ) ? $design_titles[ $rule['design_id'] ] : null; ?>
					<tr>
						<td><strong><?php echo esc_html( $rule['title'] ); ?></strong></td>
						<td><?php echo esc_html( isset( $contexts[ $rule['context'] ] ) ? $contexts[ $rule['context'] ]['label'] : $rule['context'] ); ?></td>
						<td>
							<?php if ( $design ) : ?>
								<a href="<?php echo esc_url( $design['editor_url'] ); ?>"><?php echo esc_html( $design['title'] ); ?></a>
							<?php else : ?>
								<?php /* translators: %d: post id. */ echo esc_html( sprintf( __( 'Missing design #%d', 'kirki-woocommerce-bridge' ), $rule['design_id'] ) ); ?>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $rule['conditions'] ? wp_json_encode( $rule['conditions'] ) : __( 'All', 'kirki-woocommerce-bridge' ) ); ?></code></td>
						<td><?php echo (int) $rule['priority']; ?></td>
						<td><?php echo $rule['enabled'] ? esc_html__( 'Enabled', 'kirki-woocommerce-bridge' ) : esc_html__( 'Disabled', 'kirki-woocommerce-bridge' ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( self::url( array( 'edit' => $rule['id'] ) ) ); ?>#kirki-woo-rule"><?php esc_html_e( 'Edit', 'kirki-woocommerce-bridge' ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( 'kirki_woo_delete_rule' ); ?>
								<input type="hidden" name="action" value="kirki_woo_delete_rule" />
								<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ); ?>" />
								<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'kirki-woocommerce-bridge' ) ); ?>');"><?php esc_html_e( 'Delete', 'kirki-woocommerce-bridge' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div style="display:flex;gap:32px;flex-wrap:wrap;align-items:flex-start;margin-top:24px">
				<div class="card" style="max-width:520px">
					<h2><?php esc_html_e( 'Create a design', 'kirki-woocommerce-bridge' ); ?></h2>
					<p><?php esc_html_e( 'Creates a Kirki template with a ready-made layout of WooCommerce elements and assigns it.', 'kirki-woocommerce-bridge' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'kirki_woo_create_design' ); ?>
						<input type="hidden" name="action" value="kirki_woo_create_design" />
						<p><label for="kwi-design-context"><?php esc_html_e( 'Context', 'kirki-woocommerce-bridge' ); ?></label><br />
						<select id="kwi-design-context" name="context">
							<?php foreach ( $contexts as $key => $ctx ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $ctx['label'] ); ?></option>
							<?php endforeach; ?>
						</select></p>
						<p><label for="kwi-design-title"><?php esc_html_e( 'Title (optional)', 'kirki-woocommerce-bridge' ); ?></label><br />
						<input id="kwi-design-title" type="text" class="regular-text" name="title" /></p>
						<p><label><input type="checkbox" name="starter" value="1" checked /> <?php esc_html_e( 'Start from a layout', 'kirki-woocommerce-bridge' ); ?></label></p>
						<?php submit_button( __( 'Create design', 'kirki-woocommerce-bridge' ), 'primary', 'submit', false ); ?>
					</form>
				</div>

				<div class="card" id="kirki-woo-rule" style="max-width:620px">
					<h2><?php echo $editing ? esc_html__( 'Edit rule', 'kirki-woocommerce-bridge' ) : esc_html__( 'Add rule for an existing design', 'kirki-woocommerce-bridge' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'kirki_woo_save_rule' ); ?>
						<input type="hidden" name="action" value="kirki_woo_save_rule" />
						<input type="hidden" name="rule_id" value="<?php echo esc_attr( $editing ? $editing['id'] : '' ); ?>" />
						<p><label for="kwi-rule-title"><?php esc_html_e( 'Title', 'kirki-woocommerce-bridge' ); ?></label><br />
						<input id="kwi-rule-title" type="text" class="regular-text" name="title" value="<?php echo esc_attr( $editing ? $editing['title'] : '' ); ?>" /></p>
						<p><label for="kwi-rule-context"><?php esc_html_e( 'Context', 'kirki-woocommerce-bridge' ); ?></label><br />
						<select id="kwi-rule-context" name="context">
							<?php foreach ( $contexts as $key => $ctx ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $editing ? $editing['context'] : '', $key ); ?>><?php echo esc_html( $ctx['label'] ); ?></option>
							<?php endforeach; ?>
						</select></p>
						<p><label for="kwi-rule-design"><?php esc_html_e( 'Kirki design', 'kirki-woocommerce-bridge' ); ?></label><br />
						<select id="kwi-rule-design" name="design_id">
							<?php foreach ( $designs as $d ) : ?>
								<option value="<?php echo (int) $d['id']; ?>" <?php selected( $editing ? (int) $editing['design_id'] : 0, (int) $d['id'] ); ?>><?php echo esc_html( $d['title'] . ' (' . $d['post_type'] . ' #' . $d['id'] . ')' ); ?></option>
							<?php endforeach; ?>
						</select></p>
						<p><label for="kwi-rule-priority"><?php esc_html_e( 'Priority', 'kirki-woocommerce-bridge' ); ?></label><br />
						<input id="kwi-rule-priority" type="number" min="-1000" max="1000" name="priority" value="<?php echo (int) ( $editing ? $editing['priority'] : 10 ); ?>" /></p>
						<p><label><input type="checkbox" name="enabled" value="1" <?php checked( $editing ? $editing['enabled'] : true ); ?> /> <?php esc_html_e( 'Enabled', 'kirki-woocommerce-bridge' ); ?></label></p>
						<h3><?php esc_html_e( 'Conditions', 'kirki-woocommerce-bridge' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Leave empty to match everything in the context. Lists are comma separated ids or slugs.', 'kirki-woocommerce-bridge' ); ?></p>
						<table class="form-table" role="presentation">
						<?php
						foreach ( $schema as $key => $cond ) :
							$in = array();
							foreach ( $contexts as $ckey => $ctx ) {
								if ( in_array( $key, $ctx['conditions'], true ) ) {
									$in[] = $ckey;
								}
							}
							$current = $editing && isset( $editing['conditions'][ $key ] ) ? $editing['conditions'][ $key ] : null;
							?>
							<tr class="kwi-cond" data-contexts="<?php echo esc_attr( implode( ' ', $in ) ); ?>">
								<th scope="row"><label for="kwi-cond-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $key ); ?></label></th>
								<td>
								<?php if ( 'boolean' === $cond['type'] ) : ?>
									<select id="kwi-cond-<?php echo esc_attr( $key ); ?>" name="conditions[<?php echo esc_attr( $key ); ?>]">
										<option value=""><?php esc_html_e( 'Any', 'kirki-woocommerce-bridge' ); ?></option>
										<option value="yes" <?php selected( true === $current ); ?>><?php esc_html_e( 'Yes', 'kirki-woocommerce-bridge' ); ?></option>
										<option value="no" <?php selected( false === $current ); ?>><?php esc_html_e( 'No', 'kirki-woocommerce-bridge' ); ?></option>
									</select>
								<?php elseif ( 'string' === $cond['type'] && ! empty( $cond['enum'] ) ) : ?>
									<select id="kwi-cond-<?php echo esc_attr( $key ); ?>" name="conditions[<?php echo esc_attr( $key ); ?>]">
										<?php foreach ( $cond['enum'] as $opt ) : ?>
											<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( (string) $current, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input id="kwi-cond-<?php echo esc_attr( $key ); ?>" type="text" class="regular-text" name="conditions[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( is_array( $current ) ? implode( ',', $current ) : '' ); ?>" />
								<?php endif; ?>
								<p class="description"><?php echo esc_html( $cond['description'] ); ?></p>
								</td>
							</tr>
						<?php endforeach; ?>
						</table>
						<?php submit_button( $editing ? __( 'Update rule', 'kirki-woocommerce-bridge' ) : __( 'Add rule', 'kirki-woocommerce-bridge' ), 'primary', 'submit', false ); ?>
						<?php if ( $editing ) : ?>
							<a class="button" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Cancel', 'kirki-woocommerce-bridge' ); ?></a>
						<?php endif; ?>
					</form>
					<script>
					( function () {
						var sel = document.getElementById( 'kwi-rule-context' );
						function sync() {
							document.querySelectorAll( '.kwi-cond' ).forEach( function ( row ) {
								var show = ( ' ' + row.getAttribute( 'data-contexts' ) + ' ' ).indexOf( ' ' + sel.value + ' ' ) !== -1;
								row.style.display = show ? '' : 'none';
								row.querySelectorAll( 'input,select' ).forEach( function ( f ) { f.disabled = ! show; } );
							} );
						}
						sel.addEventListener( 'change', sync );
						sync();
					}() );
					</script>
				</div>
			</div>

			<h2 style="margin-top:32px"><?php esc_html_e( 'Settings', 'kirki-woocommerce-bridge' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'kirki_woo_save_settings' ); ?>
				<input type="hidden" name="action" value="kirki_woo_save_settings" />
				<table class="form-table" role="presentation">
				<?php foreach ( Settings::schema() as $key => $def ) : ?>
					<tr>
						<th scope="row"><label for="kwi-set-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></label></th>
						<td>
						<?php if ( 'boolean' === $def['type'] ) : ?>
							<input id="kwi-set-<?php echo esc_attr( $key ); ?>" type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
						<?php elseif ( 'integer' === $def['type'] ) : ?>
							<input id="kwi-set-<?php echo esc_attr( $key ); ?>" type="number" min="0" name="<?php echo esc_attr( $key ); ?>" value="<?php echo (int) $settings[ $key ]; ?>" />
						<?php else : ?>
							<input id="kwi-set-<?php echo esc_attr( $key ); ?>" type="text" class="regular-text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>" />
						<?php endif; ?>
						<p class="description"><?php echo esc_html( $def['description'] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
				</table>
				<?php submit_button( __( 'Save settings', 'kirki-woocommerce-bridge' ) ); ?>
			</form>
			<p class="description">
				<?php
				printf(
					/* translators: %s: REST namespace. */
					esc_html__( 'Everything on this screen is also available through the REST API (%s) and the WordPress Abilities API (kirki-woo/*).', 'kirki-woocommerce-bridge' ),
					'<code>' . esc_html( KIRKI_WOO_API_NAMESPACE ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Explain how this plugin and Kirki eCommerce share the site.
	 *
	 * @return void
	 */
	private static function render_compat_notice() {
		if ( ! Compat::kirki_ecommerce_active() ) {
			return;
		}
		$conflicts = Compat::page_conflicts();
		?>
		<div class="notice notice-<?php echo $conflicts ? 'warning' : 'info'; ?> inline">
			<p><strong><?php esc_html_e( 'Kirki eCommerce is also active.', 'kirki-woocommerce-bridge' ); ?></strong>
			<?php esc_html_e( 'Both stores run side by side without sharing elements, shortcodes, REST routes or settings. Kirki eCommerce keeps its own pages and catalogue; the designs on this screen only apply to WooCommerce pages and products.', 'kirki-woocommerce-bridge' ); ?></p>
			<?php foreach ( $conflicts as $conflict ) : ?>
				<p>
				<?php
				printf(
					/* translators: 1: page title, 2: Kirki eCommerce page key, 3: WooCommerce page key. */
					esc_html__( 'Conflict: the page "%1$s" is both the Kirki eCommerce %2$s page and the WooCommerce %3$s page. Assign a different page in one of the plugins.', 'kirki-woocommerce-bridge' ),
					esc_html( get_the_title( $conflict['page_id'] ) ),
					esc_html( $conflict['kirki_ecommerce'] ),
					esc_html( $conflict['woocommerce'] )
				);
				?>
				</p>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
