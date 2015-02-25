<?php
	class Tracks_List_Table extends WP_List_Table {

			function __construct( $options ) {
					global $status, $page;

					$this -> options = $options;

					//Set parent defaults
					parent::__construct( array(
						'singular' => 'track',    //singular name of the listed records
						'plural'   => 'tracks',   //plural name of the listed records
						'ajax'     => false       //does this table support ajax?
					) );
			}

			function column_default( $item, $column_name ) {
				if ( $column_name == 'edit' ) {
					return ' <a href="#TB_inline?width=&inlineId=ts-edit-modal" title="Edit track properties" class="thickbox" data-id="'.
						$item['id'] . '" data-action="edit">Edit</a>';
				}
				elseif ( $column_name == 'view' ) {
					return ' <a href="#TB_inline?width=&inlineId=ts-view-modal" title="' . htmlspecialchars( $item['name'] ) .
						'" class="thickbox" data-id="' . $item['id'] . '" data-action="view">View</a>';
				}
				elseif ( $column_name == 'nonce' ) {
					return wp_create_nonce( 'manage_track_' . $item['id'] );
				}
				else {
					return htmlspecialchars( $item[ $column_name ] );
				}
				return print_r( $item, true );    //Show the whole array for troubleshooting purposes
			}

			function column_cb( $item ) {
				return sprintf(
					'<input type="checkbox" name="%1$s[]" value="%2$s" />',
					/*$1%s*/ $this -> _args['singular'],  //Let's simply repurpose the table's singular label ("movie")
					/*$2%s*/ $item['id']                  //The value of the checkbox should be the record's id
				);
			}

			function get_columns() {
				$columns = array(
					'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
					'id'        => 'ID',
					'name'      => 'Name',
					'tstart'    => 'Start',
					'tend'      => 'End',
					'numpoints' => 'Points',
					'distance'  => 'Distance',
					'source'    => 'Source',
					'comment'   => 'Comment',
					'view'      => 'View',
					'edit'      => 'Edit',
					'nonce'     => 'Nonce',
				);
				return $columns;
			}

			function get_sortable_columns() {
				return array(
					'id'     => array( 'id', false ),
					'name'   => array( 'name', false ),
					'tstart' => array( 'tstart', false ),
					'tend'   => array( 'tend', false ),
					'source' => array( 'source', false )
				);
			}

			function get_bulk_actions() {
				$actions = array(
					'delete' => 'Delete',
					'merge'  => 'Merge',
				);
				return $actions;
			}

			function get_current_action() {
				$action = $this -> current_action();
				if ( $action && array_key_exists( $action, $this -> get_bulk_actions() ) ) {
					return $action;
				}
				return false;
			}

			function extra_tablenav( $where ) {
				echo '<div class="alignleft actions bulkactions">';
				echo '<input id="addtrack-button" class="button action" type="submit" value="Upload tracks" name="">';
				echo '</div>';
			}

			function prepare_items() {
				global $wpdb;

				$per_page = 20;
				$columns  = $this -> get_columns();
				$hidden   = array( 'nonce' );
				$sortable = $this -> get_sortable_columns();

				$this -> _column_headers = array( $columns, $hidden, $sortable );

				# This should be prettier
				$orderby = 'tstart';
				if ( ! empty( $_REQUEST['orderby'] ) &&
					in_array( $_REQUEST['orderby'], array( 'id', 'name', 'tstart', 'tend', 'source' ) ) ) {
						$orderby = $_REQUEST['orderby'];
				}
				$order = 'DESC';
				if ( ! empty( $_REQUEST['order'] ) &&
					in_array( $_REQUEST['order'], array( 'asc', 'desc' ) ) ) {
						$order = $_REQUEST['order'];
				}

				$current_page = $this -> get_pagenum();
				$offset = ( $current_page - 1 ) * $per_page;
				$limit = $per_page;

				$sql = 'SELECT t.id, t.name, t.source, t.comment, min(l.occurred) as tstart, max(l.occurred) ' .
					'as tend, count(l.occurred) as numpoints, t.distance FROM '.
					$this -> options['tbl_tracks'] . ' t INNER JOIN ' . $this -> options['tbl_locations'] .
					" l ON l.trip_id = t.id WHERE user_id='" . get_current_user_id() .
					"' GROUP BY l.trip_id ORDER BY $orderby $order LIMIT $offset,$limit";
				$data = $wpdb -> get_results( $sql, ARRAY_A );

				/*
				 * REQUIRED for pagination. Let's check how many items are in our data array.
				 * In real-world use, this would be the total number of items in your database,
				 * without filtering. We'll need this later, so you should always include it
				 * in your own package classes.
				 */
				$sql = "SELECT count(id) FROM " . $this -> options['tbl_tracks'] . " WHERE user_id='" . get_current_user_id() . "'";
				$total_items = $wpdb -> get_var( $sql );

				/*
				 * REQUIRED. Now we can add our *sorted* data to the items property, where
				 * it can be used by the rest of the class.
				 */
				$this -> items = $data;

				/**
				 * REQUIRED. We also have to register our pagination options & calculations.
				 */
				$this -> set_pagination_args( array(
					'total_items' => $total_items,                  // WE have to calculate the total number of items
					'per_page'    => $per_page,                     // WE have to determine how many items to show on a page
					'total_pages' => ceil($total_items/$per_page)   // WE have to calculate the total number of pages
				) );

		}
	}
