<?php
class Tracks_List_Table extends WP_List_Table {

	private $options;
	private $usercache = array();
	private $total_items;

	public function __construct( $options ) {
			global $status, $page;

			$this->options = $options;

			// Set parent defaults.
			parent::__construct(
				array(
					'singular' => 'track',    // Singular name of the listed records.
					'plural'   => 'tracks',   // Plural name of the listed records.
					'ajax'     => false,      // Does this table support ajax?
				)
			);
	}

	public function column_default( $item, $column_name ) {
		if ( $column_name === 'edit' ) {
			return ' <a href="#TB_inline?width=&inlineId=trackserver-edit-modal" title="' . esc_attr__( 'Edit track properties', 'trackserver' ) .
				'" class="thickbox" data-id="' . $item['id'] . '" data-action="edit">' . esc_html__( 'Edit', 'trackserver' ) . '</a>';
		} elseif ( $column_name === 'view' ) {
			// Unfortunately, the double HTML escaping is necessary to prevent ThickBox from rendering it as HTML.
			return ' <a href="#TB_inline?width=&inlineId=trackserver-view-modal" name="' . htmlspecialchars( htmlspecialchars( $item['name'] ) ) .
				'" class="thickbox" data-id="' . $item['id'] . '" data-action="view">' . esc_html__( 'View', 'trackserver' ) . '</a>';
		} elseif ( $column_name === 'nonce' ) {
			return wp_create_nonce( 'manage_track_' . $item['id'] );
		} elseif ( $column_name === 'user_id' ) {
			if ( ! isset( $this->usercache[ $item['user_id'] ] ) ) {
				$user          = get_userdata( $item['user_id'] );
				$u             = new stdClass();
				$u->user_id    = $item['user_id'];
				$u->user_login = $user->user_login;

				$this->usercache[ $item['user_id'] ] = $u;
			}
			return $this->usercache[ $item['user_id'] ]->user_login;
		} else {
			return htmlspecialchars( $item[ $column_name ] );
		}
		return print_r( $item, true );    // Show the whole array for troubleshooting purposes.
	}

	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ $this->_args['singular'],  // Let's simply repurpose the table's singular label ("movie").
			/*$2%s*/ $item['id']                  // The value of the checkbox should be the record's id.
		);
	}

	public function get_columns() {
		$columns = array(
			'cb'        => '<input type="checkbox" />', // Render a checkbox instead of text.
			'id'        => esc_html__( 'ID', 'trackserver' ),
			'user_id'   => esc_html__( 'User', 'trackserver' ),
			'name'      => esc_html__( 'Name', 'trackserver' ),
			'tstart'    => esc_html__( 'Start', 'trackserver' ),
			'tend'      => esc_html__( 'End', 'trackserver' ),
			'numpoints' => esc_html__( 'Points', 'trackserver' ),
			'distance'  => esc_html__( 'Distance', 'trackserver' ),
			'source'    => esc_html__( 'Source', 'trackserver' ),
			'comment'   => esc_html__( 'Comment', 'trackserver' ),
			'view'      => esc_html__( 'View', 'trackserver' ),
			'edit'      => esc_html__( 'Edit', 'trackserver' ),
			'nonce'     => 'Nonce',
		);
		return $columns;
	}

	public function get_sortable_columns() {
		return array(
			'id'      => array( 'id', false ),
			'user_id' => array( 'user_id', false ),
			'name'    => array( 'name', false ),
			'tstart'  => array( 'tstart', false ),
			'tend'    => array( 'tend', false ),
			'source'  => array( 'source', false ),
		);
	}

	public function get_bulk_actions() {
		$actions = array(
			'delete'    => esc_html__( 'Delete', 'trackserver' ),
			'merge'     => esc_html__( 'Merge', 'trackserver' ),
			'duplicate' => esc_html__( 'Duplicate', 'trackserver' ),
			'dlgpx'     => esc_html__( 'Download as GPX', 'trackserver' ),
			'recalc'    => esc_html__( 'Recalculate', 'trackserver' ),
			'view'      => esc_html__( 'View', 'trackserver' ),
		);
		return $actions;
	}

	public function get_current_action() {
		$action = $this->current_action();
		if ( $action && array_key_exists( $action, $this->get_bulk_actions() ) ) {
			return $action;
		}
		return false;
	}

	public function extra_tablenav( $which ) {
		global $wpdb;

		$sql = "SELECT DISTINCT t.user_id, COALESCE(u.user_login, CONCAT('unknown UID ', t.user_id)) AS user_login FROM " .
			$this->options['tbl_tracks'] . ' t LEFT JOIN ' .
			$wpdb->users . ' u  ON t.user_id = u.ID ORDER BY user_login';

		$this->usercache = $wpdb->get_results( $sql, OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$view                = $this->options['view'];
		$author_select_name  = "author-$which";
		$author_select_id    = "author-select-$which";
		$addtrack_button_id  = "addtrack-button-$which";
		$perpage_select_name = "per-page-$which";
		$perpage_select_id   = "per-page-select-$which";
		$perpage_values      = array( 20, 50, 100 );

		echo '<div class="alignleft actions" style="padding-bottom: 1px; line-height: 32px">';
		echo '<input id="' . $addtrack_button_id . '" class="button action" style="margin: 1px 8px 0 0" type="button" value="' . esc_attr__( 'Upload tracks', 'trackserver' ) . '" name="">';
		if ( current_user_can( 'trackserver_admin' ) ) {
			echo '<select name="' . $author_select_name . '" id="' . $author_select_id . '" class="postform">';
			echo '<option value="0">All users</option>';
			foreach ( $this->usercache as $u ) {
				echo '<option class="level-0" value="' . $u->user_id . '"';
				if ( (int) $u->user_id === (int) $view ) {
					echo ' selected';
				}
				echo '>' . htmlspecialchars( $u->user_login ) . '</option>';
			}
			echo '</select>';
		}
		echo '</div>';

		echo '<div class="tablenav-pages"> &nbsp;';
		echo '<span class="paging-input"> Show ';
		echo '<select name="' . $perpage_select_name . '" id="' . $perpage_select_id . '" class="postform">';
		foreach ( $perpage_values as $npp ) {
			echo '<option value="' . $npp . '"';
			if ( $npp === $this->options['per_page'] ) {
				echo ' selected';
			}
			echo '>' . $npp . '</option>';
		}
		echo '</select> items';
		echo '</span></div>';
	}

	public function prepare_items( $search = '' ) {
		global $wpdb;

		$per_page = $this->options['per_page'];
		$columns  = $this->get_columns();
		$hidden   = array( 'nonce' );
		$sortable = $this->get_sortable_columns();

		// This should be prettier.
		$orderby = 'tstart';
		if ( ! empty( $_REQUEST['orderby'] ) &&
			in_array( $_REQUEST['orderby'], array( 'id', 'user_id', 'name', 'tstart', 'tend', 'source' ), true ) ) {
				$orderby = $_REQUEST['orderby'];
		}
		$order = 'DESC';
		if ( ! empty( $_REQUEST['order'] ) &&
			in_array( $_REQUEST['order'], array( 'asc', 'desc' ), true ) ) {
				$order = $_REQUEST['order'];
		}

		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;
		$limit        = $per_page;

		$where = "user_id='" . get_current_user_id() . "'";
		if ( current_user_can( 'trackserver_admin' ) ) {
			if ( 0 === (int) $this->options['view'] ) {
				$where = 1;
			} else {
				$where = "user_id='" . $this->options['view'] . "'";
			}
		}

		if ( ! empty( $search ) ) {
			$like   = '%' . esc_sql( $wpdb->esc_like( $search ) ) . '%';
			$like   = str_replace( '\\\\_', '\\_', $like );    // underscores are double-escaped without this
			$where .= " AND (t.name LIKE '$like' OR t.source LIKE '$like' OR t.comment LIKE '$like')";
		}

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$sql = 'SELECT t.id, t.name, t.source, t.comment, user_id, COALESCE(MIN(l.occurred), t.created) AS tstart, ' .
			'COALESCE(MAX(l.occurred), t.created) AS tend, COALESCE(COUNT(l.occurred), 0) AS numpoints, t.distance FROM ' .
			$this->options['tbl_tracks'] . ' t LEFT JOIN ' . $this->options['tbl_locations'] .
			" l ON l.trip_id = t.id WHERE $where GROUP BY t.id ORDER BY $orderby $order LIMIT $offset,$limit";

		$data = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		/*
		 * REQUIRED for pagination. Let's check how many items are in our data array.
		 */
		$sql               = 'SELECT count(id) FROM ' . $this->options['tbl_tracks'] . " t WHERE $where";
		$total_items       = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->total_items = $total_items;

		/*
		 * REQUIRED. Now we can add our *sorted* data to the items property, where
		 * it can be used by the rest of the class.
		 */
		$this->items = $data;

		/**
		 * REQUIRED. We also have to register our pagination options & calculations.
		 */
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,                       // WE have to calculate the total number of items.
				'per_page'    => $per_page,                          // WE have to determine how many items to show on a page.
				'total_pages' => ceil( $total_items / $per_page ),   // WE have to calculate the total number of pages.
			)
		);

	}

	public function get_views() {
		return array( 'all' => '<a href="' . admin_url() . 'admin.php?page=trackserver-tracks' . '">' . esc_html__( 'All tracks' ) . '</a> (' . $this->total_items . ')' );
	}
}
